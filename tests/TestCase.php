<?php

namespace Darvis\Mailtrap\Tests;

use Darvis\Mailtrap\MailtrapServiceProvider;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            MailtrapServiceProvider::class,
        ];
    }

    /**
     * Signing secret used by the webhook tests.
     */
    protected const WEBHOOK_SECRET = '0123456789abcdef0123456789abcdef';

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('manta_mailtrap.webhook.secret', self::WEBHOOK_SECRET);
    }

    /**
     * POST a webhook payload carrying a valid Mailtrap signature.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postSignedWebhook(array $payload, ?string $secret = null): TestResponse
    {
        $body = json_encode($payload);

        return $this->call(
            'POST',
            '/api/webhooks/mailtrap',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_MAILTRAP_SIGNATURE' => hash_hmac('sha256', $body, $secret ?? self::WEBHOOK_SECRET),
            ],
            content: $body,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->runPackageMigrations();
    }

    private function runPackageMigrations(): void
    {
        $migrations = [
            '/database/migrations/2024_01_01_000000_create_email_validations_table.php',
            '/database/migrations/2024_01_01_000001_create_mail_logs_table.php',
            '/database/migrations/2024_01_01_000002_add_error_tracking_to_mail_logs_table.php',
            '/database/migrations/2024_01_01_000003_make_message_id_nullable_in_mail_logs_table.php',
        ];

        foreach ($migrations as $migration) {
            $migrationInstance = require __DIR__.'/..'.$migration;
            $migrationInstance->up();
        }
    }
}
