# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **`mailtrap:install` is now a step-by-step setup wizard** for people installing the
  package for the first time. It explains each step, then checks `.env`, `APP_URL` and
  the database connection. It runs the migrations, verifies the API token with Mailtrap
  before saving it, and can fill in the Mailtrap SMTP settings (`live.smtp.mailtrap.io`,
  user `api`, token as password) and the sender address. It creates the webhook, or
  explains that a local site needs the wizard on the live server. It also covers address
  validation, access to the inbox page (with a warning when there is no `login` route),
  the inbox layout and the Tailwind `@source` line, and it sends a test mail. Answers go
  to `.env` right away, and a summary lists the open items.
- Without a terminal (`--no-interaction`), `mailtrap:install` asks nothing and follows
  only its flags. It no longer offers to publish the config file: pass `--config` for that.
  `--token` is still written to `.env` when it differs from the stored token.
- **Requires PHP 8.2.** The package claimed `^8.1`, but Laravel 11 already needs 8.2, so
  8.1 could never install it.
- **Manual blocks no longer expire.** `validation.cache_duration` applied to every
  `blocked` address, so a block set with `markAsBlocked()` lifted itself after an hour.
  Now only blocks from the local format and DNS checks expire.

### Added

- **Events for the host application.** `Darvis\Mailtrap\Events\MailBlocked` is
  dispatched just before a send to a blocked address is aborted.
  `Darvis\Mailtrap\Events\MailtrapEventReceived` is dispatched for every signed webhook
  event, including `unsubscribe`, `soft bounce` and `suspension`, which the package does
  not act on itself. A listener that throws is logged and does not affect the rest of the
  batch.
- **Webhook events find their log through a Mailtrap custom variable.** Every outgoing
  mail gets `x_message_id` in the `X-MT-Custom-Variables` header, and the webhook uses it
  before Mailtrap's own `message_id`. Custom variables the application already set are
  kept. Previously a webhook could create a second log row when Mailtrap reported its
  own id.
- GitHub Actions: tests on PHP 8.2–8.4 against Laravel 11, 12 and 13 with lowest and
  stable dependencies, plus Pint and Larastan.
- `composer lint`, `composer format` and `composer analyse`, and a `LICENSE` file.
- `.gitattributes` keeps `tests/`, `docs/` and other development files out of the
  installed package.

### Fixed

- **The inbox page crashed on Livewire 4** with a Blade syntax error: `wire:key` with an
  interpolated value on a Flux component inside a loop compiles to invalid PHP there.
- **An app with Livewire installed but its provider excluded from discovery crashed on
  boot.** The inbox is now only registered when Livewire is actually loaded.
- The `suggest` for Flux now names the minimum version: the free `card` and `table`
  components the inbox uses arrived in Flux 2.11.

## [1.2.0] - 2026-09-16

### Added

- **`mailtrap:webhook` command.** Creates the email sending webhook through the
  Mailtrap API and writes the signing secret, which Mailtrap returns only on creation,
  to `.env` together with `MAILTRAP_WEBHOOK_ENABLED=true`. Refuses local URLs, offers
  to replace an existing webhook for the same URL, and rebuilds cached config and
  routes so the secret takes effect without a redeploy. `--show` prints the secret
  instead of writing it.
- **`mailtrap:install` command.** Runs the migrations, optionally publishes the
  config, asks for the API token and sets up the webhook in one run — or disables the
  endpoint with `--without-webhook`. Works non-interactively with `--webhook` or
  `--without-webhook`.
  Re-running it on a configured site shows the current state (token, endpoint,
  secret, and a stale config cache) and lets you swap the token or replace the webhook.
- `api.account_url` config (`MAILTRAP_ACCOUNT_API_URL`, default `https://mailtrap.io`)
  for the account API used by these commands.
- **Laravel Boost resources.** A guideline in `resources/boost/guidelines/core.blade.php`
  and a `mailtrap-development` skill, picked up by `boost:install` and
  `boost:update --discover`.
- `MailLog` scopes `pending()` and `blocked()`, the constants `MailLog::STATUS_SENT` and
  `STATUS_BLOCKED`, and a `related()` morph relation that resolves the model named in the
  `X-Mail-Model` / `X-Mail-Model-ID` headers.
- `MailLog` is `MassPrunable`: `php artisan model:prune --model="Darvis\Mailtrap\Models\MailLog"`
  removes logs older than `logging.cleanup_after_days`, a setting that only the inbox
  cleanup button used before.
