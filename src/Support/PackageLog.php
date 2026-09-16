<?php

namespace Darvis\Mailtrap\Support;

use Illuminate\Support\Facades\Log;

/**
 * Write to the Laravel log only when logging.log_to_laravel is switched on.
 */
class PackageLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        if (config('manta_mailtrap.logging.log_to_laravel', false)) {
            Log::log($level, $message, $context);
        }
    }
}
