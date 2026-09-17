# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Package overview

`darvis/mailtrap` is a Laravel package (PHP 8.2+, Laravel 11/12/13) providing Mailtrap integration with three main responsibilities: email address validation, outgoing mail logging, and webhook ingestion of Mailtrap delivery events. It is consumed by host Laravel apps via Composer; this repo only contains the library itself.

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
composer lint                 # Pint (check only); composer format fixes
composer analyse              # Larastan, level 5
```

CI (`.github/workflows/tests.yml`) runs PHP 8.2–8.4 × Laravel 11/12/13 × lowest/stable. It turns off Composer's advisory blocking, because every Laravel 11 release has an open advisory. The inbox needs Flux ≥ 2.11 (free `card`/`table`); the lowest-dependency legs catch that kind of floor. The test app loads the Livewire and Flux providers; `LivewireNotLoadedTest` covers a host without them.

Tests use Orchestra Testbench with an in-memory SQLite database; migrations are loaded manually in [TestCase::runPackageMigrations()](tests/TestCase.php) (not via `loadMigrationsFrom`), so **when adding a new migration, also add it to that array** or Pest tests against it will fail with "no such table" — and, worse, the migration itself goes untested (this is how the MySQL-only `SHOW INDEX` in `000003` survived until 1.0.15).

## Architecture

Two service providers, layered:

- [MailtrapServiceProvider](src/MailtrapServiceProvider.php) — the public entry point. Registers routes (`/api/webhooks/mailtrap`), publishes migrations + config, merges config, binds `MailtrapService` as a singleton, and delegates mail-event wiring to `MailServiceProvider`.
- [MailServiceProvider](src/Providers/MailServiceProvider.php) — listens to Laravel's `MessageSending` and `MessageSent` events. This is where the package intercepts every outgoing mail in the host app.

### Outgoing mail flow (the critical path)

On `MessageSending`, for each To, Cc and Bcc recipient:

1. Run `EmailValidation::validateEmail()` (skipped when `validation.enabled` is false). It returns a cached verdict when there is one that has not gone stale. Otherwise it checks the format, then skips DNS if another address on the domain is already valid, and otherwise checks for an MX record that resolves to an IP.
2. If `EmailValidation::getBlockReason()` returns a reason, log a 550 entry via `MailLog::createWithSource()` and throw `Symfony\Component\Mailer\Exception\TransportException`. That aborts the send.
3. Otherwise reuse or create the `X-Message-ID` header (one id shared by all recipients) and create a `MailLog` row with `status_code = null`. On `MessageSent`, the rows for that id are updated to `200`.
4. Optional headers `X-Mail-Type`, `X-Mail-Model`, `X-Mail-Model-ID` are copied into the log; `MailLog::related()` resolves them as a morph relation.

**Blocking is per address, never per domain.** Before 1.2.0 `isBlocked` also matched the domain, so one typo or manual block stopped mail to a whole provider. Only `isValid` looks at the domain, and only as a shortcut to skip DNS lookups.

### Webhook flow

[MailtrapWebhookController::handle()](src/Http/Controllers/MailtrapWebhookController.php) processes Mailtrap event batches (up to 500 events / 30s) and dispatches per `event` type:

- `delivery`, `open`, `click` → `EmailValidation::markAsValid()`
- `bounce` → `markAsInvalid()` with response code (default 550)
- `spam` → `markAsInvalid()` (default 400)
- `reject` → `markAsInvalid()` (default 450)

The event-to-verdict mapping lives in the `EVENTS` constant of the controller. For every handled event, `apply()` updates the `MailLog` row matching `message_id` **and** recipient (recipients of one mail share the id), or creates a new one (the local `MessageSending` path may not have run, e.g. when Mailtrap is the only source of truth). The endpoint always returns 200 to keep Mailtrap from retrying — failures are swallowed and counted as `skipped`.

Webhook signature verification runs in [VerifyMailtrapWebhookSignature](src/Http/Middleware/VerifyMailtrapWebhookSignature.php), attached as route middleware in the service provider — not inline in the controller. It checks the HMAC-SHA256 of the **raw** request body against the `Mailtrap-Signature` header and **fails closed**: with `webhook.verify_signature` on and no `webhook.secret`, every call is rejected with 403. Never re-encode the body before hashing; Mailtrap signs the bytes as sent. The route itself is only registered when `webhook.enabled` is true.

The secret cannot be chosen locally: Mailtrap generates it and returns it only in the create response. `mailtrap:webhook` ([MailtrapWebhookCommand](src/Console/Commands/MailtrapWebhookCommand.php)) creates the webhook via [MailtrapWebhookApi](src/Services/MailtrapWebhookApi.php) and writes the secret with [EnvironmentFile](src/Support/EnvironmentFile.php); `mailtrap:install` wraps it. Both rebuild cached config/routes after writing `.env`, otherwise the cached config keeps the old (empty) secret.

### Validation states

`email_validations.status` is one of `valid` / `invalid` / `blocked`:

- `valid` — passed checks or confirmed by a delivery/open/click event.
- `invalid` — failed at the recipient side (bounce, spam, reject). **Does not block future sends** — `isBlocked` only matches `status = 'blocked'`. So `markAsInvalid` records the failure but lets retries through.
- `blocked` — hard stop on send. Set by local validation failures (bad format, no MX) and by `MailtrapService::handleFailure()` on API/transport errors. Use `markAsBlocked` when you actually want to halt sending.

This distinction matters when triaging "why isn't this email going out" vs "why are we still hammering a dead address."

Only blocks whose reason is in `EmailValidation::LOCAL_CHECK_REASONS` expire (`isStale`); manual `markAsBlocked()` blocks are permanent. Pre-1.2.0 Dutch reasons are in that list on purpose, because existing rows still carry them.

Host apps hook in through `Events\MailBlocked` (dispatched before the TransportException) and `Events\MailtrapEventReceived` (every webhook event, including types the controller ignores; listener exceptions are caught). The webhook finds a log by the `x_message_id` custom variable that `MailServiceProvider` adds to `X-MT-Custom-Variables` (merged, and left alone above Mailtrap's 1000-byte limit), with Mailtrap's `message_id` as fallback.

Use the constants (`EmailValidation::VALID/INVALID/BLOCKED`, `MailLog::STATUS_SENT/STATUS_BLOCKED`) and the `MailLog` scopes (`successful`, `failed`, `pending`, `blocked`) instead of string literals. Logging to the Laravel log always goes through `Support\PackageLog`, which honours `logging.log_to_laravel`.

### `MailLog::createWithSource()`

Captures `debug_backtrace` to record `source_file` and `source_line` of the caller (relative to `base_path()`). Auto-generates `message_id` with a prefix based on `status_code` (`BLOCKED_`, `VALIDATION_ERROR_`, `TRANSPORT_ERROR_`, `ERROR_`) when none is provided — used for logging mails that were stopped before a real Message-ID existed. Plain `MailLog::create()` does not do any of this.

### Migrations

`message_id` on `mail_logs` was originally `unique`. Migration `2024_01_01_000003` drops that constraint (so blocked/error logs can share generated IDs and unsent attempts don't collide) and replaces it with a plain index. It detects existing indexes with `Schema::getIndexes('mail_logs')`, which is native to Laravel 11+ and works on every driver. It used raw `SHOW INDEX` until 1.0.15; that is MySQL-only syntax and broke every migration in host apps testing on SQLite. Follow the `Schema::` route for any future schema-altering migration — it satisfies the no-DBAL constraint without tying the package to one database.

## Conventions specific to this package

- `docs/` is also the GitHub Pages site (Jekyll, Just the Docs, `docs/_config.yml`); `docs/README.md` is only for browsing on GitHub and is excluded from the site. Every page needs `title`, `description` and `nav_order` front matter; pages under `docs/email-validation/` also need `parent: "Email validation"`. Quote front matter values: an unquoted `: ` makes Jekyll silently drop the whole block. Don't write `{{ }}` or `{% %}` in code examples; Liquid is intended only in `faq.md`, `llms.txt` and `_includes/`. Package facts live in `docs/_config.yml` (`package`, `developer`) and FAQ answers in `docs/_data/faq.yml`; the pages, the structured data and `llms.txt` read from there. The footer credit is `ARVID.NL` only. The site says the package is not an official Mailtrap product; keep it that way and don't use Mailtrap's logo. `tests/DocsSiteTest.php` guards these rules.
- `resources/boost/` holds the Laravel Boost guideline and the `mailtrap-development` skill that host apps receive. Update them when public behaviour, commands or config change.
- Keep the public API compatible within 1.x. Don't add return types to existing public methods that host apps may override, and don't change `$casts` into `casts()`. Deprecate first and remove in 2.0.

- Config file stays as `config/manta_mailtrap.php` with that exact name (do not rename to `mailtrap.php`).
- Everything is in English: comments, log and exception messages, command output and the inbox UI. README and CHANGELOG too.
- Don't introduce Doctrine DBAL — Laravel 11+ compatibility depends on its absence. Use the native schema builder (`Schema::hasColumn`, `Schema::hasTable`, `Schema::getIndexes`) for schema checks. Never reach for driver-specific SQL such as `SHOW INDEX`: host apps run their tests on SQLite, where it is a syntax error.
- The webhook controller extends `Illuminate\Routing\Controller` (not an app-level base controller) so the package works without the host app's `App\Http\Controllers\Controller`.