- `EmailValidation::VALID`, `INVALID` and `BLOCKED` constants and `EmailValidation::domainOf()`.
- `@property` docblocks on both models and a `suggest` for Livewire and Flux in `composer.json`.

### Fixed

- **One blocked address no longer blocks its whole domain.** `isBlocked()` also matched
  on the domain, so a single address with a bad format, a manual `markAsBlocked()` or a
  failed API call stopped all mail to, say, gmail.com — for good, because only the
  original address was ever re-checked. Blocking is now per address.
- **Cc and Bcc recipients are validated and logged.** Only To was checked, so a blocked
  address in Bcc was still sent to.
- **`bulkValidationWithCheck()` counted failed addresses as valid.** `validateEmail()`
  returns `null` for a valid address, and the check was inverted.
- **Webhook events for different mails to the same address are all processed.** The
  batch skipped every event after the first for an address, so a delivery that followed
  a bounce in the same batch never updated its log. Only identical events (same
  `event_id`) are skipped now.
- **Webhook events update only the recipient they are about.** Recipients of one mail
  share its message id, so a bounce for one recipient also marked the others as bounced.
- **Webhook events store the reason on an existing log.** Only the status code was
  updated, so the bounce message was lost.
- **A leftover unique index on `message_id` no longer breaks multi-recipient mail on
  SQLite and PostgreSQL.** The duplicate was recognised by MySQL's error text only; it
  now catches `UniqueConstraintViolationException`.
- **Mail with a `Sender` but no `From` header** no longer crashes the logging listener.
- A known-valid domain no longer lets a malformed address skip the format check; the
  listener now always calls `validateEmail()`, which still skips the DNS lookups for
  such a domain.

### Changed

- **Everything is in English.** Validation reasons, webhook responses and log messages
  (for example `MX record does not resolve to a valid IP address` and `Webhook processed`),
  the `mailtrap:test` output, and the inbox UI labels, notices and test mail. Code
  comments and the config file were translated too. Numbers in the inbox stats use
  PHP's default `number_format()` (`1,234` instead of `1.234`).
- `markAsValid()` records status code `200`.

### Deprecated

- `MailtrapService` and the `app('mailtrap')` alias. They call a Mailtrap validation
  endpoint that does not exist, and nothing in the package uses them. Use
  `EmailValidation::validateEmail()`. Removed in 2.0.
- The config keys `api.base_url`, `validation.retry_attempts`, `rate_limiting.*` and
  `development.*`. The package never read them. Removed in 2.0.

## [1.1.0] - 2026-09-10

### Security

- **Webhook signature verification is now enforced.** `POST /api/webhooks/mailtrap`
  accepted any request: `webhook.verify_signature` and `webhook.secret` were
  configured but never read, so anyone who knew the URL could post `bounce` events
  and have arbitrary addresses marked invalid — blocking real mail. Requests are now
  checked against the HMAC-SHA256 in Mailtrap's `Mailtrap-Signature` header by a
  `VerifyMailtrapWebhookSignature` middleware on the route.

### Fixed

- **Config is merged in `register()`** instead of halfway through `boot()`, so
  settings are available to everything that boots afterwards. Webhook route
  registration previously read config that had not been merged yet.

### Changed

- **`webhook.enabled` is honoured**: when false, the webhook route is no longer
  registered at all instead of being registered and left reachable.
- **`validation.enabled` is honoured**: when false, the MX lookups that run
  synchronously during every send are skipped entirely.
- **`validation.block_invalid` is honoured**: when false, a flagged address is
  delivered to instead of aborting the send, and is logged as a normal send with the
  reason kept in `error_message` rather than filed as a 550 failure.
- **`validation.cache_duration` is honoured** for locally derived `blocked` records,
  which are re-checked once it has passed. A transient DNS failure used to block an
  address permanently. Statuses that come from Mailtrap events (`valid`, `invalid`)
  are never re-derived from an MX lookup, since a lookup cannot reproduce a bounce.
- **`logging.enabled`, `logging.log_successful` and `logging.log_failed` are
  honoured**; all three were previously ignored and every send was logged.

### Upgrading

