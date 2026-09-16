# Documentation

Documentation of the `darvis/mailtrap` package. For a short overview, see the
[main README](../README.md).

## Getting started

| Topic | Description |
| --- | --- |
| [Installation & Configuration](./installation.md) | Requirements, Mailtrap tokens, the setup wizard, config file and environment variables |

## Guides

| Topic | Description |
| --- | --- |
| [Sending, Blocking & Mail Logs](./sending-and-logging.md) | What happens on send, validation states, linking logs to models, pruning, events, other mailers |
| [Webhook](./webhook.md) | Delivery events, signature verification and creating the webhook with `mailtrap:webhook` |
| [Inbox UI & Health Check](./inbox.md) | The Livewire/Flux inbox and the `mailtrap:test` command |

## Email validation

| Topic | Description |
| --- | --- |
| [Overview](./email-validation.md) | Introduction to the `EmailValidation` model |
| [Basic Usage](./email-validation/basic-usage.md) | Validating a single address and the database structure |
| [Bulk Validation](./email-validation/bulk-validation.md) | Validating many addresses at once |
| [Laravel Collections](./email-validation/laravel-collections.md) | Filtering and transforming bulk results |
| [API Reference](./email-validation/api-reference.md) | All methods and return values |
| [Practical Examples](./email-validation/examples.md) | Newsletter, registration, imports and list cleanup |
| [Best Practices](./email-validation/best-practices.md) | Performance, error handling and testing |

## Quick example

```php
use Darvis\Mailtrap\Models\EmailValidation;

// null when valid, otherwise the reason
$error = EmailValidation::validateEmail('user@example.com');

$result = EmailValidation::bulkValidationStatus([
    'user1@test.com',
    'user2@example.com',
]);

$result['valid'];       // addresses with status "valid"
$result['invalid'];     // "invalid" and "blocked"
$result['not_exists'];  // no verdict yet
```
