<?php

namespace Darvis\Mailtrap\Models;

use Illuminate\Database\Eloquent\Model;

class EmailValidation extends Model
{
    protected $table = 'email_validations';

    protected $fillable = [
        'email',
        'domain',
        'status',
        'reason',
        'status_code',
        'last_checked_at'
    ];

    protected $casts = [
        'last_checked_at' => 'datetime'
    ];

    public static function validateEmail(string $email): ?string
    {
        // Controleer eerst of er al een record bestaat
        $existingValidation = static::where('email', $email)->first();

        if ($existingValidation) {
            // Als het record bestaat en niet valid is, return de foutmelding
            if ($existingValidation->status !== 'valid') {
                return $existingValidation->reason;
            }
            // Als het record bestaat en valid is, return null (geen fout)
            return null;
        }

        // Geen bestaand record gevonden, voer volledige validatie uit
        $domain = substr(strrchr($email, "@"), 1);

        // Basic validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Invalid email format';
            static::saveValidation($email, 'blocked', $errorMessage, 400);
            return $errorMessage;
        }

        // Check MX records
        if (!checkdnsrr($domain, 'MX')) {
            $errorMessage = 'No valid mail server found for domain';
            static::saveValidation($email, 'blocked', $errorMessage, 400);
            return $errorMessage;
        }
        // Controleer of een van de MX records naar een geldig IP-adres verwijst
        $mxHosts = [];
        $mxWeights = [];
        if (getmxrr($domain, $mxHosts, $mxWeights)) {
            $validIp = false;
            foreach ($mxHosts as $host) {
                $ip = gethostbyname($host);
                if (filter_var($ip, FILTER_VALIDATE_IP) && $ip !== $host) {
                    $validIp = true;
                    break;
                }
            }
            if (!$validIp) {
                $errorMessage = 'MX record verwijst niet naar geldig IP-adres';
                static::saveValidation($email, 'blocked', $errorMessage, 400);
                return $errorMessage;
            }
        } else {
            $errorMessage = 'MX records niet gevonden';
            static::saveValidation($email, 'blocked', $errorMessage, 400);
            return $errorMessage;
        }

