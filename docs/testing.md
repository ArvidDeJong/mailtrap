---
title: "Testing"
description: "Test Laravel code that sends mail with darvis/mailtrap: the array mailer, a verdict up front so no DNS lookup runs, a blocked recipient, a signed webhook."
nav_order: 9
---

# Testing

The package hooks into every send, also in your tests. Two things matter:

- **`Mail::fake()` bypasses the package.** A faked mailer sends nothing, so Laravel does not fire its mail events: no validation, no log. That is fine for a test that only asserts "this mailable was sent".
- **Without a fake, the recipient is validated with a real DNS lookup.** To test the logging or the blocking, use the `array` mailer and give each recipient a verdict up front. An address that already has a verdict is never looked up.

The `array` mailer is a Laravel mail driver that keeps mails in memory and sends nothing. A new Laravel app already sets `MAIL_MAILER=array` in `phpunit.xml`.

The examples use [Pest](https://pestphp.com) and the `WelcomeMail` and route from the [quick start](./quickstart.md). The package migrations load by themselves, so `RefreshDatabase` creates `mail_logs` and `email_validations`.

## Test that a mail is logged

```php
<?php
// tests/Feature/WelcomeMailTest.php

use App\Mail\WelcomeMail;
use App\Models\User;
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('mail.default', 'array');
});

it('logs the welcome mail and links it to the user', function (): void {
    $user = User::factory()->create(['email' => 'customer@example.org']);

    // A verdict up front: no DNS lookup runs for this address.
    EmailValidation::markAsValid('customer@example.org');

    Mail::to($user->email)->send(new WelcomeMail($user));

    $log = MailLog::forModel(User::class, $user->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->recipient)->toBe('customer@example.org')
        ->and($log->type)->toBe('welcome')
        ->and($log->status_code)->toBe(MailLog::STATUS_SENT);
});
```

`markAsValid()` writes the verdict `valid` for the address. The send then finds that verdict, skips the lookup, writes the log and marks it `200` when the `array` mailer accepts the mail.

## Test a blocked recipient

Add this to the same file:

```php
it('does not send to a blocked address', function (): void {
    $user = User::factory()->create(['email' => 'complainer@example.org']);

    EmailValidation::markAsBlocked('complainer@example.org', 'Spam complaint');

    expect(fn () => Mail::to($user->email)->send(new WelcomeMail($user)))
        ->toThrow(TransportException::class, 'Email address complainer@example.org is blocked: Spam complaint');

    expect(MailLog::blocked()->count())->toBe(1);
});
```

The send is aborted with a `TransportException` and one log row with status `550` is written.

To assert that your own listener runs, fake only the package event: `Event::fake([\Darvis\Mailtrap\Events\MailBlocked::class])`, then `Event::assertDispatched(...)`. Do not call `Event::fake()` without arguments in these tests. That also fakes Laravel's mail events, and then the package logs nothing.

## Switch validation off for a whole test suite

If your tests send to many addresses and you do not test the blocking, skip the check instead of seeding verdicts. In `phpunit.xml`:

```xml
<env name="MAILTRAP_VALIDATION_ENABLED" value="false"/>
```

Mail is still logged, and an address you block with `markAsBlocked()` is still stopped. Only the format and DNS check is skipped.

## Test the webhook with a signed request

The endpoint rejects a request without a valid signature with `403`. Sign the raw JSON body with HMAC-SHA256 and the configured secret, and send the hash in the `Mailtrap-Signature` header. Use `$this->call()` with the body as `content`, so the bytes you signed are the bytes that are sent.

```php
<?php
// tests/Feature/MailtrapWebhookTest.php

use Darvis\Mailtrap\Events\MailtrapEventReceived;
use Darvis\Mailtrap\Models\EmailValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('marks a bounced address as invalid', function (): void {
    Event::fake([MailtrapEventReceived::class]);

    $secret = '0123456789abcdef0123456789abcdef';
    config()->set('manta_mailtrap.webhook.secret', $secret);

    $body = json_encode(['events' => [[
        'event' => 'bounce',
        'email' => 'customer@example.org',
        'message_id' => 'abc-123',
        'response' => '5.5.1 User Unknown',
        'response_code' => 550,
    ]]]);

    $this->call('POST', '/api/webhooks/mailtrap', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_MAILTRAP_SIGNATURE' => hash_hmac('sha256', $body, $secret),
    ], content: $body)
        ->assertOk()
        ->assertJsonPath('stats.invalid_emails', 1);

    expect(EmailValidation::where('email', 'customer@example.org')->value('status'))
        ->toBe(EmailValidation::INVALID);

    Event::assertDispatched(
        MailtrapEventReceived::class,
        fn (MailtrapEventReceived $event): bool => $event->type === 'bounce' && $event->email === 'customer@example.org',
    );
});

it('rejects an unsigned request', function (): void {
    config()->set('manta_mailtrap.webhook.secret', '0123456789abcdef0123456789abcdef');

    $this->postJson('/api/webhooks/mailtrap', ['events' => []])
        ->assertForbidden()
        ->assertJson(['status' => 'error', 'message' => 'Missing webhook signature']);
});
```

The first test posts one signed `bounce` event. The endpoint answers `200`, stores the verdict `invalid` for the address, and dispatches `MailtrapEventReceived`. The second test shows the `403` for a request without the header.

If your app sets `MAILTRAP_WEBHOOK_ENABLED=false`, the route does not exist and these tests get `404`. The route is registered while the application boots, so set that variable in `phpunit.xml`, not with `config()->set()` inside a test.

## Nothing external is called

With the `array` mailer and verdicts up front, a test sends no mail and runs no DNS lookup. The package never calls the Mailtrap API while sending or while handling a webhook. Only `mailtrap:install` and `mailtrap:webhook` call it; when you test code around those, fake the HTTP client with `Http::fake()`.
