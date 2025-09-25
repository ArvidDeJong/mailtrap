# Darvis Mailtrap Package

A powerful Laravel package for Mailtrap integration with comprehensive email validation functionality.

[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)

## 🚀 Quick Start

```php
use Darvis\Mailtrap\Models\EmailValidation;

// Validate a single email address
$isValid = EmailValidation::validateEmail('user@example.com');

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
- ✅ **MX Record Verification** - Verifies domain mail servers
- ✅ **IP Validation** - Checks if MX records point to valid IPs
- ✅ **Bulk Validation** - Efficient validation of multiple email addresses
- ✅ **Laravel Collections** - Powerful filtering and data manipulation
- ✅ **Status Tracking** - Maintains validation history

### 🔧 **Integration & Performance**
- ✅ **Database Caching** - Fast lookups of previously validated emails
- ✅ **Automatic Registration** - Laravel package discovery
- ✅ **Event Listeners** - Automatic validation via Laravel mail events
- ✅ **Rate Limiting** - Built-in API rate limiting
- ✅ **Webhook Support** - Mailtrap callback support

### 📊 **Monitoring & Logging**
- ✅ **Mail Logging** - Automatic logging of outgoing emails
- ✅ **Detailed Tracking** - Comprehensive validation reporting
- ✅ **Status Codes** - HTTP-like status codes for categorization
- ✅ **Configurable** - Extensive configuration options

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
- **Mail Logging** - Log settings for outgoing emails
- **Webhook** - Webhook configuration and signature verification
- **Rate Limiting** - API rate limiting settings

**Environment Variables:**
```env
MAILTRAP_API_TOKEN=your_api_token_here
MAILTRAP_VALIDATION_ENABLED=true
MAILTRAP_LOGGING_ENABLED=true
MAILTRAP_WEBHOOK_ENABLED=true
MAILTRAP_WEBHOOK_SECRET=your_webhook_secret
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

## 🔗 API Endpoints

### Webhook

The package automatically includes a webhook endpoint:

- **Endpoint**: `POST /api/webhooks/mailtrap`
- **Route name**: `webhooks.mailtrap`

The webhook is automatically registered and accepts external calls from Mailtrap.

## 🛠️ Development

This package is actively developed with focus on:
- Performance optimization
- Comprehensive email validation
- Automatic blocking of invalid emails
- Comprehensive mail logging

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
