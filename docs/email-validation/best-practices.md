# Best Practices

Performance optimization, error handling, and maintenance guidelines for the EmailValidation model.

## Performance Optimization

### 1. Use Bulk Operations

**✅ Good: Use bulk validation for multiple emails**
```php
$emails = ['user1@test.com', 'user2@test.com', 'user3@test.com'];
$result = EmailValidation::bulkValidationStatus($emails);
```

**❌ Avoid: Individual validation in loops**
```php
foreach ($emails as $email) {
    $isValid = EmailValidation::validateEmail($email); // Inefficient!
}
```

### 2. Leverage Database Caching

The model automatically caches validation results. Use this to your advantage:

```php
// First call validates and stores in database
$errorMessage = EmailValidation::validateEmail('user@example.com');
$isValid = $errorMessage === null;

// Subsequent calls use cached result (fast)
$isStillValid = EmailValidation::isValid('user@example.com');
```

### 3. Limit Validation Scope

Only validate when necessary:

```php
// Check cache first
$result = EmailValidation::bulkValidationStatus($emails);

// Only validate unknown emails
$unknownEmails = [];
foreach ($result['details'] as $email => $details) {
    if ($details['status'] === 'not_exists') {
        $unknownEmails[] = $email;
    }
}

if (count($unknownEmails) > 0) {
    EmailValidation::bulkValidationWithCheck($unknownEmails, true);
}
```

### 4. Process Large Datasets in Chunks

```php
$allEmails = User::pluck('email')->toArray(); // Could be thousands

// Process in manageable chunks
$chunks = array_chunk($allEmails, 100);

foreach ($chunks as $chunk) {
    $result = EmailValidation::bulkValidationStatus($chunk);
    
    // Process results...
    $this->processResults($result);
    
    // Optional: Add small delay to prevent overwhelming the system
    usleep(100000); // 0.1 second
}
```

## Error Handling

### 1. Graceful Degradation

Always have a fallback when validation fails:

```php
try {
    $result = EmailValidation::bulkValidationStatus($emails);
} catch (Exception $e) {
    Log::error('Email validation failed: ' . $e->getMessage());
    
    // Fallback to basic PHP validation
    $validEmails = array_filter($emails, function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });
    
    // Continue with basic validation
    return $validEmails;
}
```

### 2. Handle Database Connectivity Issues

```php
try {
    $errorMessage = EmailValidation::validateEmail($email);
    $isValid = $errorMessage === null;
} catch (QueryException $e) {
    Log::error('Database error during email validation', [
        'email' => $email,
        'error' => $e->getMessage()
    ]);
    
    // Fallback to basic validation
    return filter_var($email, FILTER_VALIDATE_EMAIL);
} catch (Exception $e) {
    Log::error('Unexpected error during email validation', [
        'email' => $email,
        'error' => $e->getMessage()
    ]);
    
    return false; // Fail safe
}
```

### 3. Validate Input Data

```php
public function validateEmails(array $emails): array
{
    // Filter out invalid input
    $validEmails = array_filter($emails, function($email) {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    });
    
    if (count($validEmails) !== count($emails)) {
        Log::warning('Some emails filtered out due to invalid format', [
            'original_count' => count($emails),
            'valid_count' => count($validEmails)
        ]);
    }
    
    return EmailValidation::bulkValidationStatus($validEmails);
}
```

## Monitoring and Maintenance

### 1. Monitor Validation Statistics

Track validation performance and success rates:

```php
class EmailValidationMonitor
{
    public function getValidationStats(): array
    {
        $stats = EmailValidation::selectRaw('
            status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM email_validations), 2) as percentage
        ')
        ->groupBy('status')
        ->get()
        ->keyBy('status');
        
        return [
            'total_validations' => EmailValidation::count(),
            'valid_rate' => $stats->get('valid')->percentage ?? 0,
            'invalid_rate' => $stats->get('blocked')->percentage ?? 0 + $stats->get('invalid')->percentage ?? 0,
            'last_validation' => EmailValidation::latest('last_checked_at')->first()?->last_checked_at
        ];
    }
    
    public function getRecentActivity(): array
    {
        return EmailValidation::where('last_checked_at', '>=', now()->subHours(24))
            ->selectRaw('
                status,
                COUNT(*) as count
            ')
            ->groupBy('status')
            ->get()
            ->toArray();
    }
}
```

