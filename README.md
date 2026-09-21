# darvis/mailtrap

[![Latest Version](https://img.shields.io/packagist/v/darvis/mailtrap.svg)](https://packagist.org/packages/darvis/mailtrap)
[![Tests](https://github.com/ArvidDeJong/mailtrap/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/mailtrap/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/darvis/mailtrap.svg)](https://packagist.org/packages/darvis/mailtrap)
[![License](https://img.shields.io/packagist/l/darvis/mailtrap.svg)](LICENSE)

A Laravel package that checks every recipient before a mail goes out, logs every outgoing mail, processes signed [Mailtrap](https://mailtrap.io) webhook events (delivery, bounce, spam, reject) and adds an inbox page to inspect it all. Validation and logging work with any Laravel mailer; only the webhook needs Mailtrap.

**Unofficial.** This is an independent open-source package, not an official Mailtrap product. It is not made, supported or endorsed by Mailtrap.

## Features

- **Recipient check before sending**: format and MX lookup for every To, Cc and Bcc address; a blocked address aborts the send with a `TransportException`
- **Blocking per address**: `EmailValidation::markAsBlocked()` stops mail to one address, never to its whole domain
- **Mail log**: one `mail_logs` row per recipient, optionally linked to a model through `X-Mail-*` headers, with query scopes and pruning
- **Signed webhook**: `POST /api/webhooks/mailtrap` checks the `Mailtrap-Signature` header (HMAC-SHA256) and updates logs and address verdicts
- **Events**: `MailBlocked` and `MailtrapEventReceived` for your own listeners
- **Inbox page**: a Livewire/Flux page to search, inspect and clean up the mail log
- **Commands**: `mailtrap:install` (setup wizard), `mailtrap:webhook` (creates the webhook and stores its secret), `mailtrap:test` (health check with exit code)

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- Only for the inbox page: `livewire/livewire` ^3.7.4 or ^4.0 and `livewire/flux` ^2.11 (the free edition is enough)

## Installation

```bash
composer require darvis/mailtrap
php artisan mailtrap:install
```

The wizard runs the migrations and walks you through the Mailtrap tokens, the mailer, the webhook, validation and the inbox page. It writes every answer to `.env`, so run it on every server. See [Installation](https://arviddejong.github.io/mailtrap/installation.html) for the manual steps and every setting.

### Who can open the inbox page

With the package defaults, and Livewire installed, the inbox at `/mailtrap` only runs through the `web` middleware: **every visitor can open it**, read recipients and subjects, delete logs and send a test mail. The interactive wizard writes `MAILTRAP_UI_MIDDLEWARE=web,auth`. If you install without the wizard, or with `--no-interaction`, set that variable yourself, or set `MAILTRAP_UI_ENABLED=false`.

## Quick start

After installation every outgoing mail is validated and logged; you do not call the package to send mail.

```php
// routes/web.php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Mailer\Exception\TransportException;

Route::get('/mail-demo', function () {
    // Stop all mail to one address (never to the whole domain).
    EmailValidation::markAsBlocked('complainer@example.com', 'Spam complaint');

    try {
        Mail::raw('Hello', fn ($message) => $message->to('complainer@example.com')->subject('Demo'));
    } catch (TransportException $e) {
        // "Email address complainer@example.com is blocked: Spam complaint"
    }

    return ['blocked_logs' => MailLog::toRecipient('complainer@example.com')->blocked()->count()]; // 1
});
```

Check the setup at any time with a real address of your own:

```bash
php artisan mailtrap:test you@yourdomain.com
```

## Documentation

Full documentation: **https://arviddejong.github.io/mailtrap/**

- [Installation](https://arviddejong.github.io/mailtrap/installation.html): the steps, the two Mailtrap tokens, every setting, and how to check that it works
- [Quick start](https://arviddejong.github.io/mailtrap/quickstart.html): a complete mailable that is logged and linked to a model
- [Sending, blocking and mail logs](https://arviddejong.github.io/mailtrap/sending-and-logging.html): what happens on every send, the verdicts, scopes and events
- [Email validation](https://arviddejong.github.io/mailtrap/email-validation.html): every method of the `EmailValidation` model
- [Webhook](https://arviddejong.github.io/mailtrap/webhook.html): signature checking, status codes and `mailtrap:webhook`
- [Inbox page and health check](https://arviddejong.github.io/mailtrap/inbox.html): who can open the inbox, and `mailtrap:test`
- [Testing](https://arviddejong.github.io/mailtrap/testing.html): tests without DNS lookups or a mail server
- [Troubleshooting](https://arviddejong.github.io/mailtrap/troubleshooting.html): error messages with cause and fix
- [FAQ](https://arviddejong.github.io/mailtrap/faq.html)

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline and a `mailtrap-development` skill. Run `php artisan boost:install`, or `php artisan boost:update --discover` in a project that already uses Boost.

## Testing

```bash
composer test      # Pest
composer lint      # Pint, check only
composer format    # Pint, fixes
composer analyse   # Larastan
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Please report a security problem privately, see [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).
