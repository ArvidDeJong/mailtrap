<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mailtrap API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and endpoints for the Mailtrap account API. The token needs
    | admin access to the account and is only used by mailtrap:install and
    | mailtrap:webhook to create the webhook.
    |
    | This is not the SMTP password. Sending through Mailtrap uses the token of
    | the sending domain as MAIL_PASSWORD (Sending Domains → Integration).
    |
    */

    'api' => [
        // Account API used by mailtrap:webhook to create webhooks.
        'account_url' => env('MAILTRAP_ACCOUNT_API_URL', 'https://mailtrap.io'),

        // Deprecated: not read by the package, removed in 2.0.
        'base_url' => env('MAILTRAP_BASE_URL', 'https://api.mailtrap.io/api/v1'),

        'timeout' => env('MAILTRAP_TIMEOUT', 30),

        'token' => env('MAILTRAP_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the development environment.
    |
    */

    // Deprecated: not read by the package, removed in 2.0.
    'development' => [
        'debug_mode' => env('MAILTRAP_DEBUG_MODE', false),

        'log_api_requests' => env('MAILTRAP_LOG_API_REQUESTS', false),

        'sandbox_mode' => env('MAILTRAP_SANDBOX_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Logging Settings
    |--------------------------------------------------------------------------
    |
    | Logging of outgoing mail to the mail_logs table.
    |
    */

    'logging' => [
        // Retention for the inbox cleanup button and `php artisan model:prune`.
        'cleanup_after_days' => env('MAILTRAP_CLEANUP_AFTER_DAYS', 30),

        'enabled' => env('MAILTRAP_LOGGING_ENABLED', true),

        'log_failed' => env('MAILTRAP_LOG_FAILED', true),

        'log_successful' => env('MAILTRAP_LOG_SUCCESSFUL', true),

        // Also write events to the Laravel log (off by default).
        'log_to_laravel' => env('MAILTRAP_LOG_TO_LARAVEL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting of API calls.
    |
    */

    // Deprecated: not read by the package, removed in 2.0.
    'rate_limiting' => [
        'enabled' => env('MAILTRAP_RATE_LIMITING_ENABLED', true),

        'max_requests_per_minute' => env('MAILTRAP_MAX_REQUESTS_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbox UI
    |--------------------------------------------------------------------------
    |
    | A Mailtrap-style inbox: a Livewire/Flux page to inspect outgoing mail
    | and send a test mail. It requires livewire/livewire and livewire/flux
    | in the host application.
    |
    */

    'ui' => [
        'enabled' => env('MAILTRAP_UI_ENABLED', true),

        // Blade layout the full-page component is rendered in.
        'layout' => env('MAILTRAP_UI_LAYOUT', 'components.layouts.app'),

        // Comma-separated list of middleware, e.g. "web,auth" or "web,staff".
        'middleware' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MAILTRAP_UI_MIDDLEWARE', 'web'))
        ))),

        'per_page' => (int) env('MAILTRAP_UI_PER_PAGE', 25),

        // Path of the inbox, e.g. https://app.test/mailtrap
        'route' => env('MAILTRAP_UI_ROUTE', 'mailtrap'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Validation Settings
    |--------------------------------------------------------------------------
    |
    | How recipients are validated before a mail is sent.
    |
    */

    'validation' => [
        // When true, sending to a blocked address is aborted with a
        // TransportException. When false the mail goes out anyway and the
        // reason is only kept as error_message on the log.
        'block_invalid' => env('MAILTRAP_BLOCK_INVALID_EMAILS', true),

        // How long a 'blocked' verdict from a local check stays in force. After
        // that the address is checked again, so a temporary DNS outage does not
        // block it for good. Set to 0 to never expire. Verdicts from Mailtrap
        // events ('valid'/'invalid') are never re-derived locally.
        'cache_duration' => env('MAILTRAP_VALIDATION_CACHE_DURATION', 3600), // seconds

        // Switch off to skip the MX lookups. They run synchronously while the
        // mail is sent, so slow DNS slows down the request.
        'enabled' => env('MAILTRAP_VALIDATION_ENABLED', true),

        // Deprecated: not read by the package, removed in 2.0.
        'retry_attempts' => env('MAILTRAP_VALIDATION_RETRY_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | The endpoint that receives Mailtrap delivery events.
    |
    */

    'webhook' => [
        // When false, the /api/webhooks/mailtrap route is not registered.
        'enabled' => env('MAILTRAP_WEBHOOK_ENABLED', true),

        // The 32-character hex signing secret from the webhook detail panel in Mailtrap.
        'secret' => env('MAILTRAP_WEBHOOK_SECRET'),

        // Checks the HMAC-SHA256 in the Mailtrap-Signature header. Fails closed:
        // without a secret every call is rejected with 403. Set to false to
        // accept unsigned calls.
        'verify_signature' => env('MAILTRAP_WEBHOOK_VERIFY_SIGNATURE', true),
    ],

];
