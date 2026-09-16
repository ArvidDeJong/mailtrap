<?php

namespace Darvis\Mailtrap\Providers;

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;

class MailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(function (MessageSending $event): void {
            $message = $event->message;
            $headers = $message->getHeaders();
            $header = fn (string $name): ?string => $headers->get($name)?->getBodyAsString();

            // Use ONE message id for the whole message, shared by every recipient.
            // A message sent to multiple recipients fires a single MessageSent event,
            // so all recipient log rows must carry the same id to be marked as sent.
            $messageId = $header('X-Message-ID') ?? Str::uuid()->toString();
            $headers->remove('X-Message-ID');
            $headers->addTextHeader('X-Message-ID', $messageId);

            $modelId = $header('X-Mail-Model-ID');

            $logRow = [
                'message_id' => $messageId,
                'sender' => ($message->getFrom()[0] ?? $message->getSender())?->getAddress(),
                'subject' => $message->getSubject(),
                'type' => $header('X-Mail-Type'),
                'model' => $header('X-Mail-Model'),
                'model_id' => $modelId === null ? null : (int) $modelId,
            ];

            $validationEnabled = (bool) config('manta_mailtrap.validation.enabled', true);
            $blockInvalid = (bool) config('manta_mailtrap.validation.block_invalid', true);
            $loggingEnabled = (bool) config('manta_mailtrap.logging.enabled', true);
            $logSuccessful = $loggingEnabled && config('manta_mailtrap.logging.log_successful', true);
            $logFailed = $loggingEnabled && config('manta_mailtrap.logging.log_failed', true);

            // Cc and Bcc count too: a blocked address must not slip through as a hidden copy.
            $recipients = collect([...$message->getTo(), ...$message->getCc(), ...$message->getBcc()])
                ->map(fn (Address $address): string => $address->getAddress())
                ->unique();

            foreach ($recipients as $email) {
                // Disabling validation skips the MX lookups, which run synchronously during the send.
                if ($validationEnabled) {
                    EmailValidation::validateEmail($email);
                }

                $blockReason = EmailValidation::getBlockReason($email);

                if ($blockReason !== null && $blockInvalid) {
                    if ($logFailed) {
                        MailLog::createWithSource($logRow + [
                            'recipient' => $email,
                            'status_code' => MailLog::STATUS_BLOCKED,
                            'error_message' => $blockReason,
                        ]);
                    }

                    throw new TransportException("Email address {$email} is blocked: {$blockReason}");
                }

                if (! $logSuccessful) {
                    continue;
                }

                try {
                    // With hard blocking switched off a flagged address is still
                    // delivered, so log it as a normal send and keep the reason
                    // on the row rather than filing it as a failure.
                    MailLog::create($logRow + [
                        'recipient' => $email,
                        'status_code' => null, // Set to 200 by the MessageSent listener.
                        'error_message' => $blockReason,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // A lingering legacy unique index on message_id can reject the
                    // shared id for a second recipient. Skip the duplicate rather than
                    // regenerating the id, which would desync this row from MessageSent.
                }
            }
        });

        Event::listen(function (MessageSent $event): void {
            if (! config('manta_mailtrap.logging.enabled', true)) {
                return;
            }

            $messageId = $event->message->getHeaders()->get('X-Message-ID')?->getBodyAsString();

            if ($messageId === null) {
                return;
            }

            // Mark every recipient of this message as sent in a single update.
            MailLog::where('message_id', $messageId)
                ->pending()
                ->update(['status_code' => MailLog::STATUS_SENT]);
        });
    }
}
