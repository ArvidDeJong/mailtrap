# Laravel Collections

Laravel's `collect()` function is a powerful way to filter and manipulate bulk validation results. Here are the most commonly used methods:

## Basic Collection Operations

```php
$result = EmailValidation::bulkValidationStatus($emails);
$collection = collect($result['details']);

// Filter only invalid email addresses (simplest way)
$invalidEmails = $collection
    ->reject(function ($details) {
        return $details['status'] === 'valid';
    })
    ->keys()
    ->toArray();

// Or use filter() for more clarity
$invalidEmails = $collection
    ->filter(function ($details) {
        return $details['status'] !== 'valid';
    })
    ->keys()
    ->toArray();
```

## Specific Status Filtering

```php
// Only blocked email addresses
$blockedEmails = $collection
    ->where('status', 'blocked')
    ->keys()
    ->toArray();

// Only invalid email addresses (blocked + invalid)
$invalidEmails = $collection
    ->whereIn('status', ['blocked', 'invalid'])
    ->keys()
    ->toArray();

// Email addresses that don't exist in database
$unknownEmails = $collection
    ->where('status', 'not_exists')
    ->keys()
    ->toArray();

// Valid email addresses
$validEmails = $collection
    ->where('status', 'valid')
    ->keys()
    ->toArray();
```

## Advanced Filtering

```php
// Filter by reason (e.g., DNS problems)
$dnsIssues = $collection
    ->filter(function ($details) {
        return str_contains(strtolower($details['reason']), 'mx') ||
               str_contains(strtolower($details['reason']), 'dns');
    })
    ->keys()
    ->toArray();

// Filter by date (e.g., recently checked)
$recentlyChecked = $collection
    ->filter(function ($details) {
        if ($details['last_checked_at'] === null) return false;
        
        $lastCheck = Carbon::parse($details['last_checked_at']);
        return $lastCheck->diffInHours(now()) < 24; // Last 24 hours
    })
    ->keys()
    ->toArray();
```

## Data Transformation

```php
// Get list with email + reason combinations
$emailsWithReasons = $collection
    ->whereIn('status', ['blocked', 'invalid'])
    ->map(function ($details, $email) {
        return [
            'email' => $email,
            'reason' => $details['reason']
        ];
    })
    ->values()
    ->toArray();

// Group by status
$groupedEmails = $collection
    ->groupBy('status')
    ->map(function ($group) {
        return $group->keys()->toArray();
    })
    ->toArray();

// Result: 
// [
//     'valid' => ['email1@test.com', 'email2@test.com'],
//     'blocked' => ['spam@test.com'],
//     'invalid' => ['bad@test.com']
// ]
```

## Handy One-Liners

```php
// Number of invalid email addresses
$invalidCount = $collection->whereNotIn('status', ['valid'])->count();

// All domains of invalid email addresses
$invalidDomains = $collection
    ->whereIn('status', ['blocked', 'invalid'])
    ->keys()
    ->map(function ($email) {
        return substr(strrchr($email, "@"), 1);
    })
    ->unique()
    ->values()
    ->toArray();

// Percentage of valid email addresses
$validPercentage = round(($collection->where('status', 'valid')->count() / $collection->count()) * 100, 2);

// Most common error reasons
$commonReasons = $collection
    ->whereIn('status', ['blocked', 'invalid'])
    ->pluck('reason')
    ->countBy()
    ->sortDesc()
    ->take(5)
    ->toArray();
```

## Practical Examples

```php
// For newsletter: get only valid email addresses
$newsletterList = collect($result['details'])
    ->where('status', 'valid')
    ->keys()
    ->toArray();

// For cleanup: get email addresses that need revalidation
$needsRevalidation = collect($result['details'])
    ->filter(function ($details) {
        if ($details['last_checked_at'] === null) return true;
        
        return Carbon::parse($details['last_checked_at'])->diffInDays(now()) > 30;
    })
    ->keys()
    ->toArray();

// For reporting: get summary per domain
$domainSummary = collect($result['details'])
    ->groupBy(function ($details, $email) {
        return substr(strrchr($email, "@"), 1); // Extract domain
    })
    ->map(function ($emails, $domain) {
        return [
            'domain' => $domain,
            'total' => $emails->count(),
            'valid' => $emails->where('status', 'valid')->count(),
            'invalid' => $emails->whereIn('status', ['blocked', 'invalid'])->count(),
            'unknown' => $emails->where('status', 'not_exists')->count()
        ];
    })
    ->sortByDesc('total')
    ->values()
    ->toArray();
```

## Collection Method Reference

### Filtering Methods
- **`filter()`** - Filter items based on a condition
- **`reject()`** - Opposite of filter
- **`where()`** - Filter by specific value
- **`whereIn()`** - Filter by multiple values
- **`whereNotIn()`** - Exclude specific values

### Transformation Methods
- **`map()`** - Transform each item
- **`groupBy()`** - Group items by a key
- **`pluck()`** - Extract specific fields
- **`keys()`** - Get only the keys (email addresses)
- **`values()`** - Reset array keys

### Aggregation Methods
- **`count()`** - Count items
- **`countBy()`** - Count occurrences of values
- **`sum()`** - Sum numeric values
- **`avg()`** - Calculate average

### Sorting Methods
- **`sortBy()`** - Sort by field (ascending)
- **`sortByDesc()`** - Sort by field (descending)
- **`sortDesc()`** - Sort values (descending)

## Advanced Patterns

### Chaining Multiple Operations

```php
$report = collect($result['details'])
    ->filter(fn($details) => $details['status'] !== 'valid')
    ->groupBy('status')
    ->map(function ($group, $status) {
        return [
            'status' => $status,
            'count' => $group->count(),
            'emails' => $group->keys()->take(5)->toArray(), // First 5 examples
            'reasons' => $group->pluck('reason')->unique()->values()->toArray()
        ];
    })
    ->sortByDesc('count')
    ->values()
    ->toArray();
```

### Custom Validation Rules

```php
// Find emails that might be temporary/disposable
$suspiciousEmails = collect($result['details'])
    ->filter(function ($details, $email) {
        $domain = substr(strrchr($email, "@"), 1);
        $suspiciousDomains = ['10minutemail.com', 'tempmail.org', 'guerrillamail.com'];
        
        return in_array($domain, $suspiciousDomains) || 
               str_contains($details['reason'], 'temporary');
    })
    ->keys()
    ->toArray();
```

## Next Steps

- **[Examples →](./examples.md)** - See real-world implementation examples
- **[API Reference →](./api-reference.md)** - Complete method documentation
- **[Best Practices →](./best-practices.md)** - Performance and error handling tips
