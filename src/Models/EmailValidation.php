<?php

namespace Darvis\Mailtrap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cached verdict on whether mail to an address can be sent.
 *
 * - `valid`: passed the local checks or was confirmed by a delivery, open or click event.
 * - `invalid`: failed at the recipient (bounce, spam, reject). Recorded, but does not stop sending.
 * - `blocked`: hard stop; the outgoing mail listener aborts the send with a TransportException.
 *
 * @property int $id
 * @property string|null $email
 * @property string|null $domain
 * @property string $status One of the VALID, INVALID or BLOCKED constants.
 * @property string $reason
 * @property string|null $status_code
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmailValidation extends Model
{
    public const VALID = 'valid';

    public const INVALID = 'invalid';

    public const BLOCKED = 'blocked';

    protected $table = 'email_validations';

    protected $fillable = [
        'email',
        'domain',
        'status',
        'reason',
        'status_code',
        'last_checked_at',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];

    /**
     * Validate an address and cache the verdict.
     *
     * Checks the format, then whether the domain has a mail server that resolves
     * to an IP address. A domain on which another address is already valid skips
     * the DNS lookups, which run synchronously while a mail is being sent.
     *
     * @return string|null Null when the address is valid, otherwise the reason it is not.
     */
    public static function validateEmail(string $email): ?string
    {
        $existing = static::where('email', $email)->first();

        if ($existing && ! static::isStale($existing)) {
            return $existing->status === self::VALID ? null : $existing->reason;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return static::rejectLocally($email, 'Invalid email format');
        }

        $domain = static::domainOf($email);

        if (static::where('domain', $domain)->where('status', self::VALID)->exists()) {
            static::saveValidation($email, self::VALID, 'Domain already verified', 200);

            return null;
        }

        $mxHosts = [];

        if (! getmxrr($domain, $mxHosts) || $mxHosts === []) {
            return static::rejectLocally($email, 'No valid mail server found for domain');
        }

        $resolves = collect($mxHosts)->contains(function (string $host): bool {
            $ip = gethostbyname($host);

            return $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP) !== false;
        });

        if (! $resolves) {
            return static::rejectLocally($email, 'MX record does not resolve to a valid IP address');
        }

        static::saveValidation($email, self::VALID, 'All checks passed', 200);

        return null;
    }

    /**
     * Get the domain part of an address, or an empty string when there is none.
     */
    public static function domainOf(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '' : strtolower(substr($email, $at + 1));
    }

    public static function saveValidation(string $email, string $status, string $reason, int|string|null $statusCode): self
    {
        return static::updateOrCreate(['email' => $email], [
            'domain' => static::domainOf($email),
            'status' => $status,
            'reason' => $reason,
            'status_code' => $statusCode === null ? null : (string) $statusCode,
            'last_checked_at' => now(),
        ]);
    }

    /**
     * Whether sending to this address is blocked.
     *
     * Blocking is per address. A blocked address says nothing about the rest of
     * its domain: a typo, a manual block or a failed API call would otherwise
     * stop all mail to gmail.com.
     */
    public static function isBlocked(string $email): bool
    {
        return static::getBlockReason($email) !== null;
    }

    /**
     * Get the reason why an address is blocked, or null when it is not.
     */
    public static function getBlockReason(string $email): ?string
    {
        return static::where('email', $email)
            ->where('status', self::BLOCKED)
            ->value('reason');
    }

    /**
     * Whether the address, or any other address on its domain, is known to be valid.
     */
    public static function isValid(string $email): bool
    {
        return static::where('status', self::VALID)
            ->where(fn ($query) => $query
                ->where('email', $email)
                ->orWhere('domain', static::domainOf($email)))
            ->exists();
    }

    public static function markAsValid(string $email): self
    {
        return static::saveValidation($email, self::VALID, 'Email validated successfully', 200);
    }

    public static function markAsInvalid(string $email, string $reason, int|string|null $statusCode = null): self
    {
        return static::saveValidation($email, self::INVALID, $reason, $statusCode);
    }

    public static function markAsBlocked(string $email, string $reason, int|string|null $statusCode = null): self
    {
        return static::saveValidation($email, self::BLOCKED, $reason, $statusCode);
    }

    /**
     * Look up the cached verdict for a list of addresses without validating them.
     *
     * @param  array<int, string>  $emails
     * @return array{valid: int, invalid: int, not_exists: int, total: int, details: array<string, array{status: string, reason: string, last_checked_at: Carbon|null}>}
     */
    public static function bulkValidationStatus(array $emails): array
    {
        $validations = static::whereIn('email', $emails)->get()->keyBy('email');

        $result = ['valid' => 0, 'invalid' => 0, 'not_exists' => 0, 'total' => count($emails), 'details' => []];

        foreach ($emails as $email) {
            $validation = $validations->get($email);

            // "invalid" counts both invalid and blocked addresses.
            $bucket = match ($validation?->status) {
                null => 'not_exists',
                self::VALID => 'valid',
                default => 'invalid',
            };

            $result[$bucket]++;
            $result['details'][$email] = [
                'status' => $validation->status ?? 'not_exists',
                'reason' => $validation->reason ?? 'Email address not found in database',
                'last_checked_at' => $validation?->last_checked_at,
            ];
        }

        return $result;
    }

    /**
     * Like bulkValidationStatus(), optionally validating addresses that have no verdict yet.
     *
     * @param  array<int, string>  $emails
     * @return array{valid: int, invalid: int, not_exists: int, total: int, details: array<string, array{status: string, reason: string, last_checked_at: Carbon|null}>}
     */
    public static function bulkValidationWithCheck(array $emails, bool $validateMissing = false): array
    {
        $result = static::bulkValidationStatus($emails);

        if (! $validateMissing || $result['not_exists'] === 0) {
            return $result;
        }

        foreach ($result['details'] as $email => $details) {
            if ($details['status'] === 'not_exists') {
                static::validateEmail((string) $email);
            }
        }

        // validateEmail() stores every verdict, so a second lookup is complete.
        return static::bulkValidationStatus($emails);
    }

    /**
     * Whether a stored verdict should be checked again.
     *
     * Only blocked records expire, so a DNS outage or a briefly missing MX record
     * does not block an address for good. Valid and invalid verdicts come from
     * Mailtrap events, which an MX lookup cannot reproduce, so they stay.
     */
    protected static function isStale(self $validation): bool
    {
        if ($validation->status !== self::BLOCKED) {
            return false;
        }

        $cacheDuration = (int) config('manta_mailtrap.validation.cache_duration', 3600);

        if ($cacheDuration <= 0) {
            return false;
        }

        return $validation->last_checked_at === null
            || $validation->last_checked_at->addSeconds($cacheDuration)->isPast();
    }

    /**
     * Block an address that failed a local check and return the reason.
     */
    protected static function rejectLocally(string $email, string $reason): string
    {
        static::saveValidation($email, self::BLOCKED, $reason, 400);

        return $reason;
    }
}
