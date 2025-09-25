# Practical Examples

Real-world use cases and implementation examples for the EmailValidation model.

## Use Case 1: Newsletter Validation

Validate subscriber email addresses before sending newsletters.

```php
// List of newsletter subscribers
$subscribers = [
    'john@example.com',
    'jane@test.com',
    'invalid@nonexistent.domain',
    'blocked@spam.com'
];

// Check which email addresses are valid
$validation = EmailValidation::bulkValidationStatus($subscribers);

echo "Valid emails: " . $validation['valid'];
echo "Invalid emails: " . $validation['invalid'];
echo "Unknown emails: " . $validation['not_exists'];

// Filter only valid email addresses
$validEmails = [];
foreach ($validation['details'] as $email => $details) {
    if ($details['status'] === 'valid') {
        $validEmails[] = $email;
    }
}

// Send newsletter only to valid emails
if (count($validEmails) > 0) {
    // Mail::to($validEmails)->send(new NewsletterMail());
    echo "Newsletter sent to " . count($validEmails) . " valid subscribers";
}
```

### With Laravel Collections

```php
$validation = EmailValidation::bulkValidationStatus($subscribers);

// Get valid emails using Collections
$validEmails = collect($validation['details'])
    ->where('status', 'valid')
    ->keys()
    ->toArray();

// Generate detailed report
$report = collect($validation['details'])
    ->groupBy('status')
    ->map(function ($group, $status) {
        return [
            'status' => $status,
            'count' => $group->count(),
            'emails' => $group->keys()->toArray()
        ];
    });

echo "Newsletter Report:";
foreach ($report as $statusReport) {
    echo "{$statusReport['status']}: {$statusReport['count']} emails";
}
```

## Use Case 2: User Registration

Validate email addresses during user registration process.

```php
class UserRegistrationController extends Controller
{
    public function register(Request $request)
    {
        $email = $request->input('email');
        
        // First, check if we already know this email
        $quickCheck = EmailValidation::bulkValidationStatus([$email]);
        
        if ($quickCheck['not_exists'] > 0) {
            // Email is unknown, validate it
            $errorMessage = EmailValidation::validateEmail($email);
            
            if ($errorMessage === null) {
                // Registration can proceed
                $user = User::create([
                    'email' => $email,
                    'name' => $request->input('name'),
                    // ... other fields
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'User registered successfully'
                ]);
            } else {
                // Show error message
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email address: ' . $errorMessage
                ], 422);
            }
        } else {
            // We know this email already
            $details = $quickCheck['details'][$email];
            
            if ($details['status'] === 'valid') {
                // Proceed with registration
                $user = User::create([
                    'email' => $email,
                    'name' => $request->input('name'),
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'User registered successfully'
                ]);
            } else {
                // Email is blocked or invalid
                return response()->json([
                    'success' => false,
                    'message' => 'Email address is blocked: ' . $details['reason']
                ], 422);
            }
        }
    }
}
```

## Use Case 3: Batch Import with Reporting

Import and validate a large list of email addresses from CSV.

```php
class EmailImportService
{
    public function importFromCsv(string $csvPath): array
    {
        // Read emails from CSV
        $importEmails = [];
        if (($handle = fopen($csvPath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
                    $importEmails[] = $data[0];
                }
            }
            fclose($handle);
        }
        
        // Validate all emails in one go
        $result = EmailValidation::bulkValidationWithCheck($importEmails, true);
        
        // Generate comprehensive report
        $report = [
            'summary' => [
                'total_imported' => $result['total'],
                'valid_emails' => $result['valid'],
                'invalid_emails' => $result['invalid'],
                'success_rate' => round(($result['valid'] / $result['total']) * 100, 2) . '%'
            ],
            'details' => $this->generateDetailedReport($result),
            'invalid_emails' => $this->getInvalidEmailsWithReasons($result)
        ];
        
        return $report;
    }
    
    private function generateDetailedReport(array $result): array
    {
        return collect($result['details'])
            ->groupBy('status')
            ->map(function ($group, $status) {
                return [
                    'status' => $status,
                    'count' => $group->count(),
                    'percentage' => round(($group->count() / count($result['details'])) * 100, 2),
                    'sample_emails' => $group->keys()->take(5)->toArray()
                ];
            })
            ->toArray();
    }
    
    private function getInvalidEmailsWithReasons(array $result): array
    {
        return collect($result['details'])
            ->filter(fn($details) => $details['status'] !== 'valid')
            ->map(function ($details, $email) {
                return [
                    'email' => $email,
                    'status' => $details['status'],
                    'reason' => $details['reason']
                ];
            })
            ->values()
            ->toArray();
    }
}

// Usage
$importService = new EmailImportService();
$report = $importService->importFromCsv('subscribers.csv');

echo "Import Report:";
echo "=============";
echo "Total imported: " . $report['summary']['total_imported'];
echo "Valid emails: " . $report['summary']['valid_emails'];
echo "Invalid emails: " . $report['summary']['invalid_emails'];
echo "Success rate: " . $report['summary']['success_rate'];
```

