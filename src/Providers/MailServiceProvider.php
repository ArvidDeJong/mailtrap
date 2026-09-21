<?php

namespace Darvis\Mailtrap\Providers;

use Darvis\Mailtrap\Events\MailBlocked;
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Darvis\Mailtrap\Support\EmailAddress;
use Darvis\Mailtrap\Support\MailtrapConfig;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Throwable;

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

            self::addMailtrapCustomVariable($headers, $messageId);

            $modelId = $header('X-Mail-Model-ID');

            $logRow = [
                'message_id' => $messageId,
                'sender' => ($message->getFrom()[0] ?? $message->getSender())?->getAddress(),
                // The column is not nullable, and a mail without a subject is still a mail.
                'subject' => $message->getSubject() ?? '',
                'type' => $header('X-Mail-Type'),
                'model' => $header('X-Mail-Model'),
                'model_id' => $modelId === null ? null : (int) $modelId,
            ];

            $validationEnabled = MailtrapConfig::validationEnabled();
            $blockInvalid = MailtrapConfig::blockInvalid();
            $logSuccessful = MailtrapConfig::logSuccessful();
            $logFailed = MailtrapConfig::logFailed();

            // Cc and Bcc count too: a blocked address must not slip through as a hidden copy.
            // One mailbox in two spellings is one recipient.
            $recipients = collect([...$message->getTo(), ...$message->getCc(), ...$message->getBcc()])
                ->map(fn (Address $address): string => $address->getAddress())
                ->unique(fn (string $email): string => EmailAddress::normalise($email))
                ->values();

            // Decide on every recipient before writing a single row. A block aborts
            // the whole mail, so a row written earlier would stay pending for ever.
            $blocked = [];

            foreach ($recipients as $email) {
                // Disabling validation skips the MX lookups, which run synchronously during the send.
                if ($validationEnabled) {
                    EmailValidation::validateEmail($email);
                }

                $blockReason = EmailValidation::getBlockReason($email);

                if ($blockReason !== null) {
                    $blocked[$email] = $blockReason;
                }
            }

            if ($blocked !== [] && $blockInvalid) {
                $blockedEmail = (string) array_key_first($blocked);
                $blockReason = $blocked[$blockedEmail];
                $mailLog = null;

                foreach ($logFailed ? $recipients : [] as $email) {
                    // Nothing was sent to anyone, so every recipient gets the blocked
                    // status; the others say whose block stopped their mail.
                    $row = self::writeLog(fn (): MailLog => MailLog::createWithSource($logRow + [
                        'recipient' => $email,
                        'status_code' => MailLog::STATUS_BLOCKED,
                        'error_message' => $blocked[$email] ?? "Not sent: {$blockedEmail} is blocked ({$blockReason})",
                    ]));

                    if ($email === $blockedEmail) {
                        $mailLog = $row;
                    }
                }

                MailBlocked::dispatch($blockedEmail, $blockReason, $message, $mailLog);

                throw new TransportException("Email address {$blockedEmail} is blocked: {$blockReason}");
            }

            foreach ($logSuccessful ? $recipients : [] as $email) {
                // With hard blocking switched off a flagged address is still
                // delivered, so log it as a normal send and keep the reason
                // on the row rather than filing it as a failure.
                self::writeLog(fn (): MailLog => MailLog::create($logRow + [
                    'recipient' => $email,
                    'status_code' => null, // Set to 200 by the MessageSent listener.
                    'error_message' => $blocked[$email] ?? null,
                ]));
            }
        });

        Event::listen(function (MessageSent $event): void {
            if (! MailtrapConfig::loggingEnabled()) {
                return;
            }

            $messageId = $event->message->getHeaders()->get('X-Message-ID')?->getBodyAsString();

            if ($messageId === null) {
                return;
            }

            // Mark every recipient of this message as sent in a single update. The
            // mail has left by now; an exception here would tell the caller it failed.
            self::writeLog(fn (): int => MailLog::where('message_id', $messageId)
                ->pending()
                ->update(['status_code' => MailLog::STATUS_SENT]));
        });
    }

    /**
     * Write to the mail log without ever letting the log decide whether a mail goes out.
     *
     * A missing table, a full disk or a column that rejects a value is reported to
     * the application's exception handler; the send carries on.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $write
     * @return TResult|null
     */
    private static function writeLog(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            // A lingering legacy unique index on message_id can reject the
            // shared id for a second recipient. Skip the duplicate rather than
            // regenerating the id, which would desync this row from MessageSent.
            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Send the log's message id along as a Mailtrap custom variable.
     *
     * Mailtrap returns custom variables in its webhook events, which lets the
     * webhook find this mail's log even when Mailtrap reports its own message
     * id. Variables the application already set are kept. Mailtrap ignores the
     * whole header above 1000 bytes, so it is left alone rather than broken.
     *
     * @see https://docs.mailtrap.io/email-api-smtp/advanced/custom-variables
     */
    private static function addMailtrapCustomVariable(Headers $headers, string $messageId): void
    {
        $existing = $headers->get(MailLog::CUSTOM_VARIABLES_HEADER)?->getBodyAsString();
        $variables = $existing === null ? [] : json_decode($existing, true);

        if (! is_array($variables)) {
            return;
        }

        $json = json_encode([...$variables, MailLog::CUSTOM_VARIABLE => $messageId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false || strlen($json) > 1000) {
            return;
        }

        $headers->remove(MailLog::CUSTOM_VARIABLES_HEADER);
        $headers->addTextHeader(MailLog::CUSTOM_VARIABLES_HEADER, $json);
    }
}