        // If all checks pass, mark as valid
        static::saveValidation($email, 'valid', 'All checks passed', 200);
        return null;
    }

    public static function saveValidation($email, $status, $reason, $status_code)
    {
        $data = [
            'email' => $email,
            'domain' => substr(strrchr($email, "@"), 1),
            'status' => $status,
            'reason' => $reason,
            'status_code' => $status_code,
            'last_checked_at' => now(),
        ];
        static::updateOrCreate([
            'email' => $email
        ], $data);
    }

    public static function isBlocked(string $email): bool
    {
        $domain = substr(strrchr($email, "@"), 1);

        return static::where(function ($query) use ($email, $domain) {
            $query->where('email', $email)
                ->orWhere('domain', $domain);
        })->where('status', 'blocked')
            ->exists();
    }

    /**
     * Get the reason why an email is blocked
     *
     * @param string $email
     * @return string|null
     */
    public static function getBlockReason(string $email): ?string
    {
        $domain = substr(strrchr($email, "@"), 1);

        $validation = static::where(function ($query) use ($email, $domain) {
            $query->where('email', $email)
                ->orWhere('domain', $domain);
        })->where('status', 'blocked')
            ->first();

        return $validation?->reason;
    }

    public static function isValid(string $email): bool
    {
        $domain = substr(strrchr($email, "@"), 1);

        return static::where(function ($query) use ($email, $domain) {
            $query->where('email', $email)
                ->orWhere('domain', $domain);
        })->where('status', 'valid')
            ->exists();
    }

    public static function markAsValid(string $email): self
    {
        $domain = substr(strrchr($email, "@"), 1);

        return static::updateOrCreate(
            ['email' => $email],
            [
                'domain' => $domain,
                'status' => 'valid',
                'reason' => 'Email validated successfully',
                'last_checked_at' => now()
            ]
        );
    }

    public static function markAsInvalid(string $email, string $reason, ?string $statusCode = null): self
    {
        $domain = substr(strrchr($email, "@"), 1);

        return static::updateOrCreate(
            ['email' => $email],
            [
                'domain' => $domain,
                'status' => 'invalid',
                'reason' => $reason,
                'status_code' => $statusCode,
                'last_checked_at' => now()
            ]
        );
    }

    public static function markAsBlocked(string $email, string $reason, ?string $statusCode = null): self
    {
        $domain = substr(strrchr($email, "@"), 1);

        return static::updateOrCreate(
            ['email' => $email],
            [
                'domain' => $domain,
                'status' => 'blocked',
                'reason' => $reason,
                'status_code' => $statusCode,
                'last_checked_at' => now()
            ]
        );
    }

    /**
     * Bulk validatie van mailadressen
     * 
     * @param array $emails Array van mailadressen om te controleren
     * @return array Resultaat met telling van valid, invalid/blocked en niet bestaande mailadressen
     */
    public static function bulkValidationStatus(array $emails): array
    {
        $result = [
            'valid' => 0,
            'invalid' => 0,
            'not_exists' => 0,
            'total' => count($emails),
            'details' => []
        ];

        // Haal alle bestaande validaties op in één query
        $existingValidations = static::whereIn('email', $emails)
            ->get()
            ->keyBy('email');

        foreach ($emails as $email) {
            if (isset($existingValidations[$email])) {
                $validation = $existingValidations[$email];
                $status = $validation->status;

                if ($status === 'valid') {
                    $result['valid']++;
                    $result['details'][$email] = [
                        'status' => 'valid',
                        'reason' => $validation->reason,
                        'last_checked_at' => $validation->last_checked_at
                    ];
                } else {
                    // Status is 'blocked' of 'invalid'
                    $result['invalid']++;
                    $result['details'][$email] = [
                        'status' => $status,
                        'reason' => $validation->reason,
                        'last_checked_at' => $validation->last_checked_at
                    ];
                }
            } else {
                // Mailadres bestaat niet in database
                $result['not_exists']++;
                $result['details'][$email] = [
                    'status' => 'not_exists',
                    'reason' => 'Mailadres niet gevonden in database',
                    'last_checked_at' => null
                ];
            }
        }

        return $result;
    }

    /**
     * Bulk validatie met optie om ontbrekende mailadressen direct te valideren
     * 
     * @param array $emails Array van mailadressen om te controleren
     * @param bool $validateMissing Of ontbrekende mailadressen direct gevalideerd moeten worden
     * @return array Resultaat met telling van valid, invalid/blocked en niet bestaande mailadressen
     */
    public static function bulkValidationWithCheck(array $emails, bool $validateMissing = false): array
    {
        $result = static::bulkValidationStatus($emails);

        if ($validateMissing && $result['not_exists'] > 0) {
            // Valideer alle mailadressen die niet bestaan
            $missingEmails = [];
            foreach ($result['details'] as $email => $details) {
                if ($details['status'] === 'not_exists') {
                    $missingEmails[] = $email;
                }
            }

            // Valideer elk ontbrekend mailadres
            foreach ($missingEmails as $email) {
                $isValid = static::validateEmail($email);

                // Update het resultaat
                $result['not_exists']--;
                if ($isValid) {
                    $result['valid']++;
                    $result['details'][$email] = [
                        'status' => 'valid',
                        'reason' => 'All checks passed',
                        'last_checked_at' => now()
                    ];
                } else {
                    $result['invalid']++;
                    // Haal de nieuwe validatie op voor de details
                    $validation = static::where('email', $email)->first();
                    $result['details'][$email] = [
                        'status' => $validation->status,
                        'reason' => $validation->reason,
                        'last_checked_at' => $validation->last_checked_at
                    ];
                }
            }
        }

        return $result;
    }
}
