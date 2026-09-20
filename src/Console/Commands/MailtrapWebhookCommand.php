<?php

namespace Darvis\Mailtrap\Console\Commands;

use Darvis\Mailtrap\Console\Commands\Concerns\WritesEnvironment;
use Darvis\Mailtrap\Services\MailtrapWebhookApi;
use Darvis\Mailtrap\Support\MailtrapConfig;
use Illuminate\Console\Command;
use RuntimeException;

class MailtrapWebhookCommand extends Command
{
    use WritesEnvironment;

    /**
     * @var string
     */
    protected $signature = 'mailtrap:webhook
        {url? : Public webhook URL (defaults to APP_URL/api/webhooks/mailtrap)}
        {--token= : Mailtrap API token with admin access (defaults to MAILTRAP_API_TOKEN)}
        {--stream=transactional : Sending stream to subscribe to: transactional or bulk}
        {--domain-id= : Only receive events for this Mailtrap sending domain}
        {--replace : Delete an existing webhook for the same URL without asking}
        {--show : Print the signing secret instead of writing it to .env}';

    /**
     * @var string
     */
    protected $description = 'Create the Mailtrap webhook and store its signing secret in .env';

    public function handle(): int
    {
        $url = $this->webhookUrl();

        if ($url === null) {
            return self::FAILURE;
        }

        $token = $this->apiToken();

        if ($token === null) {
            $this->components->error('No Mailtrap API token. Set MAILTRAP_API_TOKEN or pass --token.');

            return self::FAILURE;
        }

        $stream = (string) $this->option('stream');

        if (! in_array($stream, ['transactional', 'bulk'], true)) {
            $this->components->error('--stream must be transactional or bulk.');

            return self::FAILURE;
        }

        $api = new MailtrapWebhookApi($token);

        try {
            if (! $this->removeExistingWebhook($api, $url)) {
                return self::FAILURE;
            }

            $webhook = $api->create($url, $stream, $this->option('domain-id') ? (int) $this->option('domain-id') : null);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Webhook', '#'.($webhook['id'] ?? '?'));
        $this->components->twoColumnDetail('URL', $url);
        $this->components->twoColumnDetail('Stream', $stream);
        $this->components->twoColumnDetail('Events', implode(', ', MailtrapWebhookApi::EVENT_TYPES));

        if ($this->option('show')) {
            $this->newLine();
            $this->line('  MAILTRAP_WEBHOOK_ENABLED=true');
            $this->line('  MAILTRAP_WEBHOOK_SECRET='.$webhook['signing_secret']);
            $this->newLine();
            $this->components->warn('Mailtrap shows this secret only once. Put it in the .env of the server behind the URL.');

            return self::SUCCESS;
        }

        $this->writeEnvironment([
            'MAILTRAP_WEBHOOK_ENABLED' => true,
            'MAILTRAP_WEBHOOK_SECRET' => (string) $webhook['signing_secret'],
        ]);

        config()->set('manta_mailtrap.webhook.enabled', true);
        config()->set('manta_mailtrap.webhook.secret', $webhook['signing_secret']);

        $this->components->info('Webhook created and signing secret written to .env.');

        return self::SUCCESS;
    }

    /**
     * Resolve the public URL and refuse addresses Mailtrap cannot reach.
     */
    private function webhookUrl(): ?string
    {
        $url = $this->argument('url')
            ?: rtrim((string) config('app.url'), '/').'/api/webhooks/mailtrap';

        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true) || preg_match('/\.(test|local|localhost)$/', $host)) {
            $this->components->error("Mailtrap cannot reach {$url}. Pass the public URL, e.g. php artisan mailtrap:webhook https://example.com/api/webhooks/mailtrap");

            return null;
        }

        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! $this->option('show') && $appHost !== '' && $appHost !== $host) {
            $this->components->warn("The webhook points at {$host}, but the secret is written to the .env of {$appHost}. Use --show to copy it to the right server instead.");
        }

        return $url;
    }

    private function apiToken(): ?string
    {
        $token = $this->option('token')
            ?: MailtrapConfig::apiToken()
            ?: $this->environmentFile()->get('MAILTRAP_API_TOKEN');

        return $token ? (string) $token : null;
    }

    /**
     * Mailtrap never returns the secret of an existing webhook, so the only way
     * to a working secret is to replace the webhook. Returns false when the
     * user declines.
     */
    private function removeExistingWebhook(MailtrapWebhookApi $api, string $url): bool
    {
        $existing = $api->findByUrl($url);

        if ($existing === null) {
            return true;
        }

        $id = (int) $existing['id'];

        if (! $this->option('replace') && ! $this->confirm("Webhook #{$id} already exists for this URL. Mailtrap cannot show its secret again. Replace it with a new one?", false)) {
            $this->components->error("Kept webhook #{$id}. Re-run with --replace, or copy its secret from the Mailtrap dashboard.");

            return false;
        }

        $api->delete($id);
        $this->components->info("Deleted webhook #{$id}.");

        return true;
    }
}
