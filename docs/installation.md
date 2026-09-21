---
title: "Installation"
description: "Install darvis/mailtrap in Laravel step by step: requirements, the setup wizard, the two Mailtrap tokens, every setting and how to check that it works."
nav_order: 2
---

# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- A database. The package adds two tables: `mail_logs` and `email_validations`.
- Only for the [inbox page](./inbox.md): `livewire/livewire` ^3.7.4 or ^4.0 and `livewire/flux` ^2.11 (the free edition is enough). Without them the inbox is not registered and the rest of the package works.

You do not need a Mailtrap account for validation and logging. You need one for the [webhook](./webhook.md), because only Mailtrap sends those events.

This is an unofficial package, not made or endorsed by Mailtrap.

## Install step by step

### 1. Require the package

```bash
composer require darvis/mailtrap
```

Laravel discovers the service provider by itself. From this moment every outgoing mail is validated and logged, so the tables of step 2 must exist before the application sends mail.

### 2. Run the setup wizard

```bash
php artisan mailtrap:install
```

The wizard explains each step before it asks anything, and writes every answer to `.env` straight away:

1. **Check the basics**: `.env`, `APP_URL`, the database connection, the current mailer, whether Livewire is loaded
2. **Database tables**: runs `php artisan migrate`
3. **Mailtrap API token**: the account token that creates the webhook; the wizard checks it with Mailtrap. Leave it empty to skip.
4. **Sending mail**: keep your current mailer, or send through Mailtrap. For Mailtrap the wizard writes `MAIL_MAILER=smtp`, `MAIL_HOST=live.smtp.mailtrap.io`, `MAIL_PORT=587`, `MAIL_USERNAME=api`, `MAIL_PASSWORD` and `MAIL_FROM_ADDRESS`.
5. **Webhook for delivery events**: creates the webhook and stores its signing secret. When the site does not send through Mailtrap, the wizard switches the endpoint off. On a local site it tells you to run the wizard on the live server.
6. **Address validation**: check and block (default), check and only log, or off
7. **Inbox page**: the middleware that runs before the gate, which Blade layout the page uses, and the Tailwind `@source` line. Skipped when Livewire is not loaded. The summary prints the gate from step 3.
8. **Test mail**: sends one through `mailtrap:test`

A summary at the end lists what is still left to do. `.env` is not in git, so run the wizard on every server.

