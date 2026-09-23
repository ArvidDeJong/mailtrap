<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const WIZARD_SECRET = 'fedcbafedcbafedcbafedcbafedcba98';

const MAILER_CHOICES = [
    'mailtrap' => 'Through Mailtrap Email Sending',
    'keep' => 'Keep my current mail settings (mailer: log)',
];

const VALIDATION_CHOICES = [
    'block' => 'Check and block bad addresses (recommended)',
    'log' => 'Check, but still send; only log the problem',
    'off' => 'Do not check (no DNS lookups while sending)',
];

const RETENTION_CHOICES = [
    '30' => '30 days (default)',
    '90' => '90 days',
    '365' => '1 year',
    '0' => 'Keep everything',
];

const INBOX_CHOICES = [
    'auth' => 'Only logged-in users (middleware web, auth)',
    'custom' => 'Custom middleware, e.g. for admins only',
    'off' => 'Nobody, switch the inbox off',
];

beforeEach(function (): void {
    $this->envDirectory = sys_get_temp_dir().'/mailtrap-wizard-'.uniqid();
    mkdir($this->envDirectory);
    file_put_contents($this->envDirectory.'/.env', "APP_NAME=Test\nMAIL_MAILER=log\n");

    $this->app->useEnvironmentPath($this->envDirectory);

    config()->set('app.url', 'https://example.com');
    config()->set('mail.default', 'log');
    config()->set('manta_mailtrap.api.token', null);
    // A fresh install has no signing secret; TestCase sets one for the webhook tests.
    config()->set('manta_mailtrap.webhook.secret', null);
});

afterEach(function (): void {
    @unlink($this->envDirectory.'/.env');
    @rmdir($this->envDirectory);
});

function wizardEnv(): string
{
    return (string) file_get_contents(test()->envDirectory.'/.env');
}

/**
 * Fake the Mailtrap webhooks API. Only the tokens in $validTokens are accepted.
 */
function fakeWizardMailtrapApi(array $validTokens = ['good-token']): void
{
    Http::fake([
        'mailtrap.io/api/webhooks' => function (Request $request) use ($validTokens) {
            if (! in_array($request->header('Api-Token')[0] ?? null, $validTokens, true)) {
                return Http::response(['errors' => 'Unauthorized'], 401);
            }

            return $request->method() === 'GET'
                ? Http::response(['data' => []])
                : Http::response(['data' => ['id' => 42, 'url' => $request['webhook']['url'], 'signing_secret' => WIZARD_SECRET]]);
        },
    ]);
}

it('walks a live site through mailer, webhook, validation and inbox', function (): void {
    fakeWizardMailtrapApi();

    $this->artisan('mailtrap:install', ['--skip-migrations' => true])
        ->expectsOutputToContain('Step 1 of 9 · Check the basics')
        ->expectsQuestion('Paste your Mailtrap API token', 'good-token')
        ->expectsChoice('How does this site send mail?', 'mailtrap', MAILER_CHOICES)
        ->expectsQuestion('Paste the SMTP password of your sending domain', 'domain-token')
        ->expectsQuestion('Sender address (the From of every mail)', 'noreply@example.com')
        ->expectsQuestion('Sender name (shown next to the address)', 'Acme')
        ->expectsConfirmation('Set up the webhook?', 'yes')
        ->expectsQuestion('Public webhook URL', 'https://example.com/api/webhooks/mailtrap')
        ->expectsChoice('How should recipients be checked?', 'block', VALIDATION_CHOICES)
        ->expectsChoice('How long should mail logs be kept?', '90', RETENTION_CHOICES)
        ->expectsChoice('Who may open the inbox?', 'auth', INBOX_CHOICES)
        ->expectsQuestion('The layout components.layouts.app does not exist. Which Blade layout do your pages use?', '')
        ->expectsConfirmation('Send a test mail now to check that everything works?', 'no')
        ->expectsOutputToContain("Gate::define('viewMailtrap', fn (?User \$user) => \$user?->is_admin === true);")
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request['webhook']['url'] === 'https://example.com/api/webhooks/mailtrap');

    expect(wizardEnv())
        ->toContain("APP_NAME=Test\nMAIL_MAILER=smtp\n")
        ->toContain('MAILTRAP_API_TOKEN=good-token')
        ->toContain("MAIL_HOST=live.smtp.mailtrap.io\nMAIL_PORT=587\nMAIL_USERNAME=api\nMAIL_PASSWORD=domain-token\nMAIL_FROM_ADDRESS=noreply@example.com\nMAIL_FROM_NAME=Acme")
        ->toContain('MAILTRAP_CLEANUP_AFTER_DAYS=90')
        ->toContain("MAILTRAP_WEBHOOK_ENABLED=true\nMAILTRAP_WEBHOOK_SECRET=".WIZARD_SECRET)
        ->toContain("MAILTRAP_UI_ENABLED=true\nMAILTRAP_UI_MIDDLEWARE=web,auth")
        ->not->toContain('MAILTRAP_VALIDATION_ENABLED');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request['webhook']['sending_stream'] === 'transactional');

    expect(config('mail.mailers.smtp.host'))->toBe('live.smtp.mailtrap.io')
        ->and(config('mail.from.name'))->toBe('Acme')
        ->and(config('manta_mailtrap.logging.cleanup_after_days'))->toBe(90)
        ->and(config('mail.mailers.smtp.password'))->toBe('domain-token');
});