## Use Case 4: Email List Cleanup

Clean up existing email lists by removing invalid addresses.

```php
class EmailListCleanupService
{
    public function cleanupList(array $emails): array
    {
        // Check all emails
        $result = EmailValidation::bulkValidationStatus($emails);
        
        // Separate into categories
        $cleanup = [
            'keep' => [],           // Valid emails to keep
            'remove' => [],         // Invalid emails to remove
            'revalidate' => [],     // Old emails that need revalidation
            'suspicious' => []      // Potentially problematic emails
        ];
        
        foreach ($result['details'] as $email => $details) {
            switch ($details['status']) {
                case 'valid':
                    // Check if validation is recent
                    if ($this->isRecentlyValidated($details['last_checked_at'])) {
                        $cleanup['keep'][] = $email;
                    } else {
                        $cleanup['revalidate'][] = $email;
                    }
                    break;
                    
                case 'blocked':
                case 'invalid':
                    $cleanup['remove'][] = [
                        'email' => $email,
                        'reason' => $details['reason']
                    ];
                    break;
                    
                case 'not_exists':
                    $cleanup['revalidate'][] = $email;
                    break;
            }
        }
        
        // Check for suspicious patterns
        $cleanup['suspicious'] = $this->findSuspiciousEmails($emails);
        
        return $cleanup;
    }
    
    private function isRecentlyValidated(?string $lastChecked): bool
    {
        if (!$lastChecked) return false;
        
        $lastCheck = Carbon::parse($lastChecked);
        return $lastCheck->diffInDays(now()) <= 30; // Valid for 30 days
    }
    
    private function findSuspiciousEmails(array $emails): array
    {
        $suspicious = [];
        $domainCounts = [];
        
        // Count emails per domain
        foreach ($emails as $email) {
            $domain = substr(strrchr($email, "@"), 1);
            $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + 1;
        }
        
        // Flag domains with too many emails (potential spam)
        foreach ($emails as $email) {
            $domain = substr(strrchr($email, "@"), 1);
            if ($domainCounts[$domain] > 50) { // More than 50 emails from same domain
                $suspicious[] = [
                    'email' => $email,
                    'reason' => "Too many emails from domain: {$domain} ({$domainCounts[$domain]} total)"
                ];
            }
        }
        
        return $suspicious;
    }
}

// Usage
$cleanupService = new EmailListCleanupService();
$existingEmails = User::pluck('email')->toArray();

$cleanup = $cleanupService->cleanupList($existingEmails);

echo "Cleanup Results:";
echo "Keep: " . count($cleanup['keep']) . " emails";
echo "Remove: " . count($cleanup['remove']) . " emails";
echo "Revalidate: " . count($cleanup['revalidate']) . " emails";
echo "Suspicious: " . count($cleanup['suspicious']) . " emails";
```

## Use Case 5: Real-time API Validation

Create an API endpoint for real-time email validation.

