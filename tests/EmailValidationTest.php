<?php

use Darvis\Mailtrap\Models\EmailValidation;

it('does not block a whole domain because one address has a bad format', function (): void {
    EmailValidation::validateEmail('not valid@example.org');

    expect(EmailValidation::isBlocked('not valid@example.org'))->toBeTrue()
        ->and(EmailValidation::isBlocked('someone@example.org'))->toBeFalse();
});

it('does not block a whole domain because one address was blocked', function (): void {
    EmailValidation::markAsBlocked('dead@example.org', 'Manually blocked');

    expect(EmailValidation::isBlocked('dead@example.org'))->toBeTrue()
        ->and(EmailValidation::isBlocked('alive@example.org'))->toBeFalse()
        ->and(EmailValidation::getBlockReason('alive@example.org'))->toBeNull();
});

it('skips the DNS lookup for an address on a domain that is already known to be valid', function (): void {
    EmailValidation::markAsValid('known@example.org');

    expect(EmailValidation::validateEmail('new@example.org'))->toBeNull()
        ->and(EmailValidation::where('email', 'new@example.org')->value('status'))->toBe(EmailValidation::VALID);
});

it('still rejects a malformed address on a domain that is known to be valid', function (): void {
    EmailValidation::markAsValid('known@example.org');

    expect(EmailValidation::validateEmail('not valid@example.org'))->toBe('Invalid email format');
});

it('counts addresses that fail validation as invalid in a bulk check', function (): void {
    $result = EmailValidation::bulkValidationWithCheck(['not valid@example.org'], validateMissing: true);

    expect($result['valid'])->toBe(0)
        ->and($result['invalid'])->toBe(1)
        ->and($result['not_exists'])->toBe(0)
        ->and($result['details']['not valid@example.org']['status'])->toBe(EmailValidation::BLOCKED);
});
