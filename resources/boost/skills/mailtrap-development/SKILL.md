---
name: mailtrap-development
description: Work with darvis/mailtrap. Use it to tag outgoing mail with a model, query mail logs, validate or block email addresses, handle blocked sends, and test the Mailtrap webhook.
---

# darvis/mailtrap development

## When to use this skill

Use this skill when code sends mail in an application that has `darvis/mailtrap` installed, when you query `mail_logs` or `email_validations`, or when you work on Mailtrap webhook handling or its tests.

## How a send is processed

1. On `MessageSending`, every To, Cc and Bcc address runs through `EmailValidation::validateEmail()`: a format check, then the MX record and whether it resolves to an IP. The DNS lookups are skipped when another address on the domain is already valid. Set `validation.enabled` to false to skip validation altogether.
2. A `blocked` address aborts the send: a `MailLog` row with status `550` is written and a `TransportException` is thrown.
3. Otherwise one `MailLog` row per recipient is created with `status_code = null`. Every row of the mail shares the `X-Message-ID` header.
4. On `MessageSent` those rows become `200`. Mailtrap webhook events later update the row for that message id and recipient.

## Tagging mail with a model

Set `X-Mail-Type`, `X-Mail-Model` (a class name or morph alias) and `X-Mail-Model-ID` as headers on the Mailable. They are copied to `type`, `model` and `model_id`, and `$log->related` resolves the model.

## Querying logs

Use the scopes instead of comparing status codes:

```php
use Darvis\Mailtrap\Models\MailLog;

MailLog::successful();   // status_code "200"
MailLog::pending();      // not yet confirmed as sent
MailLog::failed();       // any status other than 200
MailLog::blocked();      // stopped before sending (550)
MailLog::forModel(Order::class, $order->id)->toRecipient($email)->latest()->get();
```

Logs older than `logging.cleanup_after_days` are pruned by `php artisan model:prune --model="Darvis\Mailtrap\Models\MailLog"`. Schedule that command, because Laravel only discovers models in `app/Models` by itself.

## Address verdicts

| Status | Set by | Stops sending |
| --- | --- | --- |
| `EmailValidation::VALID` | local checks, delivery/open/click events, `markAsValid()` | no |
| `EmailValidation::INVALID` | bounce/spam/reject events, `markAsInvalid()` | no |
| `EmailValidation::BLOCKED` | failed format or MX check, `markAsBlocked()` | yes |

- Use `markAsBlocked($email, $reason)` only to halt mail to that one address, for example after a complaint. It never affects other addresses on the domain.
- Blocked records that come from a local check expire after `validation.cache_duration` seconds and are then checked again.
- To check many addresses without DNS lookups, use `EmailValidation::bulkValidationStatus($emails)`. To validate the addresses that have no verdict yet, use `bulkValidationWithCheck($emails, true)`.

## Testing

- Use the `array` mailer and give recipients a verdict up front with `EmailValidation::markAsValid()`. Otherwise tests run real DNS lookups.
- Webhook requests must be signed. Sign the raw JSON body with HMAC-SHA256 and send it in the `Mailtrap-Signature` header:

```php
$body = json_encode(['events' => [[
    'event' => 'bounce', 'email' => 'a@example.org', 'message_id' => 'abc', 'response_code' => 550,
]]]);

$this->call('POST', '/api/webhooks/mailtrap', server: [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_MAILTRAP_SIGNATURE' => hash_hmac('sha256', $body, config('manta_mailtrap.webhook.secret')),
], content: $body)->assertOk();
```

The event names in the payload are `delivery`, `open`, `click`, `bounce`, `spam` and `reject`. `soft bounce`, `unsubscribe` and `suspension` are accepted but not acted on.