```php
class EmailValidationController extends Controller
{
    public function validateSingle(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        
        $email = $request->input('email');
        
        // Check if we already know this email
        $existing = EmailValidation::bulkValidationStatus([$email]);
        
        if ($existing['not_exists'] === 0) {
            // We have this email in our database
            $details = $existing['details'][$email];
            
            return response()->json([
                'email' => $email,
                'is_valid' => $details['status'] === 'valid',
                'status' => $details['status'],
                'reason' => $details['reason'],
                'last_checked' => $details['last_checked_at'],
                'from_cache' => true
            ]);
        } else {
            // New email, validate it
            $errorMessage = EmailValidation::validateEmail($email);
            
            // Get the validation details
            $validation = EmailValidation::where('email', $email)->first();
            
            return response()->json([
                'email' => $email,
                'is_valid' => $errorMessage === null,
                'status' => $validation->status,
                'reason' => $validation->reason,
                'last_checked' => $validation->last_checked_at,
                'from_cache' => false
            ]);
        }
    }
    
    public function validateBulk(Request $request)
    {
        $request->validate([
            'emails' => 'required|array|max:100', // Limit to 100 emails per request
            'emails.*' => 'email'
        ]);
        
        $emails = $request->input('emails');
        
        // Validate all emails
        $result = EmailValidation::bulkValidationWithCheck($emails, true);
        
        // Format response
        $response = [
            'summary' => [
                'total' => $result['total'],
                'valid' => $result['valid'],
                'invalid' => $result['invalid'],
                'success_rate' => round(($result['valid'] / $result['total']) * 100, 2)
            ],
            'results' => collect($result['details'])
                ->map(function ($details, $email) {
                    return [
                        'email' => $email,
                        'is_valid' => $details['status'] === 'valid',
                        'status' => $details['status'],
                        'reason' => $details['reason']
                    ];
                })
                ->values()
                ->toArray()
        ];
        
        return response()->json($response);
    }
}

// Routes
Route::post('/api/email/validate', [EmailValidationController::class, 'validateSingle']);
Route::post('/api/email/validate-bulk', [EmailValidationController::class, 'validateBulk']);
```

## Use Case 6: Scheduled Email List Maintenance

Automatically maintain email lists with scheduled jobs.

```php
class EmailMaintenanceJob implements ShouldQueue
{
    public function handle()
    {
        // Find emails that haven't been checked in 30 days
        $oldValidations = EmailValidation::where('last_checked_at', '<', now()->subDays(30))
            ->where('status', 'valid')
            ->limit(1000) // Process in batches
            ->get();
        
        $emailsToRevalidate = $oldValidations->pluck('email')->toArray();
        
        if (count($emailsToRevalidate) > 0) {
            // Revalidate old emails
            $result = EmailValidation::bulkValidationWithCheck($emailsToRevalidate, true);
            
            // Log results
            Log::info('Email maintenance completed', [
                'emails_checked' => $result['total'],
                'still_valid' => $result['valid'],
                'now_invalid' => $result['invalid']
            ]);
            
            // Notify admin if many emails became invalid
            $invalidationRate = ($result['invalid'] / $result['total']) * 100;
            if ($invalidationRate > 10) { // More than 10% became invalid
                // Send notification to admin
                Mail::to(config('mail.admin_email'))->send(
                    new HighInvalidationRateNotification($result)
                );
            }
        }
    }
}

// Schedule in app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new EmailMaintenanceJob)
             ->daily()
             ->at('02:00'); // Run at 2 AM
}
```

## Performance Tips

### Chunking Large Datasets

```php
// For very large email lists
$allEmails = User::pluck('email'); // Could be millions

$allEmails->chunk(1000)->each(function ($emailChunk) {
    $result = EmailValidation::bulkValidationStatus($emailChunk->toArray());
    
    // Process results for this chunk
    $this->processValidationResults($result);
    
    // Optional: Add delay to avoid overwhelming the system
    sleep(1);
});
```

### Caching Results

```php
// Cache validation results for frequently checked emails
$email = 'user@example.com';
$cacheKey = "email_validation:" . md5($email);

$errorMessage = Cache::remember($cacheKey, 3600, function () use ($email) {
    return EmailValidation::validateEmail($email);
});

$isValid = $errorMessage === null;
```

## Next Steps

- **[Best Practices →](./best-practices.md)** - Performance and error handling tips
- **[API Reference →](./api-reference.md)** - Complete method documentation
- **[Laravel Collections →](./laravel-collections.md)** - Advanced filtering techniques
