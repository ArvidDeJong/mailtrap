<?php

use Darvis\Mailtrap\Support\EnvironmentFile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const NEW_SECRET = 'abcdefabcdefabcdefabcdefabcdef12';

beforeEach(function (): void {
    $this->envDirectory = sys_get_temp_dir().'/mailtrap-env-'.uniqid();
    mkdir($this->envDirectory);
    file_put_contents($this->envDirectory.'/.env', "APP_NAME=Test\n# Mailtrap\nMAILTRAP_WEBHOOK_ENABLED=false\nMAILTRAP_WEBHOOK_SECRET=\n");

    $this->app->useEnvironmentPath($this->envDirectory);

    config()->set('app.url', 'https://example.com');
    config()->set('manta_mailtrap.api.token', 'api-token');
});

afterEach(function (): void {
    @unlink($this->envDirectory.'/.env');
    @rmdir($this->envDirectory);
});

function envContents(): string
{
    return (string) file_get_contents(test()->envDirectory.'/.env');
}

function fakeMailtrapApi(array $existing = [], int $createStatus = 200): void
{
    Http::fake([
        'mailtrap.io/api/webhooks/*' => Http::response(['data' => []]),
        'mailtrap.io/api/webhooks' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['data' => $existing])
            : Http::response(
                $createStatus === 200 ? ['data' => ['id' => 42, 'url' => $request['webhook']['url'], 'signing_secret' => NEW_SECRET]] : ['errors' => 'Access forbidden'],
                $createStatus,
            ),
    ]);
}

it('creates the webhook and writes its signing secret to .env', function (): void {
    fakeMailtrapApi();

    $this->artisan('mailtrap:webhook')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('Api-Token', 'api-token')
        && $request['webhook']['url'] === 'https://example.com/api/webhooks/mailtrap'
        && $request['webhook']['payload_format'] === 'json'
        && in_array('bounce', $request['webhook']['event_types'], true));

    expect(envContents())->toBe("APP_NAME=Test\n# Mailtrap\nMAILTRAP_WEBHOOK_ENABLED=true\nMAILTRAP_WEBHOOK_SECRET=".NEW_SECRET."\n")
        ->and(config('manta_mailtrap.webhook.secret'))->toBe(NEW_SECRET);
});

it('refuses a URL Mailtrap cannot reach', function (): void {
    Http::fake();
    config()->set('app.url', 'http://wijkhuis.test');

    $this->artisan('mailtrap:webhook')->assertFailed();

    Http::assertNothingSent();
});

it('keeps an existing webhook for the same URL unless told to replace it', function (): void {
    fakeMailtrapApi([['id' => 7, 'url' => 'https://example.com/api/webhooks/mailtrap']]);

    $this->artisan('mailtrap:webhook')
        ->expectsConfirmation('Webhook #7 already exists for this URL. Mailtrap cannot show its secret again. Replace it with a new one?', 'no')
        ->assertFailed();

    Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['POST', 'DELETE'], true));
    expect(envContents())->toContain("MAILTRAP_WEBHOOK_SECRET=\n");
});

it('replaces an existing webhook for the same URL', function (): void {
    fakeMailtrapApi([['id' => 7, 'url' => 'https://example.com/api/webhooks/mailtrap']]);

    $this->artisan('mailtrap:webhook', ['--replace' => true])->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/api/webhooks/7'));
    expect(envContents())->toContain('MAILTRAP_WEBHOOK_SECRET='.NEW_SECRET);
});

it('only prints the secret with --show', function (): void {
    fakeMailtrapApi();

    $this->artisan('mailtrap:webhook', ['url' => 'https://other.example.org/api/webhooks/mailtrap', '--show' => true])
        ->expectsOutputToContain('MAILTRAP_WEBHOOK_SECRET='.NEW_SECRET)
        ->assertSuccessful();

    expect(envContents())->toContain("MAILTRAP_WEBHOOK_SECRET=\n");
});

it('reports Mailtrap API errors', function (): void {
    fakeMailtrapApi(createStatus: 403);

    $this->artisan('mailtrap:webhook')
        ->expectsOutputToContain('needs admin access')
        ->assertFailed();

    expect(envContents())->toContain("MAILTRAP_WEBHOOK_SECRET=\n");
});

it('fails without an API token', function (): void {
    Http::fake();
    config()->set('manta_mailtrap.api.token', null);

    $this->artisan('mailtrap:webhook')->assertFailed();

    Http::assertNothingSent();
});

it('installs with a webhook in one non-interactive run', function (): void {
    fakeMailtrapApi();

    $this->artisan('mailtrap:install', ['--webhook' => true, '--skip-migrations' => true, '--no-interaction' => true])
        ->assertSuccessful();

    expect(envContents())->toContain("MAILTRAP_WEBHOOK_ENABLED=true\nMAILTRAP_WEBHOOK_SECRET=".NEW_SECRET);
});

it('reconfigures an existing site with a new token and a replaced webhook', function (): void {
    fakeMailtrapApi([['id' => 7, 'url' => 'https://example.com/api/webhooks/mailtrap']]);
    config()->set('manta_mailtrap.api.token', null);
    file_put_contents($this->envDirectory.'/.env', "MAILTRAP_API_TOKEN=old-token\nMAILTRAP_WEBHOOK_ENABLED=true\nMAILTRAP_WEBHOOK_SECRET=stale\n");

    $this->artisan('mailtrap:install', ['--webhook' => true, '--skip-migrations' => true])
        ->expectsOutputToContain('Reconfiguring darvis/mailtrap')
        ->expectsConfirmation('Publish config/manta_mailtrap.php?', 'no')
        ->expectsConfirmation('Keep the current API token (…oken)?', 'no')
        ->expectsQuestion('Mailtrap API token (Settings → API Tokens, needs admin access)', 'new-token')
        ->expectsConfirmation('Webhook #7 already exists for this URL. Mailtrap cannot show its secret again. Replace it with a new one?', 'yes')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->hasHeader('Api-Token', 'new-token'));
    expect(envContents())->toBe("MAILTRAP_API_TOKEN=new-token\nMAILTRAP_WEBHOOK_ENABLED=true\nMAILTRAP_WEBHOOK_SECRET=".NEW_SECRET."\n");
});

it('disables the webhook endpoint when installing without one', function (): void {
    Http::fake();
    file_put_contents($this->envDirectory.'/.env', "APP_NAME=Test\n");

    $this->artisan('mailtrap:install', ['--without-webhook' => true, '--skip-migrations' => true, '--no-interaction' => true])
        ->assertSuccessful();

    Http::assertNothingSent();
    expect(envContents())->toBe("APP_NAME=Test\nMAILTRAP_WEBHOOK_ENABLED=false\n");
});

it('quotes environment values that need it and keeps dollar signs intact', function (): void {
    $file = new EnvironmentFile($this->envDirectory.'/.env');

    $file->set(['MAILTRAP_API_TOKEN' => 'a$1 b']);
    $file->set(['MAILTRAP_API_TOKEN' => 'c$1d']);

    expect(envContents())->toContain("MAILTRAP_API_TOKEN=\"c$1d\"\n")
        ->and(substr_count(envContents(), 'MAILTRAP_API_TOKEN'))->toBe(1);
});
