<?php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);
});

it('blocks an address however it is capitalised', function (): void {
    EmailValidation::markAsBlocked('dead@example.org', 'Manually blocked');

    expect(EmailValidation::isBlocked(' Dead@Example.ORG '))->toBeTrue()
        ->and(EmailValidation::getBlockReason('DEAD@EXAMPLE.ORG'))->toBe('Manually blocked')
        ->and(EmailValidation::validateEmail('Dead@Example.org'))->toBe('Manually blocked');

    expect(fn () => Mail::raw('Body', fn ($message) => $message->to('Dead@Example.org')->subject('Case')))
        ->toThrow(TransportException::class);
});

it('stores one lower case row per address', function (): void {
    EmailValidation::markAsBlocked('Dead@Example.org', 'Manually blocked');
    EmailValidation::markAsValid('dead@example.ORG');

    expect(EmailValidation::count())->toBe(1)
        ->and(EmailValidation::value('email'))->toBe('dead@example.org')
        ->and(EmailValidation::value('status'))->toBe(EmailValidation::VALID)
        ->and(EmailValidation::isValid('DEAD@example.org'))->toBeTrue();
});

it('still finds a row that an older version stored with capitals', function (): void {
    DB::table('email_validations')->insert([
        'email' => 'Legacy@Example.org',
        'domain' => 'example.org',
        'status' => 'blocked',
        'reason' => 'Manually blocked',
        'last_checked_at' => now(),
    ]);

    expect(EmailValidation::isBlocked('legacy@example.org'))->toBeTrue();

    // Lifting the block must reach the old row, not add a second one next to it.
    EmailValidation::markAsValid('legacy@example.org');

    expect(EmailValidation::count())->toBe(1)
        ->and(EmailValidation::isBlocked('Legacy@Example.org'))->toBeFalse();
});

it('reports the bulk status under the address as it was given', function (): void {
    EmailValidation::markAsValid('known@example.org');

    $result = EmailValidation::bulkValidationStatus(['Known@Example.org', 'unknown@example.org']);

    expect($result['valid'])->toBe(1)
        ->and($result['not_exists'])->toBe(1)
        ->and($result['details']['Known@Example.org']['status'])->toBe('valid');
});

it('finds mail logs to a recipient however it is capitalised', function (): void {
    MailLog::create(['recipient' => 'Customer@Example.org', 'subject' => 'Case', 'sender' => 'sender@example.org']);

    expect(MailLog::toRecipient('customer@example.org')->count())->toBe(1);
});

it('logs a recipient once when the same address appears in two spellings', function (): void {
    EmailValidation::markAsValid('twice@example.org');

    Mail::raw('Body', fn ($message) => $message->to('twice@example.org')->cc('Twice@Example.org')->subject('Twice'));

    expect(MailLog::count())->toBe(1);
});