Do you prefer to do it by hand? Run `php artisan migrate`, then set the variables from [Every setting](#every-setting) yourself.

### 3. Say who may open the inbox page

Skip this step when Livewire is not installed or you set `MAILTRAP_UI_ENABLED=false`.

The inbox at `/mailtrap` shows recipients and subjects, so it is closed by default. It works in the `local` environment. Everywhere else it answers `403` until your application defines the `viewMailtrap` gate, the same way Laravel Horizon does it. No `.env` setting replaces this step.

```php
// app/Providers/AppServiceProvider.php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewMailtrap', fn (?User $user) => $user?->is_admin === true);
}
```

`is_admin` is an example; use whatever marks staff in your app. Also let the login run first, so a guest is sent to your login page. The wizard writes this for you in step 7:

```env
MAILTRAP_UI_MIDDLEWARE=web,auth
```

Details on the [inbox page](./inbox.md#who-can-open-the-inbox).

## The two Mailtrap tokens and the webhook secret

A site that sends through Mailtrap and receives its webhook needs two different Mailtrap tokens, plus the webhook secret. They are often mixed up.

| `.env` variable | What it is | Where the wizard tells you to find it | Used for |
| --- | --- | --- | --- |
| `MAIL_PASSWORD` | Token of your **sending domain** (with `MAIL_USERNAME=api`) | Mailtrap: Sending Domains → your domain → Integration → SMTP → Password | Sending mail over SMTP |
| `MAILTRAP_API_TOKEN` | **Account** API token with Admin access | Mailtrap: Settings → API Tokens → Add Token | Creating the webhook (`mailtrap:install`, `mailtrap:webhook`) |
| `MAILTRAP_WEBHOOK_SECRET` | Signing secret of the webhook, not a token | Written by `mailtrap:webhook`, or shown in the webhook's detail panel in Mailtrap | Verifying incoming webhook calls |

- `MAILTRAP_API_TOKEN` is only used while creating the webhook. At runtime the webhook needs `MAILTRAP_WEBHOOK_SECRET` and nothing else.
- Not sending through Mailtrap? Then you need none of the three. Set `MAILTRAP_WEBHOOK_ENABLED=false` so the site exposes no unused endpoint.

## Check that it works

Send a test mail to an address of your own, on a domain that really receives mail:

```bash
php artisan mailtrap:test you@yourdomain.com
```

You should see:

```text
   INFO  Sending test mail to you@yourdomain.com through mailer 'smtp'….

+------------+--------------------------------------+
| Field      | Value                                |
+------------+--------------------------------------+
| Message-ID | b3788c45-6208-442d-b878-2d2a7c127cfc |
| Sender     | noreply@yourdomain.com               |
| Recipient  | you@yourdomain.com                   |
| Subject    | Mailtrap CLI test 10:47:06           |
| Status     | 200                                  |
| Error      | —                                    |
+------------+--------------------------------------+

   INFO  Mail sent successfully.
```

The mailer name is your `MAIL_MAILER`, the sender your `MAIL_FROM_ADDRESS`. Status `200` means Laravel handed the mail to the mailer and the log row was marked as sent. The command exits with code `0`.

When you see something else:

| You see | Meaning |
| --- | --- |
| `Sending failed: Email address … is blocked: …` | The address failed the format or DNS check. See [troubleshooting](./troubleshooting.md#email-address-is-blocked). |
| `Sending failed:` with an SMTP or connection error | The mailer settings are wrong. See [troubleshooting](./troubleshooting.md#sending-failed-with-an-smtp-or-connection-error). |
| `Mail sent, but no log found (is logging disabled?).` | `MAILTRAP_LOGGING_ENABLED` or `MAILTRAP_LOG_SUCCESSFUL` is `false`. |
| An SQL error about `mail_logs` or `email_validations` | The migrations did not run. Run `php artisan migrate`. |

## Install without questions (deploy scripts)

Without a terminal, or with `--no-interaction`, the wizard asks nothing. It runs the migrations and then only does what the flags say:

```bash
php artisan mailtrap:install --webhook --no-interaction          # creates the webhook with MAILTRAP_API_TOKEN
php artisan mailtrap:install --without-webhook --no-interaction  # not sending through Mailtrap
```

| Option | Effect |
| --- | --- |
| `--webhook` | Create the Mailtrap webhook without asking |
| `--without-webhook` | Write `MAILTRAP_WEBHOOK_ENABLED=false` |
| `--url=` | Public webhook URL; defaults to `APP_URL/api/webhooks/mailtrap` |
| `--token=` | Account API token; defaults to `MAILTRAP_API_TOKEN`. A new token is written to `.env`. |
| `--replace` | Replace an existing webhook for the same URL |
| `--config` | Publish `config/manta_mailtrap.php` when it does not exist yet |
| `--skip-migrations` | Do not run the migrations |

With neither `--webhook` nor `--without-webhook` the command prints `Webhook left unchanged. Pass --webhook or --without-webhook.` This mode does not touch the mailer, the validation or the inbox settings. It prints the `viewMailtrap` gate from step 3 as a reminder.

## Run the wizard again

The wizard is safe to run on a site that is already configured. It preselects the current settings, lets you keep or replace the API token, and offers to replace the existing webhook, because Mailtrap does not return the secret of an existing webhook. It never overwrites a published `config/manta_mailtrap.php`, but lists the keys that file lacks compared to the installed version.

## Publish the migrations, config or views

Publishing is optional. The migrations load from the package, and the config has defaults.

```bash
php artisan vendor:publish --tag=mailtrap-migrations   # to database/migrations
php artisan vendor:publish --tag=mailtrap-config       # to config/manta_mailtrap.php
php artisan vendor:publish --tag=mailtrap-views        # to resources/views/vendor/mailtrap
```

The config key is `manta_mailtrap`, not `mailtrap`.

## Every setting

All settings are environment variables. The config path is the key inside `config/manta_mailtrap.php`.

### Validation

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_VALIDATION_ENABLED` | `true` | `validation.enabled` | Check format and DNS of every recipient while sending. `false` skips the DNS lookups. |
| `MAILTRAP_BLOCK_INVALID_EMAILS` | `true` | `validation.block_invalid` | Abort the send for a `blocked` address. `false` sends anyway and keeps the reason on the log. |
| `MAILTRAP_VALIDATION_CACHE_DURATION` | `3600` | `validation.cache_duration` | Seconds a block from a local check stays in force before the address is checked again. `0` never expires. |

### Logging

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_LOGGING_ENABLED` | `true` | `logging.enabled` | Write outgoing mail to `mail_logs`. |
| `MAILTRAP_LOG_SUCCESSFUL` | `true` | `logging.log_successful` | Log mail that is sent. |
| `MAILTRAP_LOG_FAILED` | `true` | `logging.log_failed` | Log a send that was aborted because the recipient is blocked. |
| `MAILTRAP_CLEANUP_AFTER_DAYS` | `30` | `logging.cleanup_after_days` | Retention for the inbox Cleanup button and `model:prune`. `0` keeps everything. |
| `MAILTRAP_LOG_TO_LARAVEL` | `false` | `logging.log_to_laravel` | Also write package messages, including webhook payloads and rejections, to the Laravel log. |

### Webhook

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_WEBHOOK_ENABLED` | `true` | `webhook.enabled` | Register `POST /api/webhooks/mailtrap`. |
| `MAILTRAP_WEBHOOK_SECRET` | empty | `webhook.secret` | The signing secret of the webhook. Without it every call gets `403`. |
| `MAILTRAP_WEBHOOK_VERIFY_SIGNATURE` | `true` | `webhook.verify_signature` | Check the `Mailtrap-Signature` header. `false` accepts unsigned calls. |

### Inbox page

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_UI_ENABLED` | `true` | `ui.enabled` | Register the inbox page. |
| `MAILTRAP_UI_ROUTE` | `mailtrap` | `ui.route` | Path of the page. |
| `MAILTRAP_UI_MIDDLEWARE` | `web` | `ui.middleware` | Comma separated middleware that runs before the `viewMailtrap` gate. Use `web,auth` so a guest is sent to the login page. |
| `MAILTRAP_UI_LAYOUT` | `components.layouts.app` | `ui.layout` | Blade layout the page is rendered in. |
| `MAILTRAP_UI_PER_PAGE` | `25` | `ui.per_page` | Rows per page. |

### Mailtrap account API

Only used by `mailtrap:install` and `mailtrap:webhook`.

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_API_TOKEN` | empty | `api.token` | Account API token with Admin access. |
| `MAILTRAP_ACCOUNT_API_URL` | `https://mailtrap.io` | `api.account_url` | Base URL of the account API. |
| `MAILTRAP_TIMEOUT` | `30` | `api.timeout` | Timeout in seconds for a call to that API. |

### Keys the package does not read

`api.base_url`, `validation.retry_attempts` and the sections `development` and `rate_limiting` are still in the config file but are not read by the package. They are removed in 2.0. Setting them has no effect.

## After changing `.env` on a server

A server with a cached configuration does not read `.env` again. After a change by hand run:

```bash
php artisan config:cache
```

`mailtrap:install` and `mailtrap:webhook` do this themselves when the configuration or the routes are cached.

## Next steps

- [Quick start](./quickstart.md)
- [Webhook](./webhook.md)
- [Troubleshooting](./troubleshooting.md)
