<?php

namespace Darvis\Mailtrap\Services;

use Darvis\Mailtrap\Models\EmailValidation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailtrapService
{



    protected string $apiToken;
    protected string $baseUrl = 'https://api.mailtrap.io/api/v1';

    public function __construct()
    {
        $this->apiToken = config('services.mailtrap.api_token') ?? 'test-token';
    }

    public function validateEmail(string $email): bool
    {
        // Check if we already validated this email or domain
        if (EmailValidation::isValid($email)) {
            return true;
        }

        // Check if this email or domain is blocked
        if (EmailValidation::isBlocked($email)) {
            return false;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/accounts/validate", [
                    'email' => $email
                ]);

            if (!$response->successful()) {
                // Only block on server errors (500+), treat client errors (400-499) as invalid
                if ($response->status() >= 500) {
                    $this->handleFailure($email, 'API validation failed', $response->status());
                } else {
                    $this->handleInvalid($email, 'API validation failed', $response->status());
                }
                return false;
            }

            $data = $response->json();

            if (!$data['success']) {
                // Invalid emails should be marked as invalid, not blocked
                $this->handleInvalid($email, $data['message'] ?? 'Invalid email', $response->status());
                return false;
            }

            // Email is valid, store it
            EmailValidation::markAsValid($email);

            // Return true for valid email
            return true;
        } catch (\Exception $e) {
            Log::error('Mailtrap API error: ' . $e->getMessage());

            // Unexpected errors should block the email to prevent excessive retries
            $this->handleFailure($email, 'API error: ' . $e->getMessage());
            return false;
        }
    }

    protected function handleInvalid(string $email, string $reason, ?string $statusCode = null): void
    {
        EmailValidation::markAsInvalid($email, $reason, $statusCode);
    }

    protected function handleFailure(string $email, string $reason, ?string $statusCode = null): void
    {
        EmailValidation::markAsBlocked($email, $reason, $statusCode);
    }
}
