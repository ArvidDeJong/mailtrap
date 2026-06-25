<?php

namespace Darvis\Mailtrap\Providers;

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(function (MessageSending $event) {
            $message = $event->message;
            $addresses = collect($message->getTo())->map(fn ($address) => $address->getAddress());

            // Get headers and sender early for logging
            $headers = $message->getHeaders();
            $sender = collect($message->getFrom())->first()->getAddress();

            // Use ONE message id for the whole message, shared by every recipient.
            // A message sent to multiple recipients fires a single MessageSent event,
            // so all recipient log rows must carry the same id to be marked as sent.
            // (Generating a fresh id per recipient left the header holding only the
            // last recipient's id, so earlier recipients stayed stuck on "pending".)
            $messageId = $headers->has('X-Message-ID')
                ? $headers->get('X-Message-ID')->getBodyAsString()
                : Str::uuid()->toString();

            if ($headers->has('X-Message-ID')) {
                $headers->remove('X-Message-ID');
            }
            $headers->addTextHeader('X-Message-ID', $messageId);

            $type = $headers->has('X-Mail-Type') ? $headers->get('X-Mail-Type')->getBodyAsString() : null;
            $model = $headers->has('X-Mail-Model') ? $headers->get('X-Mail-Model')->getBodyAsString() : null;
            $modelId = $headers->has('X-Mail-Model-ID') ? (int) $headers->get('X-Mail-Model-ID')->getBodyAsString() : null;

            foreach ($addresses as $email) {
                // Validate email if not validated yet
                if (! EmailValidation::isValid($email)) {
                    EmailValidation::validateEmail($email);
                }

                // Check if email is blocked after validation
                if (EmailValidation::isBlocked($email)) {
                    $blockReason = EmailValidation::getBlockReason($email) ?? 'Email address is blocked';

                    MailLog::createWithSource([
                        'message_id' => $messageId,
                        'sender' => $sender,
                        'recipient' => $email,
                        'subject' => $message->getSubject(),
                        'status_code' => 550,
                        'error_message' => $blockReason,
                        'type' => $type,
                        'model' => $model,
                        'model_id' => $modelId,
                    ]);
                    throw new TransportException("Email address {$email} is blocked: {$blockReason}");
                }

                try {
                    MailLog::create([
                        'message_id' => $messageId,
                        'sender' => $sender,
                        'recipient' => $email,
                        'subject' => $message->getSubject(),
                        'status_code' => null, // Will be updated when message is sent
                        'type' => $type,
                        'model' => $model,
                        'model_id' => $modelId,
                    ]);
                } catch (\Exception $e) {
                    // A lingering legacy unique index on message_id can reject the
                    // shared id for a second recipient. Skip the duplicate rather than
                    // regenerating the id, which would desync this row from MessageSent.
                    if (! (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'message_id'))) {
                        throw $e;
                    }
                }
            }
        });

        Event::listen(function (MessageSent $event) {
            $message = $event->message;

            if (! $message->getHeaders()->has('X-Message-ID')) {
                return;
            }

            $messageId = $message->getHeaders()->get('X-Message-ID')->getBodyAsString();

            // Mark every recipient of this message as sent in a single update.
            MailLog::where('message_id', $messageId)
                ->whereNull('status_code')
                ->update([
                    'status_code' => '200', // In Laravel 12, if the message is sent, it's successful
                ]);
        });
    }
}
