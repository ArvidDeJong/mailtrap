<?php

namespace Darvis\Mailtrap\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Reject webhook calls that are not signed by Mailtrap.
 *
 * Mailtrap signs every webhook with an HMAC-SHA256 of the raw request body,
 * hex encoded, in the `Mailtrap-Signature` header. The signing secret is the
 * 32-character hex string shown in the webhook detail panel in Mailtrap.
 *
 * @see https://docs.mailtrap.io/email-api-smtp/advanced/webhooks
 */
class VerifyMailtrapWebhookSignature
{
    public const HEADER = 'Mailtrap-Signature';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): SymfonyResponse  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (! config('manta_mailtrap.webhook.verify_signature', true)) {
            return $next($request);
        }

        $secret = (string) config('manta_mailtrap.webhook.secret', '');

        // Fail closed: an unset secret means the endpoint cannot be trusted, and
        // waving requests through would leave it open to anyone who knows the URL.
        if ($secret === '') {
            $this->log('warning', 'Mailtrap webhook rejected: no signing secret configured. Set MAILTRAP_WEBHOOK_SECRET, or set MAILTRAP_WEBHOOK_VERIFY_SIGNATURE=false to accept unsigned calls.');

            return $this->reject('Webhook signing secret is not configured');
        }

        $signature = (string) $request->header(self::HEADER, '');

        if ($signature === '') {
            $this->log('warning', 'Mailtrap webhook rejected: missing '.self::HEADER.' header.');

            return $this->reject('Missing webhook signature');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            $this->log('warning', 'Mailtrap webhook rejected: signature mismatch.');

            return $this->reject('Invalid webhook signature');
        }

        return $next($request);
    }

    /**
     * Build the rejection response.
     */
    private function reject(string $message): SymfonyResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Write to the Laravel log only when the package is configured to do so.
     *
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (! config('manta_mailtrap.logging.log_to_laravel', false)) {
            return;
        }

        Log::{$level}($message, $context);
    }
}
