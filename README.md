# Darvis Mailtrap Package

A powerful Laravel package for Mailtrap integration: email validation, automatic logging of outgoing mail, a Mailtrap-style inbox UI and a CLI health-check command.

[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)

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
- ✅ **Rate Limiting** - Built-in API rate limiting
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

- **API Settings** - Mailtrap API token and base URL
- **Email Validation** - Validation settings and caching
- **Mail Logging** - Log settings for outgoing emails (incl. `cleanup_after_days` retention)
- **Webhook** - Webhook configuration and signature verification
- **Rate Limiting** - API rate limiting settings
- **Inbox UI** - Route, middleware, layout and page size for the inbox

**Environment Variables:**
```env
MAILTRAP_API_TOKEN=your_api_token_here
MAILTRAP_VALIDATION_ENABLED=true
MAILTRAP_BLOCK_INVALID_EMAILS=true
MAILTRAP_VALIDATION_CACHE_DURATION=3600
MAILTRAP_LOGGING_ENABLED=true
MAILTRAP_LOG_SUCCESSFUL=true
MAILTRAP_LOG_FAILED=true
MAILTRAP_CLEANUP_AFTER_DAYS=30
MAILTRAP_WEBHOOK_ENABLED=true
MAILTRAP_WEBHOOK_SECRET=your_webhook_secret
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
// Via the Mailtrap service
$mailtrap = app(Darvis\Mailtrap\Services\MailtrapService::class);

// Or via the alias
$mailtrap = app('mailtrap');

// Email validation
$isValid = $mailtrap->validateEmail('test@example.com');
```

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

> **Requirements:** the host application must have `livewire/livewire` and `livewire/flux` installed. When Livewire is absent (or `MAILTRAP_UI_ENABLED=false`) the UI registration is skipped automatically — the rest of the package keeps working.

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

#### Signature verification

Mailtrap signs every webhook with an HMAC-SHA256 of the raw request body, hex encoded,
in the `Mailtrap-Signature` header. The package verifies it and **fails closed**: with
`verify_signature` enabled and no secret configured, every call is rejected with `403`.

```env
MAILTRAP_WEBHOOK_SECRET=your_32_char_hex_signing_secret
```

Copy the secret from the webhook detail panel in Mailtrap. To accept unsigned calls
anyway — not recommended, the endpoint writes to `email_validations` and `mail_logs`:

```env
MAILTRAP_WEBHOOK_VERIFY_SIGNATURE=false
```

> **Why this matters:** a `bounce` event marks an address invalid. Left unverified,
> anyone who knows the URL can post events for arbitrary addresses.

## 🛠️ Development

This package is actively developed with focus on:
- Performance optimization
- Comprehensive email validation
- Automatic blocking of invalid emails
- Comprehensive mail logging
- Inbox UI and CLI tooling for inspecting and testing outgoing mail

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
