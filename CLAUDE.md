# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Package overview

`darvis/mailtrap` is a Laravel package (PHP 8.1+, Laravel 11/12/13) providing Mailtrap integration with three main responsibilities: email address validation, outgoing mail logging, and webhook ingestion of Mailtrap delivery events. It is consumed by host Laravel apps via Composer; this repo only contains the library itself.

- Namespace: `Darvis\Mailtrap\` → `src/`
- Service provider auto-registered via `extra.laravel.providers` in [composer.json](composer.json)
- Config key: `manta_mailtrap` (note: the config *key* is `manta_mailtrap`, not `mailtrap`)
- Container alias: `app('mailtrap')` resolves [MailtrapService](src/Services/MailtrapService.php)

## Commands

```bash
composer test                 # Run the full Pest suite (alias for "pest tests")
vendor/bin/pest                # Same, invoked directly
vendor/bin/pest tests/MailtrapWebhookControllerTest.php   # Run one test file
vendor/bin/pest --filter "creates mail log"               # Run a single test by name
```

Tests use Orchestra Testbench with an in-memory SQLite database; migrations are loaded manually in [TestCase::runPackageMigrations()](tests/TestCase.php) (not via `loadMigrationsFrom`), so **when adding a new migration, also add it to that array** or Pest tests against it will fail with "no such table".

## Architecture

Two service providers, layered:

- [MailtrapServiceProvider](src/MailtrapServiceProvider.php) — the public entry point. Registers routes (`/api/webhooks/mailtrap`), publishes migrations + config, merges config, binds `MailtrapService` as a singleton, and delegates mail-event wiring to `MailServiceProvider`.
- [MailServiceProvider](src/Providers/MailServiceProvider.php) — listens to Laravel's `MessageSending` and `MessageSent` events. This is where the package intercepts every outgoing mail in the host app.

### Outgoing mail flow (the critical path)

On `MessageSending`, for each recipient:

1. Run `EmailValidation::validateEmail()` if no prior result exists (format → MX → MX-resolves-to-IP).
2. If `EmailValidation::isBlocked()` (matches by email **or** by domain), log a 550 entry via `MailLog::createWithSource()` and throw `Symfony\Component\Mailer\Exception\TransportException` — this aborts the send.
3. Otherwise overwrite any incoming `X-Message-ID` header with a fresh UUID and create a `MailLog` row with `status_code = null`. On `MessageSent`, the row is updated to `200`.
4. Optional headers `X-Mail-Type`, `X-Mail-Model`, `X-Mail-Model-ID` are copied into the log for cross-referencing back to the calling domain object.

Implication: **blocking is by-domain, not just by-email** (see `EmailValidation::isBlocked` / `isValid` — both `orWhere('domain', ...)`). One blocked address poisons the whole domain for sending. Keep this in mind when marking records.

### Webhook flow

[MailtrapWebhookController::handle()](src/Http/Controllers/MailtrapWebhookController.php) processes Mailtrap event batches (up to 500 events / 30s) and dispatches per `event` type:

- `delivery`, `open`, `click` → `EmailValidation::markAsValid()`
- `bounce` → `markAsInvalid()` with response code (default 550)
- `spam` → `markAsInvalid()` (default 400)
- `reject` → `markAsInvalid()` (default 450)

For every handled event, `upsertMailLogFromWebhook()` either updates the existing `MailLog` row by `message_id` or creates a new one (the local `MessageSending` path may not have run, e.g. when Mailtrap is the only source of truth). The endpoint always returns 200 to keep Mailtrap from retrying — failures are swallowed and counted as `skipped`.

Webhook signature verification is configured (`webhook.verify_signature`, `webhook.secret` in config) but **not yet enforced** in the controller. If you add it, do so as middleware on the route, not inline.

### Validation states

`email_validations.status` is one of `valid` / `invalid` / `blocked`:

- `valid` — passed checks or confirmed by a delivery/open/click event.
- `invalid` — failed at the recipient side (bounce, spam, reject). **Does not block future sends** — `isBlocked` only matches `status = 'blocked'`. So `markAsInvalid` records the failure but lets retries through.
- `blocked` — hard stop on send. Set by local validation failures (bad format, no MX) and by `MailtrapService::handleFailure()` on API/transport errors. Use `markAsBlocked` when you actually want to halt sending.

This distinction matters when triaging "why isn't this email going out" vs "why are we still hammering a dead address."

### `MailLog::createWithSource()`

Captures `debug_backtrace` to record `source_file` and `source_line` of the caller (relative to `base_path()`). Auto-generates `message_id` with a prefix based on `status_code` (`BLOCKED_`, `VALIDATION_ERROR_`, `TRANSPORT_ERROR_`, `ERROR_`) when none is provided — used for logging mails that were stopped before a real Message-ID existed. Plain `MailLog::create()` does not do any of this.

### Migrations

`message_id` on `mail_logs` was originally `unique`. Migration `2024_01_01_000003` drops that constraint (so blocked/error logs can share generated IDs and unsent attempts don't collide) and replaces it with a plain index. It uses raw `SHOW INDEX` queries rather than Doctrine DBAL — required for Laravel 11+ compatibility (see [CHANGELOG.md](CHANGELOG.md) 1.0.8/1.0.9). Follow the same pattern for any future schema-altering migrations on existing columns.

## Conventions specific to this package

- Config file stays as `config/manta_mailtrap.php` with that exact name (do not rename to `mailtrap.php`).
- Existing inline comments and log messages are in Dutch; new code in the same files should match. README and CHANGELOG are English.
- Don't introduce Doctrine DBAL — Laravel 11+ compatibility depends on its absence (use raw `SHOW INDEX` / `Schema::hasColumn` style checks for schema changes).
- The webhook controller extends `Illuminate\Routing\Controller` (not an app-level base controller) so the package works without the host app's `App\Http\Controllers\Controller`.
