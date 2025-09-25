<?php

namespace Darvis\Mailtrap\Providers;

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
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
            $addresses = collect($message->getTo())->map(fn($address) => $address->getAddress());

            foreach ($addresses as $email) {
                // Validate email if not validated yet
                if (!EmailValidation::isValid($email) && !EmailValidation::isBlocked($email)) {
                    EmailValidation::validateEmail($email);
                }

                // Check if email is blocked after validation
                if (EmailValidation::isBlocked($email)) {
                    throw new TransportException("Email address {$email} is blocked");
                }

                // Always generate a new unique message ID for each sending attempt
                $messageId = Str::uuid()->toString();
                if ($message->getHeaders()->has('X-Message-ID')) {
                    $message->getHeaders()->remove('X-Message-ID');
                }
                $message->getHeaders()->addTextHeader('X-Message-ID', $messageId);

                // Log the email attempt
                $sender = collect($message->getFrom())->first()->getAddress();
                $headers = $message->getHeaders();

                try {
                    MailLog::create([
                        'message_id' => $headers->get('X-Message-ID')->getBodyAsString(),
                        'sender' => $sender,
                        'recipient' => $email,
                        'subject' => $message->getSubject(),
                        'status_code' => null, // Will be updated when message is sent
                        'type' => $headers->has('X-Mail-Type') ? $headers->get('X-Mail-Type')->getBodyAsString() : null,
                        'model' => $headers->has('X-Mail-Model') ? $headers->get('X-Mail-Model')->getBodyAsString() : null,
                        'model_id' => $headers->has('X-Mail-Model-ID') ? (int) $headers->get('X-Mail-Model-ID')->getBodyAsString() : null,
                    ]);
                } catch (\Exception $e) {
                    // If duplicate message_id, generate a new one and try again
                    if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'message_id')) {
                        $messageId = Str::uuid()->toString();
                        $message->getHeaders()->remove('X-Message-ID');
                        $message->getHeaders()->addTextHeader('X-Message-ID', $messageId);

                        MailLog::create([
                            'message_id' => $messageId,
                            'sender' => $sender,
                            'recipient' => $email,
                            'subject' => $message->getSubject(),
                            'status_code' => null,
                            'type' => $headers->has('X-Mail-Type') ? $headers->get('X-Mail-Type')->getBodyAsString() : null,
                            'model' => $headers->has('X-Mail-Model') ? $headers->get('X-Mail-Model')->getBodyAsString() : null,
                            'model_id' => $headers->has('X-Mail-Model-ID') ? (int) $headers->get('X-Mail-Model-ID')->getBodyAsString() : null,
                        ]);
                    } else {
                        throw $e;
                    }
                }
            }
        });

        Event::listen(function (MessageSent $event) {
            $message = $event->message;
            $messageId = $message->getHeaders()->get('X-Message-ID')->getBodyAsString();

            // Update the mail log with success status
            MailLog::where('message_id', $messageId)
                ->update([
                    'status_code' => '200' // In Laravel 12, if the message is sent, it's successful
                ]);
        });
    }
}
