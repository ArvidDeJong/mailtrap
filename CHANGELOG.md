# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

### Placeholder
- Reserved for future changes

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
