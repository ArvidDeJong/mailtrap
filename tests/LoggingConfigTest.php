<?php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);

    EmailValidation::saveValidation('recipient@example.org', 'valid', 'ok', 200);
});

it('writes no mail logs when logging is disabled', function (): void {
    config()->set('manta_mailtrap.logging.enabled', false);

    Mail::raw('Body', function ($message): void {
        $message->to('recipient@example.org')->subject('Unlogged');
    });

    expect(MailLog::count())->toBe(0);
});

it('writes no log for a successful send when log_successful is off', function (): void {
    config()->set('manta_mailtrap.logging.log_successful', false);

    Mail::raw('Body', function ($message): void {
        $message->to('recipient@example.org')->subject('Unlogged');
    });

    expect(MailLog::count())->toBe(0);
});

it('still blocks but writes no log when log_failed is off', function (): void {
    config()->set('manta_mailtrap.logging.log_failed', false);

    EmailValidation::saveValidation('blocked@example.net', 'blocked', 'No valid mail server found for domain', 400);

    expect(fn () => Mail::raw('Body', function ($message): void {
        $message->to('blocked@example.net')->subject('Blocked');
    }))->toThrow(TransportException::class);

    expect(MailLog::count())->toBe(0);
});
