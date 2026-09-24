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
- **Inbox page**: a Livewire/Flux page to search, inspect and clean up the mail log, closed by a `viewMailtrap` gate
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

The wizard runs the migrations and walks you through the Mailtrap tokens, the mailer, the webhook, validation and the inbox page. It writes every answer to `.env`, so run it on every server. See [Installation](https://arviddejong.github.io/mailtrap/installation.html) for the manual steps and [Environment variables](https://arviddejong.github.io/mailtrap/environment.html) for every `.env` variable and what it is for.

### The `.env` variables you will use most

The wizard writes these for you. To set them by hand:

```env
# Webhook: Mailtrap reports delivered, opened, bounced and spam per mail
MAILTRAP_WEBHOOK_ENABLED=true
MAILTRAP_WEBHOOK_SECRET=<signing secret of the webhook>
MAILTRAP_API_TOKEN=<account API token with Admin access>
```

- `MAILTRAP_WEBHOOK_SECRET` is not a token you create: Mailtrap generates it for the webhook. `php artisan mailtrap:webhook` (or the wizard) creates the webhook and writes the secret for you. Made the webhook yourself in the Mailtrap dashboard? Open it under Settings → Webhooks and copy the signing secret from its detail panel. Without a secret the endpoint answers `403` to every call.
- `MAILTRAP_API_TOKEN` is an **account** token: Mailtrap → Settings → API Tokens → Add Token, with Admin access. It is only used to create the webhook. It is **not** the SMTP password: that is the token of your sending domain (Sending Domains → your domain → Integration → SMTP) and goes in `MAIL_PASSWORD`.

For more insight into what your site sends, open the inbox page at `/mailtrap`. It lists every outgoing mail with its status, bounces and errors:

```env
MAILTRAP_UI_ENABLED=true
MAILTRAP_UI_MIDDLEWARE=web,auth
MAILTRAP_UI_LAYOUT=layouts.app
```

- `MAILTRAP_UI_ENABLED` registers the page (on by default).
- `MAILTRAP_UI_MIDDLEWARE` runs before the `viewMailtrap` gate below; with `auth` a guest is sent to your login page.
- `MAILTRAP_UI_LAYOUT` is the Blade layout the page renders in, in dot notation: `layouts.app` is `resources/views/layouts/app.blade.php`. The default is `components.layouts.app`.

### Who can open the inbox page

Outside the `local` environment the inbox at `/mailtrap` answers `403` until your application defines the `viewMailtrap` gate, the way Laravel Horizon does it. Add this to `AppServiceProvider::boot()` and adapt the condition to your users:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewMailtrap', fn (?User $user) => $user?->is_admin === true);
```

`MAILTRAP_UI_ENABLED=false` switches the page off.

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

- [Installation](https://arviddejong.github.io/mailtrap/installation.html): the steps and how to check that it works
- [Environment variables](https://arviddejong.github.io/mailtrap/environment.html): every `.env` variable, the two Mailtrap tokens and the webhook secret
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
