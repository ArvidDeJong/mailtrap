<?php

namespace Darvis\Mailtrap\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Darvis\Mailtrap\MailtrapServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            MailtrapServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
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
        ];

        foreach ($migrations as $migration) {
            $migrationInstance = require __DIR__ . '/..' . $migration;
            $migrationInstance->up();
        }
    }
}
