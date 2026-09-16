# Darvis Mailtrap Package

A powerful Laravel package for Mailtrap integration: email validation, automatic logging of outgoing mail, a Mailtrap-style inbox UI and a CLI health-check command.

[![Latest Version](https://img.shields.io/packagist/v/darvis/mailtrap.svg)](https://packagist.org/packages/darvis/mailtrap)
[![Tests](https://github.com/ArvidDeJong/mailtrap/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/mailtrap/actions/workflows/tests.yml)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)

## 🚀 Quick Start

```php
use Darvis\Mailtrap\Models\EmailValidation;

// Validate a single email address (returns null if valid, error message if invalid)
$errorMessage = EmailValidation::validateEmail('user@example.com');
$isValid = $errorMessage === null;

// Bulk validation of multiple email addresses
$emails = ['user1@test.com', 'user2@example.com', 'user3@invalid.domain'];
$result = EmailValidation::bulkValidationStatus($emails);

echo "Valid emails: " . $result['valid'];
echo "Invalid emails: " . $result['invalid'];
```

## 📚 Documentation

For complete documentation and extensive examples:

### 📖 **[Go to Documentation →](./docs/)**

**Specific topics:**
- **[EmailValidation Model](./docs/email-validation.md)** - Complete guide for email validation
- **[Laravel Collections](./docs/email-validation/laravel-collections.md)** - Advanced filtering and data manipulation
- **[API Reference](./docs/email-validation/api-reference.md)** - All available methods
- **[Practical Examples](./docs/email-validation/examples.md)** - Real-world use cases
- **[Best Practices](./docs/email-validation/best-practices.md)** - Performance and error handling

## ✨ Features

### 🎯 **Email Validation**
- ✅ **Format Validation** - Checks basic email format
- ✅ **MX Record Verification** - Verifies domain mail servers (runs synchronously
  during the send; disable with `MAILTRAP_VALIDATION_ENABLED=false`)
- ✅ **IP Validation** - Checks if MX records point to valid IPs
- ✅ **Bulk Validation** - Efficient validation of multiple email addresses
- ✅ **Laravel Collections** - Powerful filtering and data manipulation
- ✅ **Status Tracking** - Maintains validation history

### 🔧 **Integration & Performance**
- ✅ **Database Caching** - Fast lookups of previously validated emails
- ✅ **Automatic Registration** - Laravel package discovery
- ✅ **Event Listeners** - Automatic validation via Laravel mail events
- ✅ **Mailer-independent** - Logging & validation work with any transport (Mailtrap, Microsoft Graph, SES, …)
- ✅ **Webhook Support** - Mailtrap callback support

### 📊 **Monitoring & Logging**
- ✅ **Mail Logging** - Automatic logging of outgoing emails
- ✅ **Detailed Tracking** - Comprehensive validation reporting
- ✅ **Status Codes** - HTTP-like status codes for categorization
- ✅ **Configurable** - Extensive configuration options

### 📬 **Inbox UI & Tooling**
- ✅ **Mailtrap-style Inbox** - Livewire/Flux page to inspect every outgoing email
- ✅ **Search & Filters** - Filter by status (sent, pending, failed, blocked) and search recipient/sender/subject
- ✅ **Send Test Mail** - Trigger a test email straight from the inbox
- ✅ **Delete & Cleanup** - Remove a single log or purge logs older than the retention period
- ✅ **`mailtrap:test` Command** - CLI health-check with CI-friendly exit codes

## Author

**Arvid de Jong**  
Email: [info@arvid.nl](mailto:info@arvid.nl)

## 📦 Installation

Install the package via Composer:

```bash
composer require darvis/mailtrap
php artisan mailtrap:install
```

### Mailtrap credentials: two tokens and a secret

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

### Setup wizard

`mailtrap:install` is a setup wizard. It walks you through eight steps and explains each
one before it asks anything:

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

Without a terminal, e.g. in a deploy script, it asks nothing and only does what the flags say:

```bash
php artisan mailtrap:install --webhook --no-interaction          # uses MAILTRAP_API_TOKEN
php artisan mailtrap:install --without-webhook --no-interaction  # not sending through Mailtrap
```

The wizard is safe to re-run on a site that is already configured: it preselects the
current settings, lets you keep or replace the API token, and offers to replace the
existing webhook (Mailtrap never shows an old secret again). It never overwrites a
published `config/manta_mailtrap.php`, but lists the keys that file lacks compared to the
installed version. Non-interactive:

```bash
php artisan mailtrap:install --webhook --replace --token=new_token --no-interaction
```

## ⚙️ Configuration

The package is automatically registered via Laravel's package discovery.

### Database Setup

Migrations are automatically loaded. To publish migrations to your application:

```bash
php artisan vendor:publish --tag=mailtrap-migrations
php artisan migrate
```

Or simply run `php artisan migrate` - migrations are loaded automatically.

### Configuration

Publish the config file for custom settings:

```bash
php artisan vendor:publish --tag=mailtrap-config
```

This creates a `config/manta_mailtrap.php` file with configuration for:

- **API Settings** - Account API token (for creating the webhook) and API URLs
- **Email Validation** - Validation settings and caching
- **Mail Logging** - Log settings for outgoing emails (incl. `cleanup_after_days` retention)
- **Webhook** - Webhook configuration and signature verification
- **Rate Limiting** - API rate limiting settings
- **Inbox UI** - Route, middleware, layout and page size for the inbox

**Environment Variables:**
```env
MAILTRAP_API_TOKEN=your_account_api_token   # account token, only for creating the webhook
MAILTRAP_VALIDATION_ENABLED=true
MAILTRAP_BLOCK_INVALID_EMAILS=true
MAILTRAP_VALIDATION_CACHE_DURATION=3600
MAILTRAP_LOGGING_ENABLED=true
MAILTRAP_LOG_SUCCESSFUL=true
MAILTRAP_LOG_FAILED=true
MAILTRAP_CLEANUP_AFTER_DAYS=30
MAILTRAP_WEBHOOK_ENABLED=true
MAILTRAP_WEBHOOK_SECRET=your_webhook_secret # not a token: the webhook's signing secret
MAILTRAP_WEBHOOK_VERIFY_SIGNATURE=true

# Inbox UI
MAILTRAP_UI_ENABLED=true
MAILTRAP_UI_ROUTE=mailtrap
MAILTRAP_UI_MIDDLEWARE=web,auth
MAILTRAP_UI_LAYOUT=components.layouts.app
MAILTRAP_UI_PER_PAGE=25
```

## 💡 Usage Examples

### Basic Usage

```php
use Darvis\Mailtrap\Models\EmailValidation;

$error = EmailValidation::validateEmail('test@example.com'); // null when valid

// Stop all mail to one address. Other addresses on the domain are not affected.
EmailValidation::markAsBlocked('complainer@example.com', 'Spam complaint');
```

> **Deprecated:** `MailtrapService` and `app('mailtrap')` call a Mailtrap validation
> endpoint that does not exist, so `validateEmail()` there cannot succeed. They are
> removed in 2.0. Use `EmailValidation::validateEmail()` instead.

### Blocking

Before a mail is sent, every To, Cc and Bcc address is checked. A `blocked` address
aborts the whole send with a `Symfony\Component\Mailer\Exception\TransportException`
(set `MAILTRAP_BLOCK_INVALID_EMAILS=false` to send anyway and only log the reason).
Blocking is per address: a typo or a manual block never stops mail to the rest of the
domain. `invalid` (from bounce, spam and reject events) is recorded but does not block.

### Mail logs

Tag a Mailable with headers to link its log to a model:

```php
public function headers(): \Illuminate\Mail\Mailables\Headers
{
    return new \Illuminate\Mail\Mailables\Headers(text: [
        'X-Mail-Type' => 'invoice',
        'X-Mail-Model' => Invoice::class,
        'X-Mail-Model-ID' => (string) $this->invoice->id,
    ]);
}
```

```php
use Darvis\Mailtrap\Models\MailLog;

MailLog::forModel(Invoice::class, $invoice->id)->failed()->get();
MailLog::pending()->count();   // also: successful(), blocked()
$log->related;                 // the Invoice
```

Logs older than `MAILTRAP_CLEANUP_AFTER_DAYS` are removed by Laravel's pruning. Package
models are not discovered automatically, so schedule it explicitly:

```php
// routes/console.php
Schedule::command('model:prune', ['--model' => [\Darvis\Mailtrap\Models\MailLog::class]])->daily();
```

### Events

Listen to these events to react in your own application:

| Event | When | Properties |
| --- | --- | --- |
| `Darvis\Mailtrap\Events\MailBlocked` | Just before a send to a blocked address is aborted | `email`, `reason`, `message`, `mailLog` |
| `Darvis\Mailtrap\Events\MailtrapEventReceived` | For every signed webhook event, also the ones the package ignores | `type`, `email`, `payload`, `mailLog` |

```php
use Darvis\Mailtrap\Events\MailtrapEventReceived;
use Illuminate\Support\Facades\Event;

Event::listen(function (MailtrapEventReceived $event): void {
    if ($event->type === 'unsubscribe') {
        User::where('email', $event->email)->update(['newsletter' => false]);
    }
});
```

A listener that throws is logged (with `MAILTRAP_LOG_TO_LARAVEL=true`) and does not stop the rest of the webhook batch.

### Laravel Collections Filtering

```php
use Darvis\Mailtrap\Models\EmailValidation;

$emails = ['valid@test.com', 'invalid@spam.com', 'unknown@new.com'];
$result = EmailValidation::bulkValidationStatus($emails);

// Filter invalid emails with Laravel Collections
$invalidEmails = collect($result['details'])
    ->filter(fn($details) => $details['status'] !== 'valid')
    ->keys()
    ->toArray();

// Group by status
$grouped = collect($result['details'])
    ->groupBy('status')
    ->map(fn($group) => $group->keys()->toArray());
```

**📖 [More examples in the documentation →](./docs/email-validation/examples.md)**

## 📬 Inbox UI

A Mailtrap-style inbox to inspect outgoing mail, built with Livewire and Flux UI.

> **Requirements:** the host application must have `livewire/livewire` (^3.7.4 or ^4.0) and `livewire/flux` (^2.11, the free edition is enough) installed. When Livewire is absent (or `MAILTRAP_UI_ENABLED=false`) the UI registration is skipped automatically — the rest of the package keeps working.

Once enabled, the inbox lives at the configured route (default `/mailtrap`) and offers:

- A paginated list of all logged mail with status badges (sent, pending, failed, blocked)
- Search by recipient, sender or subject, and filter by status
- A detail view per message (message id, status, error, source file/line, linked model)
- A **Send test mail** action straight from the inbox
- **Delete** a single log, or **Cleanup** logs older than `logging.cleanup_after_days`

### Configuration

All UI settings are driven by environment variables (see the `ui` section in `config/manta_mailtrap.php`):

```env
MAILTRAP_UI_ENABLED=true
MAILTRAP_UI_ROUTE=mailtrap
MAILTRAP_UI_MIDDLEWARE=web,auth        # comma-separated middleware stack
MAILTRAP_UI_LAYOUT=components.layouts.app
MAILTRAP_UI_PER_PAGE=25
```

> Protect the route with appropriate middleware — the inbox exposes recipients, subjects and error details. For example `MAILTRAP_UI_MIDDLEWARE=web,auth` (or a custom staff/admin middleware).

### Tailwind / Flux

Add the package views to your Tailwind sources so its utility classes are compiled (Tailwind v4 example in `resources/css/app.css`):

```css
@source '../../vendor/darvis/mailtrap/resources/views/**/*.blade.php';
```

Optionally publish the views to override them in your application:

```bash
php artisan vendor:publish --tag=mailtrap-views
```

## 📮 Mail Transport vs. Mailtrap

This package hooks into Laravel's `MessageSending` / `MessageSent` events, so it works **independently of the mailer (transport) you send through**. Sending via Mailtrap's SMTP, Microsoft Graph, Amazon SES or any other Laravel mailer all flow through the same pipeline — pre-send validation and `MailLog` logging happen for every transport.

The webhook flow is the one exception: delivery, open, click, bounce, spam and reject events are reported **only by Mailtrap**. If you send through another transport (e.g. `microsoft-graph`), local logging and validation still work, but there is no post-delivery feedback to confirm or invalidate addresses.

| Capability | Any mailer | Mailtrap only |
| --- | :---: | :---: |
| Pre-send validation (format / MX / blocklist) | ✅ | |
| Outgoing mail logging (`MessageSending` / `MessageSent`) | ✅ | |
| Inbox UI & `mailtrap:test` health check | ✅ | |
| Delivery / open / click confirmation → `markAsValid` | | ✅ (webhook) |
| Bounce / spam / reject → `markAsInvalid` | | ✅ (webhook) |

> **In practice:** you can route production mail through Microsoft Graph and still get full logging and the inbox UI. To also keep the validation feedback loop — auto-confirming good addresses and flagging bounces — the delivery events must come from Mailtrap.

## 🩺 Health Check Command

Send a test email and report the result. The command returns exit code `0` on success and `1` on failure, which makes it suitable for CI pipelines and uptime monitoring.

```bash
php artisan mailtrap:test you@example.com

# Use a specific mailer instead of the default
php artisan mailtrap:test you@example.com --mailer=microsoft-graph
```

It sends the mail, reads back the corresponding mail log and prints a summary table (message id, sender, recipient, subject, status, error message). A recorded non-success status (e.g. a blocked recipient) is also treated as a failure.

## 🔗 API Endpoints

### Webhook

The package registers a webhook endpoint:

- **Endpoint**: `POST /api/webhooks/mailtrap`
- **Route name**: `webhooks.mailtrap`

Set `MAILTRAP_WEBHOOK_ENABLED=false` to not register the route at all — recommended
when you send through another transport, since delivery events only come from Mailtrap.

Each outgoing mail carries its log id to Mailtrap as the custom variable `x_message_id`
(header `X-MT-Custom-Variables`, merged with any variables you set yourself). Mailtrap
returns it in every webhook event, so the event updates the right log row.

#### Signature verification

Mailtrap signs every webhook with an HMAC-SHA256 of the raw request body, hex encoded,
in the `Mailtrap-Signature` header. The package verifies it and **fails closed**: with
`verify_signature` enabled and no secret configured, every call is rejected with `403`.

```env
MAILTRAP_WEBHOOK_SECRET=your_32_char_hex_signing_secret
```

#### Generating the signing secret

Mailtrap generates the secret itself and only returns it once, when the webhook is
created. `mailtrap:webhook` creates the webhook through the Mailtrap API and writes the
secret to `.env`:

```bash
php artisan mailtrap:webhook                       # URL: APP_URL/api/webhooks/mailtrap
php artisan mailtrap:webhook https://example.com/api/webhooks/mailtrap --show
```

| Option | Effect |
| --- | --- |
| `--token=` | API token with admin access; defaults to `MAILTRAP_API_TOKEN` |
| `--stream=` | `transactional` (default) or `bulk` |
| `--domain-id=` | Only events for one sending domain |
| `--replace` | Delete an existing webhook for the same URL first — its secret cannot be read back |
| `--show` | Print the secret instead of writing it, for when you run the command on another machine |

The webhook subscribes to `delivery`, `open`, `click`, `bounce`, `spam_complaint` and
`reject` with the JSON payload format. When the configuration or routes are cached,
both caches are rebuilt so the new secret is live immediately.

Alternatively, copy the secret from the webhook detail panel in Mailtrap. To accept unsigned calls
anyway — not recommended, the endpoint writes to `email_validations` and `mail_logs`:

```env
MAILTRAP_WEBHOOK_VERIFY_SIGNATURE=false
```

> **Why this matters:** a `bounce` event marks an address invalid. Left unverified,
> anyone who knows the URL can post events for arbitrary addresses.

## 🤖 Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline
(`resources/boost/guidelines/core.blade.php`) and a `mailtrap-development` skill. Run
`php artisan boost:install`, or `php artisan boost:update --discover` in a project that
already uses Boost, to give your AI agent the package's conventions.

## 🛠️ Development

```bash
composer test      # Pest
composer lint      # Pint, check only (composer format to fix)
composer analyse   # Larastan
```

GitHub Actions runs the tests on PHP 8.2–8.4 against Laravel 11, 12 and 13, with both the lowest and the latest allowed dependencies.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
