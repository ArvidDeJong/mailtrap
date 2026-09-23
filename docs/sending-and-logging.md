---
title: "Sending, blocking and mail logs"
description: "What darvis/mailtrap does on every send in Laravel: the recipient check, which verdict stops a mail, the mail_logs columns and scopes, pruning, events."
nav_order: 5
---

# Sending, blocking and mail logs

The package listens to Laravel's `MessageSending` and `MessageSent` events. Every outgoing mail is validated and logged, whatever mailer it goes through. You do not call the package to send mail.

## What happens when a mail is sent

Before the mail goes out (`MessageSending`), the package first decides on every To, Cc and Bcc address, and only then writes rows:

1. Each address is validated with `EmailValidation::validateEmail()`: the format, then a DNS lookup for a mail server (MX record) that resolves to an IP address. A verdict that is already in the database is reused. The lookups run synchronously during the send. Set `MAILTRAP_VALIDATION_ENABLED=false` to skip this step.
2. If one of the addresses has the verdict `blocked`, the whole send is aborted. Every recipient of the mail gets a log row with status `550`, because nothing was sent to anyone. The blocked address carries its reason in `error_message`; the others carry `Not sent: {email} is blocked ({reason})`. Then the `MailBlocked` event is dispatched for the first blocked address, and a `Symfony\Component\Mailer\Exception\TransportException` is thrown with the message `Email address {email} is blocked: {reason}`. Set `MAILTRAP_BLOCK_INVALID_EMAILS=false` to send anyway; the reason is then kept in `error_message` of a normal log row.
3. Otherwise one `mail_logs` row is created per recipient, with `status_code` `null` (pending).

Writing the log never stops a mail. If a row cannot be written, the exception goes to Laravel's exception handler and the send carries on.

After the mailer accepted the mail (`MessageSent`), every pending row of that message gets status `200`.

All rows of one mail share one message id. The package puts it in the `X-Message-ID` header (an existing `X-Message-ID` header is reused) and sends it to Mailtrap as the custom variable `x_message_id`, so [webhook events](./webhook.md) find the right row.

## Which verdict stops a send

Each address has at most one row in `email_validations`. Its `status` is the verdict.

| Verdict | Constant | Set by | Stops a send? |
| --- | --- | --- | --- |
| `valid` | `EmailValidation::VALID` | Passed the local checks, a `delivery`, `open` or `click` webhook event, or `markAsValid()` | No |
| `invalid` | `EmailValidation::INVALID` | A `bounce`, `spam` or `reject` webhook event, or `markAsInvalid()` | No |
| `blocked` | `EmailValidation::BLOCKED` | A failed local check (format or DNS), or `markAsBlocked()` | Yes |

- Blocking is per address. A blocked address never stops mail to other addresses on the same domain.
- A block from a local check expires after `MAILTRAP_VALIDATION_CACHE_DURATION` seconds (default 3600). The address is then checked again, so a DNS outage does not block it for good.
- A block set with `markAsBlocked()` never expires. Remove it with `markAsValid()`.

```php
use Darvis\Mailtrap\Models\EmailValidation;

// Stop all mail to one address.
EmailValidation::markAsBlocked('complainer@example.com', 'Spam complaint');

// Allow it again.
EmailValidation::markAsValid('complainer@example.com');
```

All methods are on [Email validation](./email-validation.md).

## The columns of a mail log

`Darvis\Mailtrap\Models\MailLog` is one row per recipient in the `mail_logs` table.

| Column | Contents |
| --- | --- |
| `message_id` | The id shared by all recipients of one mail |
| `sender` | The From address |
| `recipient` | One To, Cc or Bcc address, as the mail spelled it |
| `subject` | The subject, or an empty string for a mail without one |
| `status_code` | `null` pending, `"200"` sent (`MailLog::STATUS_SENT`), `"550"` blocked (`MailLog::STATUS_BLOCKED`), or the response code of a webhook event |
| `error_message` | The block reason, or the response text of a bounce, spam or reject event |
| `source_file`, `source_line` | Only for a blocked send: the first file outside `vendor/` that was involved in sending the mail, relative to the project root, and its line. `null` when there is none, for example for a queued mail. |
| `type` | The `X-Mail-Type` header, or `webhook` for a row created by a webhook event |
| `model`, `model_id` | The `X-Mail-Model` and `X-Mail-Model-ID` headers |

The body of the mail is not stored.

## Link a log to a model

Add three headers to the mailable. `X-Mail-Model` is a class name or a morph alias.

