<?php

namespace Darvis\Mailtrap\Console\Commands;

use Darvis\Mailtrap\Console\Commands\Concerns\WritesEnvironment;
use Illuminate\Console\Command;

class MailtrapInstallCommand extends Command
{
    use WritesEnvironment;

    /**
     * @var string
     */
    protected $signature = 'mailtrap:install
        {--url= : Public webhook URL (defaults to APP_URL/api/webhooks/mailtrap)}
        {--token= : Mailtrap API token with admin access (defaults to MAILTRAP_API_TOKEN)}
        {--webhook : Create the Mailtrap webhook without asking}
        {--without-webhook : Disable the webhook endpoint without asking}
        {--replace : Replace an existing webhook for the same URL}
        {--config : Publish config/manta_mailtrap.php}
        {--skip-migrations : Do not run the database migrations}';

    /**
     * @var string
     */
    protected $description = 'Install the Mailtrap package: migrations, API token and a signed webhook';

    public function handle(): int
    {
        $this->showCurrentState();

        $this->publishConfig();

        if (! $this->option('skip-migrations') && $this->confirm('Run the database migrations?', true)) {
            $this->call('migrate', ['--force' => true]);
        }

        $webhookResult = $this->configureWebhook();

        $this->newLine();
        $this->components->bulletList([
            'Inbox UI: /'.ltrim((string) config('manta_mailtrap.ui.route', 'mailtrap'), '/').' (protect it with MAILTRAP_UI_MIDDLEWARE)',
            "Tailwind: @source '../../vendor/darvis/mailtrap/resources/views/**/*.blade.php';",
            'Health check: php artisan mailtrap:test you@example.com',
        ]);

        return $webhookResult;
    }

    /**
     * Print what is configured now, so a re-run on an existing site shows what
     * it is about to change.
     */
    private function showCurrentState(): void
    {
        $token = $this->currentApiToken();
        $secret = (string) config('manta_mailtrap.webhook.secret');
        $isInstalled = $token !== null || $secret !== '' || $this->environmentFile()->get('MAILTRAP_WEBHOOK_ENABLED') !== null;

        $this->components->info($isInstalled ? 'Reconfiguring darvis/mailtrap' : 'Installing darvis/mailtrap');

        if (! $isInstalled) {
            return;
        }

        $this->components->twoColumnDetail('API token', $token ? 'set (…'.substr($token, -4).')' : '<fg=yellow>not set</>');
        $this->components->twoColumnDetail('Webhook endpoint', config('manta_mailtrap.webhook.enabled') ? 'enabled' : 'disabled');
        $this->components->twoColumnDetail('Signing secret', $secret !== '' ? 'set' : '<fg=yellow>not set</>');
        $this->components->twoColumnDetail('Signature check', config('manta_mailtrap.webhook.verify_signature', true) ? 'on' : '<fg=yellow>off</>');

        if ($this->laravel->configurationIsCached() && (string) $this->environmentFile()->get('MAILTRAP_WEBHOOK_SECRET') !== $secret) {
            $this->components->warn('The cached configuration holds a different signing secret than .env. It is rebuilt when this command writes .env.');
        }

        $this->newLine();
    }

    private function currentApiToken(): ?string
    {
        $token = config('manta_mailtrap.api.token') ?: $this->environmentFile()->get('MAILTRAP_API_TOKEN');

        return $token ? (string) $token : null;
    }

    /**
     * Use --token, keep the current token, or ask for a new one. A token that
     * differs from the stored one is written to .env.
     */
    private function resolveApiToken(): ?string
    {
        $current = $this->currentApiToken();
        $token = $this->option('token') ? (string) $this->option('token') : null;

        if ($token === null && $current !== null && (! $this->input->isInteractive() || $this->confirm('Keep the current API token (…'.substr($current, -4).')?', true))) {
            return $current;
        }

        if ($token === null && $this->input->isInteractive()) {
            $token = (string) $this->secret('Mailtrap API token (Settings → API Tokens, needs admin access)') ?: null;
        }

        if ($token !== null && $token !== $current) {
            $this->writeEnvironment(['MAILTRAP_API_TOKEN' => $token]);
            config()->set('manta_mailtrap.api.token', $token);
        }

        return $token;
    }

    private function publishConfig(): void
    {
        if (file_exists(config_path('manta_mailtrap.php'))) {
            return;
        }

        if ($this->option('config') || ($this->input->isInteractive() && $this->confirm('Publish config/manta_mailtrap.php?', false))) {
            $this->callSilently('vendor:publish', ['--tag' => 'mailtrap-config']);
            $this->components->info('Config published.');
        }
    }

    private function configureWebhook(): int
    {
        if ($this->option('without-webhook')) {
            return $this->disableWebhook();
        }

        if (! $this->option('webhook')) {
            if (! $this->input->isInteractive()) {
                $this->components->warn('Webhook left unchanged. Pass --webhook or --without-webhook.');

                return self::SUCCESS;
            }

            if (! $this->confirm('Do you send through Mailtrap and want delivery and bounce events via a webhook?', true)) {
                return $this->disableWebhook();
            }
        }

        $token = $this->resolveApiToken();

        return $this->call('mailtrap:webhook', array_filter([
            'url' => $this->option('url'),
            '--token' => $token ?: null,
            '--replace' => $this->option('replace'),
            '--no-interaction' => ! $this->input->isInteractive(),
        ]));
    }

    private function disableWebhook(): int
    {
        $this->writeEnvironment(['MAILTRAP_WEBHOOK_ENABLED' => false]);
        $this->components->info('Webhook endpoint disabled.');

        return self::SUCCESS;
    }
}
