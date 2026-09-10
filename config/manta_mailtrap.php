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
        // Schakel uit om de MX-lookups over te slaan. Die worden synchroon
        // tijdens het versturen uitgevoerd, dus trage DNS vertraagt de request.
        'enabled' => env('MAILTRAP_VALIDATION_ENABLED', true),

        // Hoe lang een lokaal vastgesteld 'blocked'-resultaat blijft gelden.
        // Daarna wordt het adres opnieuw gecontroleerd, zodat een tijdelijke
        // DNS-storing een adres niet voorgoed blokkeert. Zet op 0 om nooit te
        // verlopen. Statussen uit Mailtrap-events ('valid'/'invalid') worden
        // nooit lokaal opnieuw afgeleid.
        'cache_duration' => env('MAILTRAP_VALIDATION_CACHE_DURATION', 3600), // seconds

        'retry_attempts' => env('MAILTRAP_VALIDATION_RETRY_ATTEMPTS', 3),

        // Wanneer true wordt het versturen naar een geblokkeerd adres afgebroken
        // met een TransportException. Wanneer false gaat de mail gewoon uit en
        // blijft de reden alleen als error_message op de log staan.
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

        // Schrijf events ook naar de Laravel Log facade (standaard uit).
        'log_to_laravel' => env('MAILTRAP_LOG_TO_LARAVEL', false),
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
        // Wanneer false wordt de route /api/webhooks/mailtrap niet geregistreerd.
        'enabled' => env('MAILTRAP_WEBHOOK_ENABLED', true),

        // De 32-tekens hex signing secret uit het webhook-detailpaneel in Mailtrap.
        'secret' => env('MAILTRAP_WEBHOOK_SECRET'),

        // Controleert de HMAC-SHA256 in de Mailtrap-Signature header. Faalt
        // dicht: zonder secret worden alle calls met 403 geweigerd. Zet op false
        // om ongetekende calls te accepteren.
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

    /*
    |--------------------------------------------------------------------------
    | Inbox UI
    |--------------------------------------------------------------------------
    |
    | Configuratie voor de Mailtrap-achtige inbox: een Livewire/Flux pagina
    | waarmee je uitgaande mail kunt inspecteren en een testmail kunt sturen.
    | De UI vereist livewire/livewire en livewire/flux in de host-applicatie.
    |
    */

    'ui' => [
        'enabled' => env('MAILTRAP_UI_ENABLED', true),

        // Pad waarop de inbox bereikbaar is, bijv. https://app.test/mailtrap
        'route' => env('MAILTRAP_UI_ROUTE', 'mailtrap'),

        // Komma-gescheiden lijst van middleware, bijv. "web,auth" of "web,staff".
        'middleware' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MAILTRAP_UI_MIDDLEWARE', 'web'))
        ))),

        // Blade-layout waarin de full-page component wordt gerenderd.
        'layout' => env('MAILTRAP_UI_LAYOUT', 'components.layouts.app'),

        'per_page' => (int) env('MAILTRAP_UI_PER_PAGE', 25),
    ],

];
