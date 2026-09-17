---
title: "Email validation"
description: "Validate email addresses in Laravel before sending, with the EmailValidation model of darvis/mailtrap."
nav_order: 6
has_children: true
---

# EmailValidation Model Documentation

The `EmailValidation` model provides comprehensive functionality for validating and managing email addresses. This model tracks which email addresses are valid, which are blocked, and why.

## 📚 Documentation Chapters

This documentation is organized into focused chapters for better navigation and maintenance:

### 🚀 **[Basic Usage & Database Structure →](./email-validation/basic-usage.md)**
Get started with email validation fundamentals:
- Overview of validation checks
- Database schema and structure  
- Individual email validation
- Manual status updates
- Quick examples

### 📊 **[Bulk Validation →](./email-validation/bulk-validation.md)**
Efficiently validate multiple email addresses:
- Bulk validation methods
- Result structures and status types
- Performance considerations
- Common use cases (newsletter, import)

### 🎨 **[Laravel Collections →](./email-validation/laravel-collections.md)**
Advanced filtering and data manipulation:
- Collection operations and filtering
- Data transformation techniques
- Handy one-liners and patterns
- Real-world filtering examples

### 📋 **[API Reference →](./email-validation/api-reference.md)**
Complete method documentation:
- All static methods with parameters
- Return value structures
- Error handling and exceptions
- Database schema details

### 💡 **[Practical Examples →](./email-validation/examples.md)**
Real-world implementation examples:
- Newsletter validation
- User registration flow
- Batch import with reporting
- Email list cleanup
- API endpoints
- Scheduled maintenance

### ⚡ **[Best Practices →](./email-validation/best-practices.md)**
Performance and maintenance guidelines:
- Performance optimization
- Error handling strategies
- Monitoring and maintenance
- Security considerations
- Testing approaches

## Quick Start

### Basic Validation
```php
use Darvis\Mailtrap\Models\EmailValidation;

// Validate a single email (returns null if valid, error message if invalid)
$errorMessage = EmailValidation::validateEmail('user@example.com');
$isValid = $errorMessage === null;

// Bulk validation
$emails = ['user1@test.com', 'user2@example.com'];
$result = EmailValidation::bulkValidationStatus($emails);

echo "Valid: " . $result['valid'];
echo "Invalid: " . $result['invalid'];
```

### Laravel Collections Filtering
```php
// Filter invalid emails
$invalidEmails = collect($result['details'])
    ->filter(fn($details) => $details['status'] !== 'valid')
    ->keys()
    ->toArray();
```

## Key Features

- ✅ **Format Validation** - Checks basic email format
- ✅ **MX Record Verification** - Verifies domain mail servers  
- ✅ **IP Validation** - Checks if MX records point to valid IPs
- ✅ **Bulk Processing** - Efficient validation of multiple emails
- ✅ **Laravel Collections** - Powerful filtering and data manipulation
- ✅ **Database Caching** - Fast lookups of previously validated emails
- ✅ **Status Tracking** - Maintains validation history

## Navigation Guide

| What you want to do | Go to |
|---------------------|-------|
| **Get started** | [Basic Usage →](./email-validation/basic-usage.md) |
| **Validate multiple emails** | [Bulk Validation →](./email-validation/bulk-validation.md) |
| **Filter and manipulate results** | [Laravel Collections →](./email-validation/laravel-collections.md) |
| **See real examples** | [Practical Examples →](./email-validation/examples.md) |
| **Look up a method** | [API Reference →](./email-validation/api-reference.md) |
| **Optimize performance** | [Best Practices →](./email-validation/best-practices.md) |

## Status Types

- **`valid`** - Email passed all validation checks
- **`blocked`** - Email is blocked (spam domain, invalid MX, etc.)
- **`invalid`** - Email has format issues or other problems  
- **`not_exists`** - Email not found in database (needs validation)

## Getting Help

Each chapter includes:
- 📖 **Detailed explanations** with context
- 💻 **Code examples** you can copy and use
- ⚡ **Performance tips** and best practices
- 🔗 **Cross-references** to related topics

Start with [Basic Usage →](./email-validation/basic-usage.md) if you're new to the EmailValidation model, or jump directly to the chapter that matches your needs using the navigation guide above.
