# Sending, Blocking & Mail Logs

The package listens to Laravel's `MessageSending` and `MessageSent` events. Every
outgoing mail is validated and logged, whatever mailer it goes through.

## What happens on send

For every To, Cc and Bcc recipient:

1. The address is validated: format, then an MX record that resolves to an IP. A
   previous verdict from the database is reused while it is fresh. Set
   `MAILTRAP_VALIDATION_ENABLED=false` to skip this; the DNS lookups run synchronously
   during the send.
2. A `blocked` address aborts the whole send with a
   `Symfony\Component\Mailer\Exception\TransportException`, after a `MailBlocked` event.
   Set `MAILTRAP_BLOCK_INVALID_EMAILS=false` to send anyway and only log the reason.
3. Otherwise a `MailLog` row is created. It is marked sent once `MessageSent` fires.

## Validation states

| Status | Set by | Blocks sending? |
| --- | --- | --- |
| `valid` | Passed the checks, or a `delivery`/`open`/`click` webhook event | No |
| `invalid` | A `bounce`, `spam` or `reject` webhook event | No |
| `blocked` | A failed local check, or `markAsBlocked()` | Yes |

Blocking is per address: a typo or a manual block never stops mail to the rest of the
domain. Blocks from a local check expire after `MAILTRAP_VALIDATION_CACHE_DURATION`, so a
temporary DNS outage does not block an address for good. Manual blocks are permanent.

```php
use Darvis\Mailtrap\Models\EmailValidation;

$error = EmailValidation::validateEmail('test@example.com'); // null when valid

// Stop all mail to one address.
EmailValidation::markAsBlocked('complainer@example.com', 'Spam complaint');
```

More in the [EmailValidation documentation](./email-validation.md).

> **Deprecated:** `MailtrapService` and `app('mailtrap')` call a Mailtrap validation
> endpoint that does not exist, so `validateEmail()` there cannot succeed. They are
> removed in 2.0. Use `EmailValidation::validateEmail()` instead.

## Linking a log to a model

Tag a Mailable with headers:

```php
public function headers(): \Illuminate\Mail\Mailables\Headers
{
    return new \Illuminate\Mail\Mailables\Headers(text: [
        'X-Mail-Type' => 'invoice',
        'X-Mail-Model' => Invoice::class,
        'X-Mail-Model-ID' => (string) $this->invoice->id,
    ]);
}
```

Then query the logs:

```php
use Darvis\Mailtrap\Models\MailLog;

MailLog::forModel(Invoice::class, $invoice->id)->failed()->get();
MailLog::pending()->count();   // also: successful(), blocked()
$log->related;                 // the Invoice
```

## Cleaning up old logs

Logs older than `MAILTRAP_CLEANUP_AFTER_DAYS` are removed by Laravel's pruning. Package
models are not discovered automatically, so schedule it explicitly:

```php
// routes/console.php
Schedule::command('model:prune', ['--model' => [\Darvis\Mailtrap\Models\MailLog::class]])->daily();
```

## Events

| Event | When | Properties |
| --- | --- | --- |
| `Darvis\Mailtrap\Events\MailBlocked` | Just before a send to a blocked address is aborted | `email`, `reason`, `message`, `mailLog` |
| `Darvis\Mailtrap\Events\MailtrapEventReceived` | For every signed webhook event, also the ones the package ignores | `type`, `email`, `payload`, `mailLog` |

```php
use Darvis\Mailtrap\Events\MailtrapEventReceived;
use Illuminate\Support\Facades\Event;

Event::listen(function (MailtrapEventReceived $event): void {
    if ($event->type === 'unsubscribe') {
        User::where('email', $event->email)->update(['newsletter' => false]);
    }
});
```

A listener that throws is logged (with `MAILTRAP_LOG_TO_LARAVEL=true`) and does not stop
the rest of the webhook batch.

## Other mailers

Validation and logging work with any transport: Mailtrap SMTP, Microsoft Graph, Amazon
SES or any other Laravel mailer. Delivery feedback is the exception, because only
Mailtrap reports it through the [webhook](./webhook.md).

| Capability | Any mailer | Mailtrap only |
| --- | :---: | :---: |
| Pre-send validation (format / MX / blocklist) | ✅ | |
| Outgoing mail logging | ✅ | |
| Inbox UI & `mailtrap:test` health check | ✅ | |
| Delivery / open / click → `markAsValid` | | ✅ |
| Bounce / spam / reject → `markAsInvalid` | | ✅ |

So you can route production mail through Microsoft Graph and still get full logging and
the inbox. Only the feedback loop (confirming good addresses, flagging bounces) needs
Mailtrap.

## Next Steps

- [Webhook](./webhook.md)
- [Inbox UI & Health Check](./inbox.md)