`MAILTRAP_WEBHOOK_SECRET` is now required for the webhook to accept anything. Copy
the 32-character hex secret from the webhook detail panel in Mailtrap. If you do not
use Mailtrap webhooks, set `MAILTRAP_WEBHOOK_ENABLED=false`. To keep accepting
unsigned calls, set `MAILTRAP_WEBHOOK_VERIFY_SIGNATURE=false` — but note the endpoint
writes to `email_validations` and `mail_logs`.

Apps that relied on the ignored config flags will see behaviour change to match what
those flags say. Check `MAILTRAP_VALIDATION_ENABLED`, `MAILTRAP_BLOCK_INVALID_EMAILS`
and the `MAILTRAP_LOG_*` values before upgrading.

## [1.0.15] - 2026-09-09

### Fixed

- **Migrations now run on every database driver**: the
  `make_message_id_nullable_in_mail_logs_table` migration probed for indexes with
  raw `SHOW INDEX FROM mail_logs`, which is MySQL-only syntax. Installing the
  package in an application that runs its test suite on SQLite — the Laravel
  default — made every migration fail with
  `SQLSTATE[HY000]: General error: 1 near "SHOW"`, taking the host
  application's whole suite down with it. Index detection now goes through
  `Schema::getIndexes()`, which works on all drivers Laravel supports.

### Changed

- **Dev dependencies widened** to `orchestra/testbench ^9.0|^10.0|^11.0` and
  `phpunit/phpunit ^11.0|^12.0|^13.0`. The old constraints only resolved against
  Laravel 10, so the package's own test suite could not be installed at all on
  the Laravel 11–13 versions the package supports. This affects contributors
  only, not applications.
- The test suite now runs migration `000003` as well; it was silently skipped
  before, which is why the broken `SHOW INDEX` was never caught.

## [1.0.14] - 2026-06-25

### Fixed

- **Multi-recipient mail logging**: a message sent to several recipients in one
  send (`Mail::to([a, b])`) left every recipient except the last stuck on
  "pending" in the inbox. The listener generated a fresh `X-Message-ID` per
  recipient, but the header kept only the last one, so the single `MessageSent`
  event matched just one log row. The `X-Message-ID` is now generated once per
  message and shared across all recipient log rows, and `MessageSent` marks every
  pending row for that id as sent.

## [1.0.13] - 2026-06-24

### Added

- **Inbox UI**: a Mailtrap-style Livewire/Flux page to inspect outgoing mail.
  - Paginated list with status badges (sent, pending, failed, blocked), search by
    recipient/sender/subject and a status filter
  - Per-message detail view, a **Send test mail** action, delete a single log and
    cleanup of logs older than `logging.cleanup_after_days`
  - New `ui` config section with `MAILTRAP_UI_*` env vars (enabled, route,
    middleware, layout, per_page)
  - Views are publishable via `php artisan vendor:publish --tag=mailtrap-views`
  - Registration is skipped automatically when Livewire is absent or
    `MAILTRAP_UI_ENABLED=false`, so the rest of the package keeps working
- **`mailtrap:test` Artisan command**: a CLI health check that sends a test mail,
  reads back the corresponding mail log and prints a summary. Returns exit code `0`
  on success and `1` on failure (CI-friendly); `--mailer` selects the transport.
- **Documentation**: README sections for the Inbox UI, the health-check command,
  Tailwind/Flux setup, and a "Mail Transport vs. Mailtrap" section explaining that
  validation and logging are mailer-independent while delivery feedback
  (delivery/open/click/bounce/spam/reject) is reported only by Mailtrap.

### Changed

- Code-style sweep across the package (Laravel Pint): negation spacing, trailing
  commas and doc-block formatting. No behavioural changes.

## [1.0.12] - 2026-05-13

### Changed

- Laravel `Log` facade output is now disabled by default. Webhook and API
  events no longer write to the host app's log channels unless explicitly
  enabled via `manta_mailtrap.logging.log_to_laravel` (env
  `MAILTRAP_LOG_TO_LARAVEL=true`). `MailLog` database logging is unaffected.

## [1.0.11] - 2026-03-21

### Changed

- Added official support for Laravel 13.

## [1.0.10] - 2026-02-20

### Added

- **Pest testing framework**: Added Pest PHP for modern, expressive testing
  - Added `pestphp/pest` and `pestphp/pest-plugin-laravel` as dev dependencies
  - Added `composer test` script to run Pest tests
  - Created `tests/Pest.php` configuration file

- **Webhook controller tests**: Added test coverage for MailLog webhook upsert logic
  - Test for creating MailLog when message_id doesn't exist
  - Test for updating existing MailLog without creating duplicates

