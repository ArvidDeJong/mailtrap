# Darvis Mailtrap

[![Latest Version](https://img.shields.io/packagist/v/darvis/mailtrap.svg)](https://packagist.org/packages/darvis/mailtrap)
[![Tests](https://github.com/ArvidDeJong/mailtrap/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/mailtrap/actions/workflows/tests.yml)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)

Mailtrap integration for Laravel: address validation before sending, logging of every
outgoing mail, delivery feedback through the Mailtrap webhook, and an inbox to inspect it
all.

## Features

- **Validation before sending**: format and MX checks on every To, Cc and Bcc address, with blocking per address
- **Mail logging**: every outgoing mail in `mail_logs`, optionally linked to a model
- **Webhook**: delivery, bounce, spam and reject events from Mailtrap update logs and addresses, with signature verification
- **Inbox UI**: a Livewire/Flux page to search, inspect and clean up logged mail
- **Health check**: `mailtrap:test` sends a test mail, with CI-friendly exit codes
- **Setup wizard**: `mailtrap:install` configures tokens, SMTP, webhook and inbox
- **Any mailer**: validation and logging also work with SES, Microsoft Graph and others

## Installation

```bash
composer require darvis/mailtrap
php artisan mailtrap:install
```

The wizard walks you through the database, Mailtrap tokens, SMTP settings, webhook,
validation and inbox, and writes every answer to `.env`. Run it on every server.

Sending through Mailtrap takes **two different tokens** plus a webhook secret:

| `.env` variable | What it is | Used for |
| --- | --- | --- |
| `MAIL_PASSWORD` | Token of your sending domain | Sending mail over SMTP |
| `MAILTRAP_API_TOKEN` | Account API token with Admin access | Creating the webhook |
| `MAILTRAP_WEBHOOK_SECRET` | Signing secret of the webhook | Verifying webhook calls |

See [Installation & Configuration](docs/installation.md) for where to find them,
non-interactive installs and all configuration options.

## Usage

Once installed, outgoing mail is validated and logged automatically. A blocked address
aborts the send with a `TransportException`.

```php
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;

// null when valid, otherwise the reason
$error = EmailValidation::validateEmail('user@example.com');

// Stop all mail to one address (never to the whole domain)
EmailValidation::markAsBlocked('complainer@example.com', 'Spam complaint');

// Query the logs
MailLog::forModel(Invoice::class, $invoice->id)->failed()->get();
```

```bash
php artisan mailtrap:test you@example.com
```

## Documentation

| Topic | |
| --- | --- |
| [Installation & Configuration](docs/installation.md) | Tokens, setup wizard, config file, environment variables |
| [Sending, Blocking & Mail Logs](docs/sending-and-logging.md) | Validation states, linking logs to models, pruning, events, other mailers |
| [Webhook](docs/webhook.md) | Delivery events, signature verification, `mailtrap:webhook` |
| [Inbox UI & Health Check](docs/inbox.md) | Inbox setup, Tailwind, `mailtrap:test` |
| [Email Validation](docs/email-validation.md) | Single and bulk validation, collections, API reference, examples |

Or start at the [documentation index](docs/README.md).

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline
and a `mailtrap-development` skill. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Development

```bash
composer test      # Pest
composer lint      # Pint, check only (composer format to fix)
composer analyse   # Larastan
```

GitHub Actions runs the tests on PHP 8.2–8.4 against Laravel 11, 12 and 13, with both the
lowest and the latest allowed dependencies. See the [changelog](CHANGELOG.md) for release
notes.

## Author

**Arvid de Jong** · [info@arvid.nl](mailto:info@arvid.nl)

## License

MIT, see [LICENSE](LICENSE).