it('sets up sending through Mailtrap without an account API token', function (): void {
    fakeWizardMailtrapApi();

    $this->artisan('mailtrap:install', ['--skip-migrations' => true])
        ->expectsQuestion('Paste your Mailtrap API token', '')
        ->expectsChoice('How does this site send mail?', 'mailtrap', MAILER_CHOICES)
        ->expectsQuestion('Paste the SMTP password of your sending domain', 'domain-token')
        ->expectsQuestion('Sender address (the From of every mail)', 'noreply@example.com')
        ->expectsQuestion('Sender name (shown next to the address)', 'Acme')
        ->expectsQuestion('Paste the signing secret of the webhook', '')
        ->expectsChoice('How should recipients be checked?', 'block', VALIDATION_CHOICES)
        ->expectsChoice('How long should mail logs be kept?', '30', RETENTION_CHOICES)
        ->expectsChoice('Who may open the inbox?', 'off', INBOX_CHOICES)
        ->expectsConfirmation('Send a test mail now to check that everything works?', 'no')
        ->expectsOutputToContain('needs an API token first')
        ->assertSuccessful();

    expect(wizardEnv())
        ->toContain('MAIL_PASSWORD=domain-token')
        ->not->toContain('MAILTRAP_API_TOKEN')
        ->not->toContain('MAILTRAP_WEBHOOK_SECRET')
        ->not->toContain('MAILTRAP_CLEANUP_AFTER_DAYS');
});

it('asks for the signing secret of a dashboard webhook when there is no API token', function (): void {
    fakeWizardMailtrapApi();

    $this->artisan('mailtrap:install', ['--skip-migrations' => true])
        ->expectsQuestion('Paste your Mailtrap API token', '')
        ->expectsChoice('How does this site send mail?', 'mailtrap', MAILER_CHOICES)
        ->expectsQuestion('Paste the SMTP password of your sending domain', 'domain-token')
        ->expectsQuestion('Sender address (the From of every mail)', 'noreply@example.com')
        ->expectsQuestion('Sender name (shown next to the address)', 'Acme')
        ->expectsQuestion('Paste the signing secret of the webhook', WIZARD_SECRET)
        ->expectsChoice('How should recipients be checked?', 'block', VALIDATION_CHOICES)
        ->expectsChoice('How long should mail logs be kept?', '30', RETENTION_CHOICES)
        ->expectsChoice('Who may open the inbox?', 'off', INBOX_CHOICES)
        ->expectsConfirmation('Send a test mail now to check that everything works?', 'no')
        ->expectsOutputToContain('signing secret saved')
        ->assertSuccessful();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    expect(wizardEnv())->toContain("MAILTRAP_WEBHOOK_ENABLED=true\nMAILTRAP_WEBHOOK_SECRET=".WIZARD_SECRET)
        ->and(config('manta_mailtrap.webhook.secret'))->toBe(WIZARD_SECRET);
});