### 2. Periodic Cleanup

Remove old validation records to keep the database lean:

```php
class EmailValidationCleanup
{
    public function cleanupOldRecords(): int
    {
        // Remove validations older than 6 months for invalid emails
        $deletedInvalid = EmailValidation::where('status', '!=', 'valid')
            ->where('last_checked_at', '<', now()->subMonths(6))
            ->delete();
        
        // Remove validations older than 1 year for valid emails
        $deletedValid = EmailValidation::where('status', 'valid')
            ->where('last_checked_at', '<', now()->subYear())
            ->delete();
        
        $totalDeleted = $deletedInvalid + $deletedValid;
        
        Log::info('Email validation cleanup completed', [
            'deleted_invalid' => $deletedInvalid,
            'deleted_valid' => $deletedValid,
            'total_deleted' => $totalDeleted
        ]);
        
        return $totalDeleted;
    }
}

// Schedule in app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        (new EmailValidationCleanup())->cleanupOldRecords();
    })->monthly();
}
```

### 3. Revalidation Strategy

Implement smart revalidation for old records:

```php
class EmailRevalidationService
{
    public function revalidateOldEmails(int $limit = 1000): array
    {
        // Find emails that need revalidation
        $oldValidations = EmailValidation::where('last_checked_at', '<', now()->subDays(30))
            ->where('status', 'valid') // Only revalidate previously valid emails
            ->limit($limit)
            ->get();
        
        if ($oldValidations->isEmpty()) {
            return ['message' => 'No emails need revalidation'];
        }
        
        $emailsToCheck = $oldValidations->pluck('email')->toArray();
        
        // Revalidate
        $result = EmailValidation::bulkValidationWithCheck($emailsToCheck, true);
        
        // Calculate change statistics
        $nowInvalid = collect($result['details'])
            ->filter(fn($details) => $details['status'] !== 'valid')
            ->count();
        
        return [
            'checked' => $result['total'],
            'still_valid' => $result['valid'],
            'now_invalid' => $nowInvalid,
            'invalidation_rate' => round(($nowInvalid / $result['total']) * 100, 2) . '%'
        ];
    }
}
```

## Security Considerations

### 1. Rate Limiting

Implement rate limiting for validation endpoints:

```php
// In your controller
public function validateEmail(Request $request)
{
    // Rate limit: 100 requests per minute per IP
    if (RateLimiter::tooManyAttempts('email-validation:' . $request->ip(), 100)) {
        return response()->json([
            'error' => 'Too many validation requests'
        ], 429);
    }
    
    RateLimiter::hit('email-validation:' . $request->ip(), 60);
    
    // Proceed with validation...
}
```

### 2. Input Sanitization

Always sanitize email input:

```php
public function sanitizeEmail(string $email): string
{
    // Remove whitespace and convert to lowercase
    $email = strtolower(trim($email));
    
    // Remove any potentially dangerous characters
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    return $email;
}
```

### 3. Prevent Enumeration Attacks

Don't expose too much information about validation results:

```php
public function publicValidation(string $email): array
{
    $errorMessage = EmailValidation::validateEmail($email);
    $isValid = $errorMessage === null;
    
    // Only return basic validation status, not detailed reasons
    return [
        'email' => $email,
        'is_valid' => $isValid,
        // Don't expose: reason, status_code, last_checked_at
    ];
}
```

## Configuration Best Practices

### 1. Environment-Specific Settings