- **MailLog upsert functionality**: Webhook controller now creates MailLog records when they don't exist
  - Added `upsertMailLogFromWebhook()` method to handle upsert logic
  - Webhook events now capture `sending_domain_name` for new records
  - New records include `category` in subject and `response` as error message

### Changed

- **Base controller**: Changed from `App\Http\Controllers\Controller` to `Illuminate\Routing\Controller` for better package compatibility
- **PHPUnit**: Updated from `^10.0` to `^11.0`
- **TestCase improvements**: Added SQLite in-memory database configuration and automatic migration loading

## [1.0.9] - 2026-02-17

### Fixed

- **Migration robustness**: Check if unique constraint exists before trying to drop it
  - Prevents migration failure when `mail_logs_message_id_unique` index doesn't exist
  - Uses native MySQL `SHOW INDEX` query for compatibility with Laravel 11+

## [1.0.8] - 2026-02-17

### Fixed

- **Laravel 11+ compatibility**: Removed Doctrine DBAL dependency from migration
  - `getDoctrineSchemaManager()` was removed in Laravel 11
  - Now uses native MySQL `SHOW INDEX` query to check for existing indexes

## [1.0.7] - 2026-02-17

### Fixed

- **MailLog message_id now nullable**: Fixed database error when logging blocked emails
  - `message_id` field is now nullable to support logging blocked emails that were never sent
  - `sender` field is now nullable for blocked email scenarios
  - Replaced unique constraint with index on `message_id` for better flexibility
  - Added migration `2024_01_01_000003_make_message_id_nullable_in_mail_logs_table.php` for existing databases

### Migration Required

Run `php artisan migrate` to apply the schema changes for existing installations.

## [1.0.6] - 2026-02-17

### Added

- **Error Tracking for MailLog**: Added new columns to track error details and source location
  - `error_message`: Stores the error message when email sending fails
  - `source_file`: Stores the file path where the MailLog entry was created
  - `source_line`: Stores the line number where the MailLog entry was created

- **MailLog::createWithSource() Helper Method**: New static method that automatically captures caller location
  - Uses `debug_backtrace()` to capture source file and line
  - Stores relative paths from `base_path()` for cleaner logs
  - Drop-in replacement for `MailLog::create()` with automatic source tracking

- **EmailValidation::getBlockReason() Method**: New method to retrieve the reason why an email is blocked
  - Returns the `reason` field from blocked email records
  - Used by MailServiceProvider to provide detailed error messages

### Changed

- **MailServiceProvider**: Enhanced blocked email handling
  - Now uses `createWithSource()` for automatic source tracking
  - Includes `error_message` with detailed block reason
  - Exception message now includes the block reason for better debugging

### Migration

- Added `2024_01_01_000002_add_error_tracking_to_mail_logs_table.php`
  - Adds `error_message`, `source_file`, and `source_line` columns
  - Safe migration with column existence checks

## [1.0.4] - 2025-09-30

### Added

- **Mailtrap Response Code Support**: Enhanced webhook processing to handle `response_code` from Mailtrap events
  - `MailtrapWebhookController` now extracts and processes `response_code` from webhook events
  - `EmailValidation` model stores response codes in `status_code` field
  - `MailLog` model gets updated with response codes based on `message_id`

### Improved

