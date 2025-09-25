# Basic Usage & Database Structure

## Overview

The EmailValidation model performs various checks on email addresses:

1. **Format validation** - Checks if the email address has a valid format
2. **MX record verification** - Verifies if the domain has valid mail servers
3. **IP validation** - Checks if MX records point to valid IP addresses

## Database Structure

The model uses the `email_validations` table with the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `id` | bigint | Primary key |
| `email` | string | The complete email address |
| `domain` | string | The domain part of the email address |
| `status` | string | Status: 'valid', 'blocked', or 'invalid' |
| `reason` | string | Reason for the status |
| `status_code` | integer | HTTP-like status code |
| `last_checked_at` | timestamp | When the email address was last checked |
| `created_at` | timestamp | Creation date |
| `updated_at` | timestamp | Last modification |

## Basic Functionality

### Individual Validation

```php
use Darvis\Mailtrap\Models\EmailValidation;

// Validate an email address (returns null if valid, error message if invalid)
$errorMessage = EmailValidation::validateEmail('user@example.com');

// Check if an email address is blocked
$isBlocked = EmailValidation::isBlocked('user@example.com');

// Check if an email address is valid
$isValid = EmailValidation::isValid('user@example.com');
```

### Manual Status Updates

```php
// Mark as valid
EmailValidation::markAsValid('user@example.com');

// Mark as invalid
EmailValidation::markAsInvalid('user@example.com', 'Domain does not exist');

// Mark as blocked
EmailValidation::markAsBlocked('user@example.com', 'Spam domain', '403');
```

## Quick Examples

### Simple Validation Check

```php
$email = 'user@example.com';

$errorMessage = EmailValidation::validateEmail($email);
if ($errorMessage === null) {
    echo "Email is valid and can be used";
} else {
    echo "Email is invalid or blocked: " . $errorMessage;
}
```

### Check Existing Status

```php
$email = 'user@example.com';

if (EmailValidation::isValid($email)) {
    echo "Email is known to be valid";
} elseif (EmailValidation::isBlocked($email)) {
    echo "Email is blocked";
} else {
    echo "Email status unknown - needs validation";
}
```

## Next Steps

- **[Bulk Validation →](./bulk-validation.md)** - Learn how to validate multiple emails efficiently
- **[Laravel Collections →](./laravel-collections.md)** - Advanced filtering and data manipulation
- **[API Reference →](./api-reference.md)** - Complete method documentation
