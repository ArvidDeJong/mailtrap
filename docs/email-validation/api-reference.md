# API Reference

Complete reference for all EmailValidation model methods.

## Static Methods

### `validateEmail(string $email): bool`
Validates an email address and stores the result in the database.

**Parameters:**
- `$email` - The email address to validate

**Return:** `true` if valid, `false` if invalid

**Example:**
```php
$isValid = EmailValidation::validateEmail('user@example.com');
if ($isValid) {
    echo "Email is valid";
} else {
    echo "Email is invalid or blocked";
}
```

### `bulkValidationStatus(array $emails): array`
Checks the status of multiple email addresses against the database.

**Parameters:**
- `$emails` - Array of email addresses

**Return:** Array with counts and details

**Example:**
```php
$emails = ['user1@test.com', 'user2@example.com'];
$result = EmailValidation::bulkValidationStatus($emails);

echo "Valid: " . $result['valid'];
echo "Invalid: " . $result['invalid'];
echo "Unknown: " . $result['not_exists'];
```

### `bulkValidationWithCheck(array $emails, bool $validateMissing = false): array`
Bulk validation with option to validate missing email addresses.

**Parameters:**
- `$emails` - Array of email addresses
- `$validateMissing` - Whether missing email addresses should be validated

**Return:** Array with counts and details

**Example:**
```php
// Check existing status only
$result = EmailValidation::bulkValidationWithCheck($emails, false);

// Check and validate missing emails
$result = EmailValidation::bulkValidationWithCheck($emails, true);
```

### `isBlocked(string $email): bool`
Checks if an email address is blocked.

**Parameters:**
- `$email` - The email address to check

**Return:** `true` if blocked, `false` otherwise

**Example:**
```php
if (EmailValidation::isBlocked('spam@blocked.com')) {
    echo "Email is blocked";
}
```

### `isValid(string $email): bool`
Checks if an email address is valid.

**Parameters:**
- `$email` - The email address to check

**Return:** `true` if valid, `false` otherwise

**Example:**
```php
if (EmailValidation::isValid('user@example.com')) {
    echo "Email is valid";
}
```

### `markAsValid(string $email): EmailValidation`
Marks an email address as valid.

**Parameters:**
- `$email` - The email address to mark as valid

**Return:** EmailValidation model instance

**Example:**
```php
$validation = EmailValidation::markAsValid('user@example.com');
echo "Marked as valid: " . $validation->email;
```

### `markAsInvalid(string $email, string $reason, ?string $statusCode = null): EmailValidation`
Marks an email address as invalid.

**Parameters:**
- `$email` - The email address to mark as invalid
- `$reason` - Reason for marking as invalid
- `$statusCode` - Optional HTTP-like status code

**Return:** EmailValidation model instance

**Example:**
```php
$validation = EmailValidation::markAsInvalid(
    'bad@example.com', 
    'Domain does not exist',
    '404'
);
```

### `markAsBlocked(string $email, string $reason, ?string $statusCode = null): EmailValidation`
Marks an email address as blocked.

**Parameters:**
- `$email` - The email address to mark as blocked
- `$reason` - Reason for blocking
- `$statusCode` - Optional HTTP-like status code

**Return:** EmailValidation model instance

**Example:**
```php
$validation = EmailValidation::markAsBlocked(
    'spam@blocked.com', 
    'Known spam domain',
    '403'
);
```

## Return Value Structures

### Bulk Validation Result

```php
[
    'valid' => 2,           // Number of valid emails
    'invalid' => 1,         // Number of invalid/blocked emails  
    'not_exists' => 1,      // Number of emails not in database
    'total' => 4,           // Total number of emails checked
    'details' => [          // Detailed information per email
        'user1@example.com' => [
            'status' => 'valid',
            'reason' => 'All checks passed',
            'last_checked_at' => '2025-09-25 08:30:00'
        ],
        'user2@test.com' => [
            'status' => 'blocked',
            'reason' => 'No valid mail server found for domain',
            'last_checked_at' => '2025-09-25 08:29:00'
        ],
        'user3@invalid.domain' => [
            'status' => 'not_exists',
            'reason' => 'Email address not found in database',
            'last_checked_at' => null
        ]
    ]
]
```

### Status Values

- **`valid`** - Email passed all validation checks
- **`blocked`** - Email is blocked (spam, invalid MX, etc.)
- **`invalid`** - Email has format or other issues
- **`not_exists`** - Email not found in database

### Status Codes

The model uses HTTP-like status codes:

- `200` - Successfully validated
- `400` - Invalid format or DNS problems
- `403` - Blocked domain
- `404` - Domain not found
- `500` - Server/validation error

## Model Properties

### Fillable Fields

```php
protected $fillable = [
    'email',
    'domain', 
    'status',
    'reason',
    'status_code',
    'last_checked_at'
];
```

### Casts

```php
protected $casts = [
    'last_checked_at' => 'datetime'
];
```

## Database Schema

### Table: `email_validations`

```sql
CREATE TABLE email_validations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    reason TEXT,
    status_code INT,
    last_checked_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_domain (domain),
    INDEX idx_status (status),
    INDEX idx_last_checked (last_checked_at)
);
```

## Error Handling

### Common Exceptions

```php
try {
    $result = EmailValidation::bulkValidationStatus($emails);
} catch (\Exception $e) {
    Log::error('Email validation failed: ' . $e->getMessage());
    
    // Fallback to basic validation
    $validEmails = array_filter($emails, function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });
}
```

### Validation Failures

When `validateEmail()` returns `false`, check the database record for details:

```php
$email = 'test@example.com';
$isValid = EmailValidation::validateEmail($email);

if (!$isValid) {
    $validation = EmailValidation::where('email', $email)->first();
    echo "Validation failed: " . $validation->reason;
    echo "Status code: " . $validation->status_code;
}
```

## Performance Notes

### Bulk Operations

- Use `bulkValidationStatus()` for checking multiple emails
- Avoid loops with individual `validateEmail()` calls
- Results are automatically cached in database

### Database Queries

- Bulk methods use single queries for efficiency
- Indexes on email, domain, and status fields
- Consider pagination for very large datasets

### Memory Usage

```php
// For large datasets, process in chunks
$emails = [...]; // Large array
$chunks = array_chunk($emails, 100);

foreach ($chunks as $chunk) {
    $result = EmailValidation::bulkValidationStatus($chunk);
    // Process results...
}
```

## Next Steps

- **[Examples →](./examples.md)** - See real-world implementation examples
- **[Best Practices →](./best-practices.md)** - Performance and error handling tips
- **[Basic Usage →](./basic-usage.md)** - Getting started guide
