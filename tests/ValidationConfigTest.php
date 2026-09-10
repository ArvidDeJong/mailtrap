<?php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);
});

it('performs no address validation when validation is disabled', function (): void {
    config()->set('manta_mailtrap.validation.enabled', false);

    // Would resolve to nothing and be recorded as blocked if validation ran.
    Mail::raw('Body', function ($message): void {
        $message->to('nobody@does-not-exist.invalid')->subject('No lookup');
    });

    expect(EmailValidation::count())->toBe(0)
        ->and(MailLog::where('recipient', 'nobody@does-not-exist.invalid')->value('status_code'))->toBe('200');
});

it('aborts the send for a blocked address by default', function (): void {
    EmailValidation::saveValidation('blocked@example.org', 'blocked', 'No valid mail server found for domain', 400);

    expect(fn () => Mail::raw('Body', function ($message): void {
        $message->to('blocked@example.org')->subject('Blocked');
    }))->toThrow(TransportException::class);

    expect(MailLog::where('recipient', 'blocked@example.org')->value('status_code'))->toBe('550');
});

it('delivers to a flagged address when hard blocking is switched off', function (): void {
    config()->set('manta_mailtrap.validation.block_invalid', false);

    EmailValidation::saveValidation('blocked@example.org', 'blocked', 'No valid mail server found for domain', 400);

    Mail::raw('Body', function ($message): void {
        $message->to('blocked@example.org')->subject('Delivered anyway');
    });

    $log = MailLog::where('recipient', 'blocked@example.org')->sole();

    // Delivered, so logged as sent — with the reason kept for visibility.
    expect($log->status_code)->toBe('200')
        ->and($log->error_message)->toBe('No valid mail server found for domain');
});

it('re-checks a blocked address once the cache duration has passed', function (): void {
    config()->set('manta_mailtrap.validation.cache_duration', 60);

    EmailValidation::saveValidation('not valid@example.org', 'blocked', 'Stale reason', 400);
    EmailValidation::where('email', 'not valid@example.org')
        ->update(['last_checked_at' => now()->subMinutes(5)]);

    EmailValidation::validateEmail('not valid@example.org');

    $record = EmailValidation::where('email', 'not valid@example.org')->sole();

    expect($record->reason)->toBe('Invalid email format')
        ->and($record->last_checked_at->isAfter(now()->subMinute()))->toBeTrue();
});

it('keeps a blocked address cached while the cache duration has not passed', function (): void {
    config()->set('manta_mailtrap.validation.cache_duration', 3600);

    EmailValidation::saveValidation('not valid@example.org', 'blocked', 'Original reason', 400);

    expect(EmailValidation::validateEmail('not valid@example.org'))->toBe('Original reason');
});

it('never expires a blocked address when the cache duration is zero', function (): void {
    config()->set('manta_mailtrap.validation.cache_duration', 0);

    EmailValidation::saveValidation('not valid@example.org', 'blocked', 'Original reason', 400);
    EmailValidation::where('email', 'not valid@example.org')
        ->update(['last_checked_at' => now()->subYear()]);

    expect(EmailValidation::validateEmail('not valid@example.org'))->toBe('Original reason');
});

it('does not re-derive an invalid address reported by a webhook', function (): void {
    config()->set('manta_mailtrap.validation.cache_duration', 60);

    // "invalid" comes from a bounce; an MX lookup cannot reproduce that verdict.
    EmailValidation::saveValidation('bounced@example.org', 'invalid', 'Hard bounce', 550);
    EmailValidation::where('email', 'bounced@example.org')
        ->update(['last_checked_at' => now()->subYear()]);

    expect(EmailValidation::validateEmail('bounced@example.org'))->toBe('Hard bounce');
});