```php
// config/mail_validation.php
return [
    'validation' => [
        'enabled' => env('EMAIL_VALIDATION_ENABLED', true),
        'timeout' => env('EMAIL_VALIDATION_TIMEOUT', 10), // seconds
        'max_bulk_size' => env('EMAIL_VALIDATION_MAX_BULK', 100),
    ],
    
    'cleanup' => [
        'invalid_retention_days' => env('EMAIL_VALIDATION_INVALID_RETENTION', 180),
        'valid_retention_days' => env('EMAIL_VALIDATION_VALID_RETENTION', 365),
    ],
    
    'monitoring' => [
        'alert_threshold' => env('EMAIL_VALIDATION_ALERT_THRESHOLD', 10), // % invalid
        'admin_email' => env('EMAIL_VALIDATION_ADMIN_EMAIL'),
    ]
];
```

### 2. Database Indexing

Ensure proper database indexes for performance:

```php
// In your migration
Schema::table('email_validations', function (Blueprint $table) {
    $table->index(['email']);
    $table->index(['domain']);
    $table->index(['status']);
    $table->index(['last_checked_at']);
    $table->index(['status', 'last_checked_at']); // Composite index
});
```

## Testing Best Practices

### 1. Mock External Dependencies

```php
// In your tests
public function test_email_validation_with_mock()
{
    // Mock DNS lookups for consistent testing
    $this->mock('dns_get_record', function ($hostname, $type) {
        return [['type' => 'MX', 'target' => 'mail.example.com']];
    });
    
    $errorMessage = EmailValidation::validateEmail('test@example.com');
    
    $this->assertNull($errorMessage);
}
```

### 2. Test Error Conditions

```php
public function test_handles_database_errors_gracefully()
{
    // Simulate database error
    DB::shouldReceive('table')->andThrow(new QueryException('Connection failed'));
    
    $errorMessage = EmailValidation::validateEmail('test@example.com');
    
    // Should return error message or fallback
    $this->assertNotNull($errorMessage);
}
```

### 3. Performance Testing

```php
public function test_bulk_validation_performance()
{
    $emails = factory(User::class, 1000)->make()->pluck('email')->toArray();
    
    $startTime = microtime(true);
    $result = EmailValidation::bulkValidationStatus($emails);
    $endTime = microtime(true);
    
    $executionTime = $endTime - $startTime;
    
    // Should complete within reasonable time
    $this->assertLessThan(5, $executionTime); // 5 seconds max
    $this->assertEquals(1000, $result['total']);
}
```

## Common Pitfalls to Avoid

### 1. Don't Validate in Loops

```php
// ❌ Bad
foreach ($users as $user) {
    $errorMessage = EmailValidation::validateEmail($user->email);
    if ($errorMessage === null) {
        // Send email
    }
}

// ✅ Good
$emails = $users->pluck('email')->toArray();
$result = EmailValidation::bulkValidationStatus($emails);
$validEmails = collect($result['details'])
    ->where('status', 'valid')
    ->keys()
    ->toArray();
```

### 2. Don't Ignore Cache

```php
// ❌ Bad - Always validates, ignoring cache
$errorMessage = EmailValidation::validateEmail($email);

// ✅ Good - Check cache first
if (!EmailValidation::isValid($email)) {
    $errorMessage = EmailValidation::validateEmail($email);
}
```

### 3. Don't Forget Error Handling

```php
// ❌ Bad - No error handling
$result = EmailValidation::bulkValidationStatus($emails);
$validEmails = collect($result['details'])->where('status', 'valid');

// ✅ Good - Handle potential errors
try {
    $result = EmailValidation::bulkValidationStatus($emails);
    $validEmails = collect($result['details'])->where('status', 'valid');
} catch (Exception $e) {
    Log::error('Validation failed', ['error' => $e->getMessage()]);
    // Implement fallback logic
}
```

## Next Steps

- **[Examples →](./examples.md)** - See real-world implementation examples
- **[API Reference →](./api-reference.md)** - Complete method documentation
- **[Basic Usage →](./basic-usage.md)** - Getting started guide
