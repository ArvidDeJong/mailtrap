# Installation & Configuration

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13
- For the [inbox UI](./inbox.md) only: `livewire/livewire` (^3.7.4 or ^4.0) and `livewire/flux` (^2.11, the free edition is enough)

## Installation

```bash
composer require darvis/mailtrap
php artisan mailtrap:install
```

The service provider is registered automatically through Laravel's package discovery.

## Mailtrap credentials: two tokens and a secret

A site that sends through Mailtrap and receives its webhook needs **two different
Mailtrap tokens**, plus the webhook secret. They are easy to mix up:

| `.env` variable | What it is | Where to find it in Mailtrap | Used for |
| --- | --- | --- | --- |
| `MAIL_PASSWORD` | Token of your **sending domain** (with `MAIL_USERNAME=api`) | Sending Domains → your domain → Integration → SMTP → Password | Sending mail over SMTP |
| `MAILTRAP_API_TOKEN` | **Account** API token with Admin access | Settings → API Tokens → Add Token | Creating the webhook (`mailtrap:install`, `mailtrap:webhook`) |
| `MAILTRAP_WEBHOOK_SECRET` | Signing secret of the webhook, not a token | Written by `mailtrap:webhook`, or shown once in the webhook's detail panel | Verifying incoming webhook calls |

- The account token cannot replace the domain token: Mailtrap only accepts a token with
  admin access to the sending domain as SMTP password.
- `MAILTRAP_API_TOKEN` is only needed while creating the webhook. At runtime the webhook
  needs just `MAILTRAP_WEBHOOK_SECRET`.
- Not sending through Mailtrap? Then you need none of the three.

## Setup wizard

`mailtrap:install` walks you through eight steps and explains each one before it asks
anything:

1. **Check the basics**: `.env`, `APP_URL`, the database connection and the current mailer
2. **Database tables**: runs the migrations
3. **Mailtrap API token**: the account token for the webhook; explains where to create one and verifies it with Mailtrap
4. **Sending mail**: asks for the sending domain token and fills in the Mailtrap SMTP settings and the sender address, or keeps your own mailer
5. **Webhook**: creates the webhook and stores its signing secret. On a local site it tells you to run the wizard on the live server instead.
6. **Address validation**: check and block, check and only log, or off
7. **Inbox page**: who may open it, which layout it uses and the Tailwind `@source` line
8. **Test mail**: sends one through `mailtrap:test`

Every answer is written to `.env` straight away, and a summary at the end lists what is
still left to do. `.env` is not in git, so run the wizard on every server.

### Non-interactive

Without a terminal, e.g. in a deploy script, the wizard asks nothing and only does what
the flags say:

```bash
php artisan mailtrap:install --webhook --no-interaction          # uses MAILTRAP_API_TOKEN
php artisan mailtrap:install --without-webhook --no-interaction  # not sending through Mailtrap
```

### Re-running

The wizard is safe to re-run on a site that is already configured: it preselects the
current settings, lets you keep or replace the API token, and offers to replace the
existing webhook (Mailtrap never shows an old secret again). It never overwrites a
published `config/manta_mailtrap.php`, but lists the keys that file lacks compared to the
installed version.

```bash
php artisan mailtrap:install --webhook --replace --token=new_token --no-interaction
```

## Migrations

Migrations are loaded automatically, so `php artisan migrate` is enough. To publish them
into your application instead:

```bash
php artisan vendor:publish --tag=mailtrap-migrations
php artisan migrate
```

## Configuration file

```bash
php artisan vendor:publish --tag=mailtrap-config
```

This creates `config/manta_mailtrap.php` with these sections:

| Section | Contents |
| --- | --- |
| `api` | Account API token (for creating the webhook) and API URLs |
| `validation` | Pre-send validation, blocking and how long a local verdict stays valid |
| `logging` | Logging of outgoing mail and retention |
| `webhook` | Webhook route and signature verification |
| `ui` | Route, middleware, layout and page size of the inbox |

## Environment variables

```env
# Account API token, only for creating the webhook
MAILTRAP_API_TOKEN=

# Validation
MAILTRAP_VALIDATION_ENABLED=true
MAILTRAP_BLOCK_INVALID_EMAILS=true
MAILTRAP_VALIDATION_CACHE_DURATION=3600   # seconds, 0 = never expire

# Logging
MAILTRAP_LOGGING_ENABLED=true
MAILTRAP_LOG_SUCCESSFUL=true
MAILTRAP_LOG_FAILED=true
MAILTRAP_CLEANUP_AFTER_DAYS=30
MAILTRAP_LOG_TO_LARAVEL=false

# Webhook
MAILTRAP_WEBHOOK_ENABLED=true
MAILTRAP_WEBHOOK_SECRET=                  # not a token: the webhook's signing secret
MAILTRAP_WEBHOOK_VERIFY_SIGNATURE=true

# Inbox UI
MAILTRAP_UI_ENABLED=true
MAILTRAP_UI_ROUTE=mailtrap
MAILTRAP_UI_MIDDLEWARE=web,auth
MAILTRAP_UI_LAYOUT=components.layouts.app
MAILTRAP_UI_PER_PAGE=25
```

## Next Steps

- [Sending, Blocking & Mail Logs](./sending-and-logging.md)
- [Webhook](./webhook.md)
- [Inbox UI & Health Check](./inbox.md)
