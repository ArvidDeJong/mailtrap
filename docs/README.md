# 📚 Mailtrap Package Documentation

Welcome to the comprehensive documentation of the Darvis Mailtrap package. This package provides powerful email validation functionality with Laravel integration.

## 📖 Available Documentation

### 🎯 **[EmailValidation Model →](./email-validation.md)**
**Complete guide for email validation functionality**

**What you'll learn:**
- 🔍 **Basic Functionality** - Individual email validation
- 📊 **Bulk Validation** - Efficient validation of multiple email addresses
- 🎨 **Laravel Collections** - Advanced filtering and data manipulation
- 📋 **API Reference** - All available methods and parameters
- 💡 **Practical Examples** - Real-world use cases and implementations
- ⚡ **Best Practices** - Performance optimization and error handling

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
echo "Unknown emails: " . $result['not_exists'];
```

### Laravel Collections Filtering

```php
// Filter invalid emails with Collections
$invalidEmails = collect($result['details'])
    ->filter(fn($details) => $details['status'] !== 'valid')
    ->keys()
    ->toArray();

// Group by status
$grouped = collect($result['details'])
    ->groupBy('status')
    ->map(fn($group) => $group->keys()->toArray());
```

**📖 [More advanced examples →](./email-validation/laravel-collections.md)**

## ✨ Key Features

### 🎯 **Email Validation**
- ✅ **Format Validation** - Checks basic email format
- ✅ **MX Record Verification** - Verifies domain mail servers
- ✅ **IP Validation** - Checks if MX records point to valid IPs
- ✅ **Bulk Validation** - Efficient validation of multiple email addresses
- ✅ **Laravel Collections** - Powerful filtering and data manipulation

### 📊 **Performance & Tracking**
- ✅ **Database Caching** - Fast lookups of previously validated emails
- ✅ **Status Tracking** - Maintains validation history
- ✅ **Detailed Reporting** - Comprehensive validation reporting
- ✅ **Status Codes** - HTTP-like codes for categorization

## 📋 Documentation Overview

| Topic | Description | Link |
|-------|-------------|------|
| **Basic Validation** | Individual email validation | [→](./email-validation/basic-usage.md) |
| **Bulk Validation** | Multiple email addresses at once | [→](./email-validation/bulk-validation.md) |
| **Laravel Collections** | Advanced filtering | [→](./email-validation/laravel-collections.md) |
| **API Reference** | All methods and parameters | [→](./email-validation/api-reference.md) |
| **Use Cases** | Practical examples | [→](./email-validation/examples.md) |
| **Best Practices** | Performance & error handling | [→](./email-validation/best-practices.md) |

## 🔧 Installation & Configuration

See the main documentation in **[README.md](../README.md)** for:
- 📦 Installation instructions
- ⚙️ Configuration options
- 🔑 Environment variables
- 🗄️ Database setup

## 💬 Support

For questions or issues:

1. **📖 Check the documentation first** - Many answers are already in the docs
2. **🔍 Search the examples** - Practical use cases can help
3. **🐛 Create an issue** - If you find a bug or missing functionality

**Tip:** Use the table of contents in each document to quickly find what you're looking for!
