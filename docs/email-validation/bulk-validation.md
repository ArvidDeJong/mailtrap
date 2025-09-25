# Bulk Validation

For efficiently checking multiple email addresses at once.

## Basic Bulk Check

```php
$emails = [
    'user1@example.com',
    'user2@test.com',
    'user3@invalid.domain'
];

$result = EmailValidation::bulkValidationStatus($emails);
```

**Result structure:**
```php
[
    'valid' => 1,           // Number of valid email addresses
    'invalid' => 1,         // Number of invalid/blocked email addresses
    'not_exists' => 1,      // Number of email addresses not in database
    'total' => 3,           // Total number of checked email addresses
    'details' => [          // Detailed information per email address
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

## Bulk Validation with Automatic Check

```php
// Check and validate missing email addresses automatically
$result = EmailValidation::bulkValidationWithCheck($emails, true);
```

This method performs the same check, but automatically validates email addresses that are not yet in the database.

## Understanding the Results

### Status Types

- **`valid`** - Email passed all validation checks
- **`blocked`** - Email is blocked (spam domain, invalid MX, etc.)
- **`invalid`** - Email has format issues or other problems
- **`not_exists`** - Email not found in database (needs validation)

### Working with Results

```php
$result = EmailValidation::bulkValidationStatus($emails);

// Get summary
echo "Total emails checked: " . $result['total'];
echo "Valid emails: " . $result['valid'];
echo "Invalid emails: " . $result['invalid'];
echo "Unknown emails: " . $result['not_exists'];

// Calculate success rate
$successRate = round(($result['valid'] / $result['total']) * 100, 2);
echo "Success rate: {$successRate}%";
```

## Performance Considerations

### Efficient Database Queries

The bulk validation methods are optimized for performance:

- **Single Query** - All existing validations are fetched in one database query
- **Batch Processing** - Multiple emails processed together
- **Caching** - Results are automatically cached in database

### Best Practices

```php
// ✅ Good: Use bulk validation for multiple emails
$result = EmailValidation::bulkValidationStatus($emails);

// ❌ Avoid: Individual validation in loops
foreach ($emails as $email) {
    $isValid = EmailValidation::validateEmail($email); // Inefficient!
}
```

## Common Use Cases

### Newsletter Signup Validation

```php
$subscriberEmails = [
    'john@example.com',
    'jane@test.com',
    'spam@blocked.com'
];

$result = EmailValidation::bulkValidationStatus($subscriberEmails);

// Only send to valid emails
$validEmails = [];
foreach ($result['details'] as $email => $details) {
    if ($details['status'] === 'valid') {
        $validEmails[] = $email;
    }
}

// Send newsletter to valid emails
// Mail::to($validEmails)->send(new NewsletterMail());
```

### Import Validation

```php
// Large CSV import
$importedEmails = [...]; // Array from CSV

// Validate all at once
$result = EmailValidation::bulkValidationWithCheck($importedEmails, true);

// Generate import report
$report = [
    'total_imported' => $result['total'],
    'valid_emails' => $result['valid'],
    'invalid_emails' => $result['invalid'],
    'success_rate' => round(($result['valid'] / $result['total']) * 100, 2) . '%'
];
```

## Next Steps

- **[Laravel Collections →](./laravel-collections.md)** - Learn advanced filtering techniques
- **[Examples →](./examples.md)** - See real-world implementation examples
- **[API Reference →](./api-reference.md)** - Complete method documentation
