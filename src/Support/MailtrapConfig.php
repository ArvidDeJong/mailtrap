<?php

declare(strict_types=1);

namespace Darvis\Mailtrap\Support;

/**
 * The one place that reads the package config. Callers ask this class, so a default is written
 * once and a caller cannot quietly disagree with the config file about what it is.
 *
 * The deprecated keys (`api.base_url`, `validation.retry_attempts`, `rate_limiting` and
 * `development`) have no accessor: the package does not read them, and they go in 2.0.
 */
final class MailtrapConfig
{
    /**
     * Account API token. Only mailtrap:install and mailtrap:webhook use it.
     */
    public static function apiToken(): ?string
    {
        $token = config('manta_mailtrap.api.token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Base URL of the account API.
     */
    public static function apiAccountUrl(): string
    {
        return (string) config('manta_mailtrap.api.account_url', 'https://mailtrap.io');
    }

    /**
     * Timeout in seconds for a call to the account API.
     */
    public static function apiTimeout(): int
    {
        return (int) config('manta_mailtrap.api.timeout', 30);
    }

    /**
     * Whether recipients are validated before a mail is sent.
     */
    public static function validationEnabled(): bool
    {
        return (bool) config('manta_mailtrap.validation.enabled', true);
    }

    /**
     * Whether sending to a blocked address is aborted instead of logged.
     */
    public static function blockInvalid(): bool
    {
        return (bool) config('manta_mailtrap.validation.block_invalid', true);
    }

    /**
     * Seconds a local 'blocked' verdict stays in force. 0 never expires.
     */
    public static function validationCacheDuration(): int
    {
        return (int) config('manta_mailtrap.validation.cache_duration', 3600);
    }

    /**
     * Whether outgoing mail is written to the mail_logs table.
     */
    public static function loggingEnabled(): bool
    {
        return (bool) config('manta_mailtrap.logging.enabled', true);
    }

    /**
     * Whether a sent mail is logged. False as soon as logging itself is off.
     */
    public static function logSuccessful(): bool
    {
        return self::loggingEnabled() && (bool) config('manta_mailtrap.logging.log_successful', true);
    }

    /**
     * Whether a failed mail is logged. False as soon as logging itself is off.
     */
    public static function logFailed(): bool
    {
        return self::loggingEnabled() && (bool) config('manta_mailtrap.logging.log_failed', true);
    }

    /**
     * Retention in days for the inbox cleanup button and model:prune.
     */
    public static function cleanupAfterDays(): int
    {
        return (int) config('manta_mailtrap.logging.cleanup_after_days', 30);
    }

    /**
     * Whether events are also written to the Laravel log.
     */
    public static function logToLaravel(): bool
    {
        return (bool) config('manta_mailtrap.logging.log_to_laravel', false);
    }

    /**
     * Whether the webhook route is registered.
     */
    public static function webhookEnabled(): bool
    {
        return (bool) config('manta_mailtrap.webhook.enabled', true);
    }

    /**
     * The signing secret from the webhook detail panel in Mailtrap.
     */
    public static function webhookSecret(): string
    {
        return (string) config('manta_mailtrap.webhook.secret', '');
    }

    /**
     * Whether the Mailtrap-Signature header is checked. Fails closed without a secret.
     */
    public static function webhookVerifySignature(): bool
    {
        return (bool) config('manta_mailtrap.webhook.verify_signature', true);
    }

    /**
     * Whether the inbox page is registered.
     */
    public static function uiEnabled(): bool
    {
        return (bool) config('manta_mailtrap.ui.enabled', true);
    }

    /**
     * The inbox route as it is configured, without a leading slash.
     */
    public static function uiRoute(): string
    {
        return (string) config('manta_mailtrap.ui.route', 'mailtrap');
    }

    /**
     * The inbox route as a path, so every caller shows and registers the same thing.
     */
    public static function uiPath(): string
    {
        return '/'.ltrim(self::uiRoute(), '/');
    }

    /**
     * Middleware the inbox route runs through.
     *
     * @return array<int, string>
     */
    public static function uiMiddleware(): array
    {
        $middleware = config('manta_mailtrap.ui.middleware', ['web']);

        return array_values(array_filter(array_map('strval', (array) $middleware)));
    }

    /**
     * Blade layout the full-page inbox component is rendered in.
     */
    public static function uiLayout(): string
    {
        return (string) config('manta_mailtrap.ui.layout', 'components.layouts.app');
    }

    /**
     * Rows per page in the inbox.
     */
    public static function uiPerPage(): int
    {
        return (int) config('manta_mailtrap.ui.per_page', 25);
    }
}
