---
title: "Environment variables"
description: "Every .env variable of darvis/mailtrap: what it does, its default, and where you find the Mailtrap tokens and the webhook signing secret."
nav_order: 3
---

# Environment variables

Every setting of the package is an environment variable. You rarely set them by hand: `php artisan mailtrap:install` asks for the values and writes them to `.env`. This page says what each one does, so you can check or change a value yourself. The config path is the key inside `config/manta_mailtrap.php`; the config key is `manta_mailtrap`.

## A typical `.env`

A live site that sends through Mailtrap, receives the webhook and shows the inbox to logged in users:

```env
# Sending through Mailtrap (Laravel's own mail settings)
MAIL_MAILER=smtp
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=api
MAIL_PASSWORD=your-sending-domain-token
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Your company"

# darvis/mailtrap
MAILTRAP_API_TOKEN=your-account-api-token
MAILTRAP_WEBHOOK_ENABLED=true
MAILTRAP_WEBHOOK_SECRET=the-signing-secret-of-the-webhook
MAILTRAP_UI_MIDDLEWARE=web,auth
```

Every other variable has a default that fits most sites. A site that does not send through Mailtrap only needs `MAILTRAP_WEBHOOK_ENABLED=false`.

## Sending through Mailtrap

These are Laravel's own mail settings, not settings of the package. The wizard writes them when you choose to send through Mailtrap Email Sending. The package checks and logs mail with every mailer.

| Variable | Value | What it is |
| --- | --- | --- |
| `MAIL_MAILER` | `smtp` | Laravel's mailer |
| `MAIL_HOST` | `live.smtp.mailtrap.io` | Mailtrap's SMTP server for transactional Email Sending. `bulk.smtp.mailtrap.io` is the bulk stream; the webhook then follows that stream. `sandbox.smtp.mailtrap.io` is Email Testing: nothing is delivered and there are no webhook events. |
| `MAIL_PORT` | `587` | STARTTLS port. The wizard also sets `MAIL_SCHEME=smtp` and `MAIL_ENCRYPTION=tls` when those are in `.env`, because a leftover `smtps` breaks the connection. |
| `MAIL_USERNAME` | `api` | Always `api` |
| `MAIL_PASSWORD` | token | The token of your **sending domain**, see below |
| `MAIL_FROM_ADDRESS` | an address | Must be on a domain you verified in Mailtrap under Sending Domains, or Mailtrap refuses the mail |
| `MAIL_FROM_NAME` | a name | The name recipients see next to the address. Without it Laravel uses its default, often "Laravel" or "Example". |

## The two Mailtrap tokens and the webhook secret

A site that sends through Mailtrap and receives its webhook needs two different Mailtrap tokens, plus the webhook secret. They are often mixed up.

| `.env` variable | What it is | Where the wizard tells you to find it | Used for |
| --- | --- | --- | --- |
| `MAIL_PASSWORD` | Token of your **sending domain** (with `MAIL_USERNAME=api`) | Mailtrap: Sending Domains → your domain → Integration → SMTP → Password | Sending mail over SMTP |
| `MAILTRAP_API_TOKEN` | **Account** API token with Admin access | Mailtrap: Settings → API Tokens → Add Token | Creating the webhook (`mailtrap:install`, `mailtrap:webhook`) |
| `MAILTRAP_WEBHOOK_SECRET` | Signing secret of the webhook, not a token | Written by `mailtrap:webhook`, or shown in the webhook's detail panel in Mailtrap | Verifying incoming webhook calls |

- `MAILTRAP_API_TOKEN` is only used while creating the webhook. At runtime the webhook needs `MAILTRAP_WEBHOOK_SECRET` and nothing else.
- Not sending through Mailtrap? Then you need none of the three. Set `MAILTRAP_WEBHOOK_ENABLED=false` so the site exposes no unused endpoint.
- `mailtrap:install` asks for the signing secret when it cannot create the webhook itself, for example without an API token. Create the webhook in the Mailtrap dashboard and paste its secret.

