# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. The conventions shared by every darvis package (language, releases, CI, docs site, Boost guidelines, public API policy) are in [../CLAUDE.md](../CLAUDE.md); this file only holds what is specific to this package.

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

The inbox needs Flux ≥ 2.11 (free `card`/`table`); the lowest-dependency legs catch that kind of floor. The test app loads the Livewire and Flux providers; `LivewireNotLoadedTest` covers a host without them.

Tests use Orchestra Testbench with an in-memory SQLite database; migrations are loaded manually in [TestCase::runPackageMigrations()](tests/TestCase.php) (not via `loadMigrationsFrom`), so **when adding a new migration, also add it to that array** or Pest tests against it will fail with "no such table" — and, worse, the migration itself goes untested (this is how the MySQL-only `SHOW INDEX` in `000003` survived until 1.0.15).

## Architecture

Two service providers, layered:

- [MailtrapServiceProvider](src/MailtrapServiceProvider.php) — the public entry point. Registers routes (`/api/webhooks/mailtrap`), publishes migrations + config, merges config, binds `MailtrapService` as a singleton, and delegates mail-event wiring to `MailServiceProvider`.
- [MailServiceProvider](src/Providers/MailServiceProvider.php) — listens to Laravel's `MessageSending` and `MessageSent` events. This is where the package intercepts every outgoing mail in the host app.

### Outgoing mail flow (the critical path)

On `MessageSending`, for each To, Cc and Bcc recipient:

1. Run `EmailValidation::validateEmail()` (skipped when `validation.enabled` is false). It returns a cached verdict when there is one that has not gone stale. Otherwise it checks the format, then skips DNS if another address on the domain is already valid, and otherwise checks for an MX record that resolves to an IP.
2. All recipients are decided on before a row is written. If `EmailValidation::getBlockReason()` returns a reason for one of them, every recipient gets a `MailLog::STATUS_BLOCKED` (550) row via `MailLog::createWithSource()` (nothing was sent to anyone, so none may stay pending), `MailBlocked` fires for the first blocked address and a `Symfony\Component\Mailer\Exception\TransportException` aborts the send.
3. Otherwise reuse or create the `X-Message-ID` header (one id shared by all recipients) and create a `MailLog` row with `status_code = null`. On `MessageSent`, the rows for that id are updated to `200`.
4. Optional headers `X-Mail-Type`, `X-Mail-Model`, `X-Mail-Model-ID` are copied into the log; `MailLog::related()` resolves them as a morph relation.

**Blocking is per address, never per domain.** Before 1.2.0 `isBlocked` also matched the domain, so one typo or manual block stopped mail to a whole provider. Only `isValid` looks at the domain, and only as a shortcut to skip DNS lookups.

Every write to `mail_logs` in the listeners goes through `MailServiceProvider::writeLog()`, which reports a failure and carries on. Never let the log decide whether a mail goes out: a missing table or a rejected value must not cost the host a password reset mail. Laravel has no event for a failed transport (only `MessageSending` and `MessageSent`, in 11, 12 and 13), so a row whose transport threw stays pending; that is documented, not fixable from here.

Addresses go through [EmailAddress::normalise()](src/Support/EmailAddress.php) (trim, lower case) wherever `email_validations` is written or read, and `MailLog::toRecipient()` compares `lower(recipient)`. Lookups use `whereRaw('lower(email) = ?')` so rows stored with capitals before 1.6.0 are still found; `saveValidation()` folds such duplicates into one row. Never compare an address with a plain `where('email', …)`: SQLite and PostgreSQL compare as written, so a block would not stop another spelling.

### Webhook flow

[MailtrapWebhookController::handle()](src/Http/Controllers/MailtrapWebhookController.php) processes Mailtrap event batches (up to 500 events / 30s) and dispatches per `event` type:

- `delivery`, `open`, `click` → `EmailValidation::markAsValid()`
- `bounce` → `markAsInvalid()` with response code (default 550)
- `spam` → `markAsInvalid()` (default 400)
- `reject` → `markAsInvalid()` (default 450)

The event-to-verdict mapping lives in the `EVENTS` constant of the controller. For every handled event, `apply()` updates the `MailLog` row matching `message_id` **and** recipient (recipients of one mail share the id), or creates a new one (the local `MessageSending` path may not have run, e.g. when Mailtrap is the only source of truth). Once the payload has an `events` list the endpoint returns 200 to keep Mailtrap from retrying — failures are swallowed and counted as `skipped`. A payload without that list gets 400.

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

Walks `debug_backtrace` and records the first frame outside `vendor/`, outside this package's `src/` and not the entry script as `source_file` and `source_line` (relative to `base_path()`), or nulls when there is none. Never go back to a fixed depth: the method is called from an event listener, so a fixed depth always lands in `Illuminate/Events/Dispatcher.php`. Auto-generates `message_id` with a prefix based on `status_code` (`BLOCKED_`, `VALIDATION_ERROR_`, `TRANSPORT_ERROR_`, `ERROR_`) when none is provided — used for logging mails that were stopped before a real Message-ID existed. Plain `MailLog::create()` does not do any of this.

### Migrations

`message_id` on `mail_logs` was originally `unique`. Migration `2024_01_01_000003` drops that constraint (so blocked/error logs can share generated IDs and unsent attempts don't collide) and replaces it with a plain index. It detects existing indexes with `Schema::getIndexes('mail_logs')`, which is native to Laravel 11+ and works on every driver. It used raw `SHOW INDEX` until 1.0.15; that is MySQL-only syntax and broke every migration in host apps testing on SQLite. Follow the `Schema::` route for any future schema-altering migration — it satisfies the no-DBAL constraint without tying the package to one database.

## Conventions specific to this package

- Every docs page sits directly in `docs/`; there is no `docs/README.md` and no sub section. The index, the README and the FAQ say the package is unofficial (not made or endorsed by Mailtrap); keep it that way and don't use Mailtrap's logo. `tests/DocsSiteTest.php` guards these rules.
- The inbox is guarded by the `viewMailtrap` gate (Horizon pattern). [AuthorizeInbox](src/Http/Middleware/AuthorizeInbox.php) is appended to the route after `ui.middleware` and registered as Livewire persistent middleware, because Livewire update requests do not run route middleware. The provider defines the gate in a `booted()` callback and only when `Gate::has()` is false, so a host gate always wins; the default allows `local` only. Never make the default more permissive and never rely on `auth` alone: on a site with public registration every customer has a login, and the page shows recipients, deletes logs and sends mail. Never remove the `AuthorizeInbox::authorize()` calls from `boot()`, `render()` and the actions of [MailtrapInbox](src/Livewire/MailtrapInbox.php): every public Livewire method is callable by whoever holds a component snapshot, and an embedded component never passes the route middleware. A new public action gets the same first line. The gate closure takes a nullable user, otherwise Laravel never calls it for a guest. Don't change the `ui.middleware` default to `web,auth`: a host without a `login` route would get an error page instead of a 403.
- `resources/boost/` also holds the `mailtrap-development` skill.
- Don't change `$casts` into `casts()`; host apps may override it.

- Config file stays as `config/manta_mailtrap.php` with that exact name (do not rename to `mailtrap.php`).
