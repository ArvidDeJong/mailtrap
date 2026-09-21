<?php

namespace Darvis\Mailtrap\Http\Controllers;

use Darvis\Mailtrap\Events\MailtrapEventReceived;
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Darvis\Mailtrap\Support\PackageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MailtrapWebhookController extends Controller
{
    /**
     * What each Mailtrap event says about the address, and the status code to
     * record when the event carries none.
     *
     * Other events (soft bounce, unsubscribe, suspension) are acknowledged and
     * counted as skipped.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const EVENTS = [
        'delivery' => [EmailValidation::VALID, 200],
        'open' => [EmailValidation::VALID, 200],
        'click' => [EmailValidation::VALID, 200],
        'bounce' => [EmailValidation::INVALID, 550],
        'spam' => [EmailValidation::INVALID, 400],
        'reject' => [EmailValidation::INVALID, 450],
    ];

    /**
     * Handle a batch of Mailtrap events (up to 500, sent every 30 seconds).
     *
     * Always answers 200 once the payload is readable, so a failing event does
     * not make Mailtrap retry the whole batch; failures are counted as skipped.
     */
    public function handle(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        PackageLog::info('Mailtrap webhook received', ['payload' => $request->all()]);

        $events = $request->input('events');

        if (! is_array($events)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No valid events found in webhook payload',
            ], 400);
        }

        $stats = ['valid_emails' => 0, 'invalid_emails' => 0, 'skipped' => 0];
        $handled = [];

        foreach ($events as $event) {
            if (! is_array($event) || ! is_string($event['email'] ?? null) || ! is_string($event['event'] ?? null)) {
                $stats['skipped']++;

                continue;
            }

            // Several events for one address are normal (a delivery, then an open);
            // only the very same event twice is a duplicate.
            $key = $event['event_id'] ?? implode('|', [$event['message_id'] ?? '', $event['event'], $event['email']]);

            if (isset($handled[$key])) {
                $stats['skipped']++;

                continue;
            }

            $handled[$key] = true;

            $context = array_filter([
                'event' => $event['event'],
                'email' => $event['email'],
                'message_id' => $event['message_id'] ?? null,
                'category' => $event['category'] ?? null,
                'response' => $event['response'] ?? null,
                'response_code' => $event['response_code'] ?? null,
                'bounce_category' => $event['bounce_category'] ?? null,
                'timestamp' => $event['timestamp'] ?? null,
                'sending_stream' => $event['sending_stream'] ?? null,
            ], fn (mixed $value): bool => $value !== null);

            [$status, $defaultStatusCode] = self::EVENTS[$event['event']] ?? [null, null];
            $mailLog = null;

            if ($status === null) {
                PackageLog::info('Mailtrap event needs no action', $context);
                $stats['skipped']++;
            } else {
                try {
                    $mailLog = $this->apply($event, $status, $defaultStatusCode);
                } catch (\Throwable $e) {
                    PackageLog::error('Failed to process Mailtrap event', $context + ['error' => $e->getMessage()]);
                    $stats['skipped']++;

                    continue;
                }

                PackageLog::info("Email marked as {$status} by Mailtrap webhook", $context);
                $stats[$status === EmailValidation::VALID ? 'valid_emails' : 'invalid_emails']++;
            }

            // A failing listener in the host application must not cost the rest
            // of the batch, nor make Mailtrap retry events that were processed.
            try {
                MailtrapEventReceived::dispatch($event['event'], $event['email'], $event, $mailLog);
            } catch (\Throwable $e) {
                PackageLog::error('A MailtrapEventReceived listener failed', $context + ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook processed',
            'stats' => $stats + [
                'total_processed' => count($handled),
                'total_events' => count($events),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000),
            ],
        ]);
    }

    /**
     * Record the event on the address and on the mail log.
     *
     * @param  array<string, mixed>  $event
     * @return MailLog|null The updated or created log, or null when the event has no message id.
     */
    private function apply(array $event, string $status, int $defaultStatusCode): ?MailLog
    {
        $email = $event['email'];
        $statusCode = (string) ($event['response_code'] ?? $defaultStatusCode);
        $reason = $status === EmailValidation::VALID
            ? null
            : (string) ($event['response'] ?? $event['reason'] ?? "Mailtrap reported a {$event['event']} event");

        if ($status === EmailValidation::VALID) {
            EmailValidation::markAsValid($email);
        } else {
            EmailValidation::markAsInvalid($email, $reason, $statusCode);
        }

        // Prefer the id the MessageSending listener passed along as a custom
        // variable: Mailtrap may report its own message id instead.
        $customId = $event['custom_variables'][MailLog::CUSTOM_VARIABLE] ?? null;
        $messageId = is_string($customId) && $customId !== '' ? $customId : ($event['message_id'] ?? null);

        if (! is_string($messageId) || $messageId === '') {
            return null;
        }

        // Recipients of one message share its id, so match the recipient as well.
        $mailLog = MailLog::where('message_id', $messageId)
            ->toRecipient($email)
            ->first();

        if ($mailLog !== null) {
            $mailLog->update(array_filter(
                ['status_code' => $statusCode, 'error_message' => $reason],
                fn (?string $value): bool => $value !== null,
            ));

            return $mailLog;
        }

        // The local MessageSending listener may not have run, e.g. for mail sent
        // through the Mailtrap API directly.
        return MailLog::create([
            'message_id' => $messageId,
            'sender' => $event['sending_domain_name'] ?? null,
            'recipient' => $email,
            'subject' => $event['category'] ?? 'Mailtrap webhook '.$event['event'],
            'status_code' => $statusCode,
            'error_message' => $reason,
            'type' => 'webhook',
        ]);
    }
}