- **Enhanced Webhook Processing**:
  - Bounce events now use actual Mailtrap `response_code` (e.g., 555, 550) instead of hardcoded values
  - Response text from Mailtrap is used as primary reason (fallback to existing logic)
  - Delivery events automatically get status_code 200 (Mailtrap doesn't send response_code for successful deliveries)
  - Open/Click events get status_code 200 confirming successful delivery
  - Spam/Reject events use Mailtrap response_code or fallback to default values

- **Enhanced MailServiceProvider**:
  - Improved email validation flow with better performance
  - Enhanced blocked email handling with proper MailLog creation
  - Better code organization with early variable initialization
  - Removed redundant `isBlocked()` check in validation flow
  - Added comprehensive MailLog entry for blocked emails with status_code 550

- **Dual Model Updates**:
  - `EmailValidation` tracks email address validity with response codes
  - `MailLog` tracks individual message status with response codes
  - Both models maintain consistent status_code information
  - Enhanced logging includes response_code and response text for debugging

### Changed

- **Simplified EmailValidation API**: Removed `markAsValidWithCode()` method in favor of using existing `markAsValid()` method
- **Streamlined validation logic**: Removed redundant blocked email check in MailServiceProvider

### Technical Details

- Added `$responseCode` and `$response` extraction from Mailtrap webhook events
- All event types (delivery, bounce, spam, reject, open, click) now handle response codes appropriately
- MailLog updates are conditional on `message_id` availability for safety
- Backwards compatible with existing webhooks that don't include response_code
- Improved performance by reducing duplicate database queries in email validation flow

### Files Changed

- `src/Http/Controllers/MailtrapWebhookController.php`: Enhanced webhook processing
- `src/Models/EmailValidation.php`: Simplified API by removing `markAsValidWithCode()` method
- `src/Providers/MailServiceProvider.php`: Enhanced email validation and logging flow
- `tests/WebhookResponseCodeExample.php`: Added example of new functionality

## [1.0.3] - 2025-09-30

### Note

- This version was tagged in git but corresponds to the changes now documented in v1.0.4
- See v1.0.4 for the actual feature additions and improvements

## [1.0.2] - 2025-09-30

### Improved

- Enhanced route registration in `MailtrapServiceProvider`
  - Added proper API prefix (`/api`) to all package routes
  - Added API middleware group for better request handling
  - Improved code formatting and consistency

### Technical Details

- Routes are now properly prefixed with `/api` and use the `api` middleware group
- This ensures better integration with Laravel's API routing conventions
- Improved string concatenation formatting throughout the service provider

## [1.0.1] - 2025-09-25

### Changed

- **BREAKING**: `EmailValidation::validateEmail()` now returns `?string` instead of `bool`
  - Returns `null` when email is valid
  - Returns error message string when email is invalid or blocked
  - This provides more detailed feedback about validation failures

### Improved

- Enhanced email validation with more detailed error messages
- Better MX record validation with IP address verification
- Improved caching mechanism for existing validations
- More comprehensive DNS validation checks

### Fixed

- Fixed issue where validation would not check existing records first
- Improved error handling for DNS lookup failures
- Better handling of MX records that don't resolve to valid IP addresses

### Documentation

- Updated all documentation to reflect new `validateEmail()` return type
- Updated API reference with correct method signatures
- Updated all code examples in documentation
- Updated README.md with correct usage examples
- Updated best practices guide with new patterns

### Technical Details

- The `validateEmail()` method now performs these checks in order:
  1. Check existing validation record in database
  2. Basic email format validation
  3. MX record existence check
  4. MX record IP address validation
- All validation results are automatically stored in database for caching
- Bulk validation methods remain unchanged and fully compatible

### Migration Guide

If you're upgrading from version 1.0.0, update your code as follows:

**Before (v1.0.0):**

```php
$isValid = EmailValidation::validateEmail('user@example.com');
if ($isValid) {
    // Email is valid
} else {
    // Email is invalid
}
```

**After (v1.0.1):**

```php
$errorMessage = EmailValidation::validateEmail('user@example.com');
if ($errorMessage === null) {
    // Email is valid
} else {
    // Email is invalid: $errorMessage contains the reason
    echo "Validation failed: " . $errorMessage;
}
```

## [1.0.0] - 2025-09-24

### Added

- Initial release of the Darvis Mailtrap package
- EmailValidation model with comprehensive validation features
- Bulk validation capabilities
- Laravel Collections integration
- Database caching of validation results
- Mailtrap API integration
- Webhook support for Mailtrap callbacks
- Mail logging functionality
- Rate limiting for API endpoints
- Comprehensive documentation
- Migration files for database setup
- Configuration file with extensive options
- Service provider with automatic registration
- Event listeners for automatic email validation

### Features

- **Email Validation**
  - Format validation using PHP's built-in filters
  - MX record verification
  - Domain validation
  - Bulk validation for multiple emails
  - Status tracking (valid, blocked, invalid)
  - Automatic caching in database

- **Laravel Integration**
  - Automatic package discovery
  - Eloquent model for email validations
  - Laravel Collections support for result filtering
  - Event-driven validation
  - Artisan commands for maintenance

- **Mailtrap Integration**
  - API client for Mailtrap services
  - Webhook endpoint for callbacks
  - Mail logging and tracking
  - Rate limiting protection

- **Documentation**
  - Comprehensive API reference
  - Practical examples and use cases
  - Best practices guide
  - Laravel Collections filtering guide
  - Performance optimization tips