## Validation

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_VALIDATION_ENABLED` | `true` | `validation.enabled` | Check format and DNS of every recipient while sending. `false` skips the DNS lookups. |
| `MAILTRAP_BLOCK_INVALID_EMAILS` | `true` | `validation.block_invalid` | Abort the send for a `blocked` address. `false` sends anyway and keeps the reason on the log. |
| `MAILTRAP_VALIDATION_CACHE_DURATION` | `3600` | `validation.cache_duration` | Seconds a block from a local check stays in force before the address is checked again. `0` never expires. |

## Logging

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_LOGGING_ENABLED` | `true` | `logging.enabled` | Write outgoing mail to `mail_logs`. |
| `MAILTRAP_LOG_SUCCESSFUL` | `true` | `logging.log_successful` | Log mail that is sent. |
| `MAILTRAP_LOG_FAILED` | `true` | `logging.log_failed` | Log a send that was aborted because the recipient is blocked. |
| `MAILTRAP_CLEANUP_AFTER_DAYS` | `30` | `logging.cleanup_after_days` | Retention for the inbox Cleanup button and `model:prune`. `0` keeps everything. The wizard asks for it, because the logs hold recipients. |
| `MAILTRAP_LOG_TO_LARAVEL` | `false` | `logging.log_to_laravel` | Also write package messages, including webhook payloads and rejections, to the Laravel log. |

## Webhook

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_WEBHOOK_ENABLED` | `true` | `webhook.enabled` | Register `POST /api/webhooks/mailtrap`. |
| `MAILTRAP_WEBHOOK_SECRET` | empty | `webhook.secret` | The signing secret of the webhook. Without it every call gets `403`. |
| `MAILTRAP_WEBHOOK_VERIFY_SIGNATURE` | `true` | `webhook.verify_signature` | Check the `Mailtrap-Signature` header. `false` accepts unsigned calls. |

## Inbox page

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_UI_ENABLED` | `true` | `ui.enabled` | Register the inbox page. |
| `MAILTRAP_UI_ROUTE` | `mailtrap` | `ui.route` | Path of the page. |
| `MAILTRAP_UI_MIDDLEWARE` | `web` | `ui.middleware` | Comma separated middleware that runs before the `viewMailtrap` gate. Use `web,auth` so a guest is sent to the login page. |
| `MAILTRAP_UI_LAYOUT` | `components.layouts.app` | `ui.layout` | Blade layout the page is rendered in. |
| `MAILTRAP_UI_PER_PAGE` | `25` | `ui.per_page` | Rows per page. |

## Mailtrap account API

Only used by `mailtrap:install` and `mailtrap:webhook`.

| Variable | Default | Config path | What it does |
| --- | --- | --- | --- |
| `MAILTRAP_API_TOKEN` | empty | `api.token` | Account API token with Admin access. |
| `MAILTRAP_ACCOUNT_API_URL` | `https://mailtrap.io` | `api.account_url` | Base URL of the account API. |
| `MAILTRAP_TIMEOUT` | `30` | `api.timeout` | Timeout in seconds for a call to that API. |

## Variables the package does not read

These are still in the config file but the package never reads them. They are removed in 2.0. Setting them has no effect, so you can leave them out of `.env`.

| Variable | Config path |
| --- | --- |
| `MAILTRAP_BASE_URL` | `api.base_url` |
| `MAILTRAP_VALIDATION_RETRY_ATTEMPTS` | `validation.retry_attempts` |
| `MAILTRAP_DEBUG_MODE` | `development.debug_mode` |
| `MAILTRAP_LOG_API_REQUESTS` | `development.log_api_requests` |
| `MAILTRAP_SANDBOX_MODE` | `development.sandbox_mode` |
| `MAILTRAP_RATE_LIMITING_ENABLED` | `rate_limiting.enabled` |
| `MAILTRAP_MAX_REQUESTS_PER_MINUTE` | `rate_limiting.max_requests_per_minute` |

## After changing `.env` on a server

A server with a cached configuration does not read `.env` again. After a change by hand run:

```bash
php artisan config:cache
```

`mailtrap:install` and `mailtrap:webhook` do this themselves when the configuration or the routes are cached.
