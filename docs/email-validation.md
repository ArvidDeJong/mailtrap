---
title: "Email validation"
description: "Check an email address in Laravel with the EmailValidation model: format and MX lookup, blocking one address, the status of a list, and every method."
nav_order: 5
---

# Email validation

`Darvis\Mailtrap\Models\EmailValidation` keeps one verdict per address in the `email_validations` table. The package calls it for every recipient while sending. You can also call it yourself, for example in a form or an import.

## What is checked

`EmailValidation::validateEmail($email)` runs these steps and stops at the first answer:

1. **A stored verdict.** If the address already has a row, that verdict is returned without any lookup. The exception is a block from a local check that is older than `MAILTRAP_VALIDATION_CACHE_DURATION` seconds: that one is checked again.
2. **The format**, with PHP's `FILTER_VALIDATE_EMAIL`.
3. **A known domain.** If another address on the same domain is already `valid`, the address is stored as `valid` without a DNS lookup.
4. **The mail server.** The domain must have an MX record (`getmxrr()`), and at least one of those hosts must resolve to an IP address (`gethostbyname()`).

It does not connect to the mail server and does not know whether the mailbox exists. There is no call to Mailtrap.

The result is stored and returned:

| Outcome | Return value | Stored verdict | Stored `status_code` |
| --- | --- | --- | --- |
| Passed | `null` | `valid`, reason `All checks passed` or `Domain already verified` | `200` |
| Bad format | `Invalid email format` | `blocked` | `400` |
| No MX record | `No valid mail server found for domain` | `blocked` | `400` |
| MX host without IP | `MX record does not resolve to a valid IP address` | `blocked` | `400` |
| A stored `invalid` or `blocked` verdict | The stored reason | unchanged | unchanged |

The DNS lookups are synchronous. A slow DNS server slows down the request that calls this method.

## Check one address

```php
use Darvis\Mailtrap\Models\EmailValidation;

$reason = EmailValidation::validateEmail('user@example.com');

if ($reason === null) {
    // valid
} else {
    // $reason says why not, e.g. "No valid mail server found for domain"
}
```

As a rule in a form request (a class that validates a form in Laravel):

```php
<?php
// app/Http/Requests/SubscribeRequest.php

namespace App\Http\Requests;

use Closure;
use Darvis\Mailtrap\Models\EmailValidation;
use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (EmailValidation::validateEmail((string) $value) !== null) {
                        $fail('We cannot send mail to this address.');
                    }
                },
            ],
        ];
    }
}
```

The closure stores a verdict for the address. A later send to it reuses that verdict.

## Look up a verdict without checking

These methods only read the table. They never do a DNS lookup.

```php
EmailValidation::isBlocked('user@example.com');      // true when the verdict is "blocked"
EmailValidation::getBlockReason('user@example.com'); // the reason, or null when not blocked
EmailValidation::isValid('user@example.com');        // true when this address, or another one on its domain, is "valid"
```

`isValid()` looks at the domain too; `isBlocked()` never does. An address without a row is neither valid nor blocked.

Addresses are trimmed and compared in lower case, on every database. `User@Example.com` and `user@example.com` are one address with one row, stored as `user@example.com`. A row that a version before 1.6.0 stored with capitals is still found, and is rewritten in lower case the next time its verdict is saved.

## Block, unblock or mark an address

```php
EmailValidation::markAsBlocked('complainer@example.com', 'Spam complaint');  // stops every send to this address
EmailValidation::markAsInvalid('old@example.com', 'Left the company');       // recorded, does not stop a send
EmailValidation::markAsValid('complainer@example.com');                      // lifts a block
```

Each call replaces the row of that address. A block set this way never expires. Which verdict stops a send is explained on [Sending, blocking and mail logs](./sending-and-logging.md#which-verdict-stops-a-send).

## Get the status of a list

`bulkValidationStatus()` reads the verdicts of a list in one query, without lookups:

```php
use Darvis\Mailtrap\Models\EmailValidation;

$result = EmailValidation::bulkValidationStatus([
    'first@example.com',
    'second@example.com',
    'third@example.com',
]);
```

```php
[
    'valid' => 1,        // verdict "valid"
    'invalid' => 1,      // verdict "invalid" or "blocked"
    'not_exists' => 1,   // no row yet
    'total' => 3,
    'details' => [
        'first@example.com' => [
            'status' => 'valid',
            'reason' => 'All checks passed',
            'last_checked_at' => Illuminate\Support\Carbon,
        ],
        'second@example.com' => [
            'status' => 'blocked',
            'reason' => 'No valid mail server found for domain',
            'last_checked_at' => Illuminate\Support\Carbon,
        ],
        'third@example.com' => [
            'status' => 'not_exists',
            'reason' => 'Email address not found in database',
            'last_checked_at' => null,
        ],
    ],
]
```

`not_exists` is not a stored verdict; it only appears in this result. The keys of `details` are the addresses as you passed them.

To also check the addresses that have no row yet, pass `true` as the second argument:

```php
$result = EmailValidation::bulkValidationWithCheck($emails, true);
```

This calls `validateEmail()` for each unknown address, one after another, with DNS lookups. For a long list, run it in a queued job. With `false` (the default) it behaves like `bulkValidationStatus()`.

Pick the addresses you can mail from the result with a collection:

```php
$sendable = collect($result['details'])
    ->filter(fn (array $details): bool => $details['status'] === EmailValidation::VALID)
    ->keys()
    ->all();
```

## All methods

All methods are static.

- `validateEmail(string $email): ?string`: `null` when valid, otherwise the reason. Stores the verdict.
- `isBlocked(string $email): bool`: whether the verdict is `blocked`.
- `getBlockReason(string $email): ?string`: the block reason, or `null`.
- `isValid(string $email): bool`: whether the address, or another address on its domain, is `valid`.
- `markAsValid(string $email): self`: stores `valid` with reason `Email validated successfully` and status code `200`.
- `markAsInvalid(string $email, string $reason, int|string|null $statusCode = null): self`: stores `invalid`.
- `markAsBlocked(string $email, string $reason, int|string|null $statusCode = null): self`: stores `blocked`.
- `saveValidation(string $email, string $status, string $reason, int|string|null $statusCode): self`: stores any verdict; the `markAs…` methods call this.
- `bulkValidationStatus(array $emails): array`: counts and details, see above.
- `bulkValidationWithCheck(array $emails, bool $validateMissing = false): array`: the same, after validating unknown addresses when asked.
- `domainOf(string $email): string`: the lowercased domain, or an empty string.

Constants: `EmailValidation::VALID` (`'valid'`), `EmailValidation::INVALID` (`'invalid'`), `EmailValidation::BLOCKED` (`'blocked'`), and `EmailValidation::LOCAL_CHECK_REASONS`, the list of reasons whose blocks expire.

## The email_validations table

| Column | Type | Contents |
| --- | --- | --- |
| `id` | big integer | Primary key |
| `email` | string, nullable | The address, in lower case |
| `domain` | string, nullable | The lowercased domain of the address |
| `status` | enum | `valid`, `invalid` or `blocked` |
| `reason` | string | Why the address has this verdict |
| `status_code` | string, nullable | `200` or `400` from a local check, or the response code of a webhook event |
| `last_checked_at` | timestamp | When the verdict was stored |
| `created_at`, `updated_at` | timestamp | |

## Next steps

- [Sending, blocking and mail logs](./sending-and-logging.md)
- [Testing](./testing.md): give addresses a verdict up front so tests run no DNS lookups
