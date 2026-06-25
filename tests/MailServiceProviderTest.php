<?php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);
});

it('marks every recipient as sent when one message has multiple recipients', function (): void {
    // Pre-seed validation so the listener does not perform live DNS lookups.
    EmailValidation::saveValidation('first@example.org', 'valid', 'ok', 200);
    EmailValidation::saveValidation('second@example.org', 'valid', 'ok', 200);

    Mail::raw('Body', function ($message): void {
        $message->to(['first@example.org', 'second@example.org'])
            ->subject('Multi recipient');
    });

    expect(MailLog::count())->toBe(2)
        ->and(MailLog::where('recipient', 'first@example.org')->value('status_code'))->toBe('200')
        ->and(MailLog::where('recipient', 'second@example.org')->value('status_code'))->toBe('200')
        // Both recipient rows correlate to the same message via one shared id.
        ->and(MailLog::query()->distinct()->pluck('message_id'))->toHaveCount(1);
});

it('marks a single recipient as sent', function (): void {
    EmailValidation::saveValidation('solo@example.org', 'valid', 'ok', 200);

    Mail::raw('Body', function ($message): void {
        $message->to('solo@example.org')->subject('Single recipient');
    });

    expect(MailLog::count())->toBe(1)
        ->and(MailLog::where('recipient', 'solo@example.org')->value('status_code'))->toBe('200');
});
