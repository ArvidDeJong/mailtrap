<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mailtrap API Configuration
    |--------------------------------------------------------------------------
    |
    | Deze configuratie bevat de instellingen voor de Mailtrap API integratie.
    | Zorg ervoor dat je de juiste API token en instellingen configureert.
    |
    */

    'api' => [
        'token' => env('MAILTRAP_API_TOKEN'),
        'base_url' => env('MAILTRAP_BASE_URL', 'https://api.mailtrap.io/api/v1'),
        'timeout' => env('MAILTRAP_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configuratie voor email validatie via Mailtrap.
    |
    */

    'validation' => [
        'enabled' => env('MAILTRAP_VALIDATION_ENABLED', true),
        'cache_duration' => env('MAILTRAP_VALIDATION_CACHE_DURATION', 3600), // seconds
        'retry_attempts' => env('MAILTRAP_VALIDATION_RETRY_ATTEMPTS', 3),
        'block_invalid' => env('MAILTRAP_BLOCK_INVALID_EMAILS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configuratie voor het loggen van uitgaande emails.
    |
    */

    'logging' => [
        'enabled' => env('MAILTRAP_LOGGING_ENABLED', true),
        'log_successful' => env('MAILTRAP_LOG_SUCCESSFUL', true),
        'log_failed' => env('MAILTRAP_LOG_FAILED', true),
        'cleanup_after_days' => env('MAILTRAP_CLEANUP_AFTER_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configuratie voor Mailtrap webhooks.
    |
    */

    'webhook' => [
        'enabled' => env('MAILTRAP_WEBHOOK_ENABLED', true),
        'secret' => env('MAILTRAP_WEBHOOK_SECRET'),
        'verify_signature' => env('MAILTRAP_WEBHOOK_VERIFY_SIGNATURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuratie voor rate limiting van API calls.
    |
    */

    'rate_limiting' => [
        'enabled' => env('MAILTRAP_RATE_LIMITING_ENABLED', true),
        'max_requests_per_minute' => env('MAILTRAP_MAX_REQUESTS_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Settings
    |--------------------------------------------------------------------------
    |
    | Instellingen specifiek voor development omgeving.
    |
    */

    'development' => [
        'debug_mode' => env('MAILTRAP_DEBUG_MODE', false),
        'log_api_requests' => env('MAILTRAP_LOG_API_REQUESTS', false),
        'sandbox_mode' => env('MAILTRAP_SANDBOX_MODE', false),
    ],

];