it('asks for another token when Mailtrap rejects one', function (): void {
    fakeWizardMailtrapApi();

    $this->artisan('mailtrap:install', ['--skip-migrations' => true])
        ->expectsQuestion('Paste your Mailtrap API token', 'bad-token')
        ->expectsOutputToContain('Check MAILTRAP_API_TOKEN')
        ->expectsConfirmation('Try another token?', 'yes')
        ->expectsQuestion('Paste your Mailtrap API token', 'good-token')
        ->expectsChoice('How does this site send mail?', 'keep', MAILER_CHOICES)
        ->expectsChoice('How should recipients be checked?', 'off', VALIDATION_CHOICES)
        ->expectsChoice('How long should mail logs be kept?', '30', RETENTION_CHOICES)
        ->expectsChoice('Who may open the inbox?', 'off', INBOX_CHOICES)
        ->expectsConfirmation('Send a test mail now to check that everything works?', 'no')
        ->assertSuccessful();

    expect(wizardEnv())
        ->toContain('MAILTRAP_API_TOKEN=good-token')
        ->not->toContain('bad-token')
        ->toContain('MAIL_MAILER=log')
        ->toContain('MAILTRAP_WEBHOOK_ENABLED=false')
        ->toContain("MAILTRAP_VALIDATION_ENABLED=false\nMAILTRAP_BLOCK_INVALID_EMAILS=true")
        ->toContain('MAILTRAP_UI_ENABLED=false');
});

it('keeps the current token and leaves the webhook for the live server on a local site', function (): void {
    fakeWizardMailtrapApi();
    config()->set('app.url', 'http://wijkhuis.test');
    file_put_contents($this->envDirectory.'/.env', "MAILTRAP_API_TOKEN=good-token\nMAIL_MAILER=log\n");

    $this->artisan('mailtrap:install', ['--skip-migrations' => true])
        ->expectsOutputToContain('APP_URL points at this computer')
        ->expectsConfirmation('Keep the current API token (…oken)?', 'yes')
        ->expectsChoice('How does this site send mail?', 'mailtrap', MAILER_CHOICES)
        ->expectsQuestion('Paste the SMTP password of your sending domain', 'domain-token')
        ->expectsQuestion('Sender address (the From of every mail)', 'noreply@example.com')
        ->expectsQuestion('Sender name (shown next to the address)', 'Acme')
        ->expectsConfirmation('Set up the webhook?', 'yes')
        ->expectsChoice('What do you want to do?', 'later', [
            'later' => 'Skip, I will run the wizard on the live server',
            'show' => 'Enter the live URL and show the secret',
        ])
        ->expectsChoice('How should recipients be checked?', 'block', VALIDATION_CHOICES)
        ->expectsChoice('How long should mail logs be kept?', '30', RETENTION_CHOICES)
        ->expectsChoice('Who may open the inbox?', 'off', INBOX_CHOICES)
        ->expectsConfirmation('Send a test mail now to check that everything works?', 'no')
        ->expectsOutputToContain('run php artisan mailtrap:install on the live server')
        ->assertSuccessful();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    expect(wizardEnv())->not->toContain('MAILTRAP_WEBHOOK_SECRET');
});

it('does not treat the Email Testing sandbox as sending through Mailtrap', function (): void {
    fakeWizardMailtrapApi();
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', 'sandbox.smtp.mailtrap.io');

    $this->artisan('mailtrap:install', ['--skip-migrations' => true])
        ->expectsQuestion('Paste your Mailtrap API token', 'good-token')
        ->expectsChoice('How does this site send mail?', 'keep', [
            'mailtrap' => 'Through Mailtrap Email Sending',
            'keep' => 'Keep my current mail settings (mailer: smtp)',
        ])
        ->expectsChoice('How should recipients be checked?', 'block', VALIDATION_CHOICES)
        ->expectsChoice('How long should mail logs be kept?', '30', RETENTION_CHOICES)
        ->expectsChoice('Who may open the inbox?', 'off', INBOX_CHOICES)
        ->expectsConfirmation('Send a test mail now to check that everything works?', 'no')
        ->assertSuccessful();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    expect(wizardEnv())->toContain('MAILTRAP_WEBHOOK_ENABLED=false');
});

it('stops when the app has no .env file', function (): void {
    Http::fake();
    unlink($this->envDirectory.'/.env');

    $this->artisan('mailtrap:install')
        ->expectsOutputToContain('There is no .env file')
        ->assertFailed();

    Http::assertNothingSent();
});