```php
// app/Mail/InvoiceMail.php, inside the class

use App\Models\Invoice;
use Illuminate\Mail\Mailables\Headers;

public function headers(): Headers
{
    return new Headers(text: [
        'X-Mail-Type' => 'invoice',
        'X-Mail-Model' => Invoice::class,
        'X-Mail-Model-ID' => (string) $this->invoice->id,
    ]);
}
```

A complete mailable is in the [quick start](./quickstart.md).

## Query the mail logs

Use the scopes (named query filters) instead of comparing status codes yourself.

| Scope | Rows |
| --- | --- |
| `successful()` | `status_code` is `"200"` |
| `pending()` | `status_code` is `null` |
| `failed()` | Any status other than `null` and `"200"`, so blocked rows too |
| `blocked()` | `status_code` is `"550"` |
| `forModel(string $model, ?int $modelId = null)` | Logs of one model class, or one model |
| `toRecipient(string $email)` | Logs to one address |
| `fromSender(string $email)` | Logs from one address |

```php
use App\Models\Invoice;
use Darvis\Mailtrap\Models\MailLog;

MailLog::forModel(Invoice::class, $invoice->id)->failed()->get();
MailLog::toRecipient('user@example.com')->latest('id')->first();
MailLog::pending()->count();

$log->related; // the Invoice, through the model and model_id columns
```

A bounce that Mailtrap reports with response code 550 gets the same status as a blocked send, so `blocked()` returns both. Read `error_message` to tell them apart. `toRecipient()` ignores capitals.

## Remove old logs

`MailLog` uses Laravel's `MassPrunable`. Logs older than `MAILTRAP_CLEANUP_AFTER_DAYS` (default 30) are deleted by `model:prune`. Laravel only finds models in `app/Models` by itself, so name the model:

```php
// routes/console.php

use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [MailLog::class]])->daily();
```

With `MAILTRAP_CLEANUP_AFTER_DAYS=0` nothing is deleted. The Cleanup button in the [inbox](./inbox.md) deletes the same rows.

## React to a blocked send or a webhook event

| Event | When | Properties |
| --- | --- | --- |
| `Darvis\Mailtrap\Events\MailBlocked` | Right before a send to a blocked address is aborted | `email`, `reason`, `message` (the Symfony `Email`), `mailLog` (`null` when `MAILTRAP_LOG_FAILED=false`) |
| `Darvis\Mailtrap\Events\MailtrapEventReceived` | For every event in a webhook call that was accepted (see [signature checking](./webhook.md#how-the-signature-is-checked)), after the package processed it | `type`, `email`, `payload` (the event as Mailtrap sent it), `mailLog` (`null` for types the package does not act on) |

`MailtrapEventReceived` is also dispatched for types the package does nothing with, such as `unsubscribe`, `soft bounce` and `suspension`. It is not dispatched for an event without an `email` or `event` field, for a duplicate inside one call, or for an event the package failed to store.

```php
// app/Providers/AppServiceProvider.php, inside boot()

use App\Models\User;
use Darvis\Mailtrap\Events\MailtrapEventReceived;
use Illuminate\Support\Facades\Event;

Event::listen(function (MailtrapEventReceived $event): void {
    if ($event->type === 'unsubscribe') {
        User::where('email', $event->email)->update(['newsletter' => false]);
    }
});
```

The `newsletter` column is an example from your own app. A listener that throws does not stop the rest of the webhook call; the error is written to the Laravel log when `MAILTRAP_LOG_TO_LARAVEL=true`.

## What works without Mailtrap

Validation and logging work with any Laravel mailer. Delivery feedback is the exception, because only Mailtrap sends the [webhook](./webhook.md) events.

| Capability | Any mailer | Needs Mailtrap |
| --- | :---: | :---: |
| Recipient check before sending (format, DNS, manual blocks) | Yes | |
| Mail logs | Yes | |
| Inbox page and `mailtrap:test` | Yes | |
| `delivery`, `open`, `click` events mark an address `valid` | | Yes |
| `bounce`, `spam`, `reject` events mark an address `invalid` | | Yes |

## Deprecated: MailtrapService

`Darvis\Mailtrap\Services\MailtrapService` and `app('mailtrap')` are deprecated since 1.2.0 and removed in 2.0. Nothing in the package calls them. Use `EmailValidation::validateEmail()` instead.

## Next steps

- [Email validation](./email-validation.md)
- [Webhook](./webhook.md)
- [Testing](./testing.md)
