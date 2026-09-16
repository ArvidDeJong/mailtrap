<?php

namespace Darvis\Mailtrap\Console\Commands;

use Darvis\Mailtrap\Console\Commands\Concerns\WritesEnvironment;
use Darvis\Mailtrap\Services\MailtrapWebhookApi;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class MailtrapInstallCommand extends Command
{
    use WritesEnvironment;

    public const MAILTRAP_SMTP_HOST = 'live.smtp.mailtrap.io';

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
    protected $description = 'Set up the Mailtrap package step by step: database, API token, mailer, webhook, validation and inbox';

    private bool $databaseReachable = false;

    private ?string $apiToken = null;

    private bool $sendsThroughMailtrap = false;

    /**
     * Outcome per wizard step, shown in the summary.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private array $results = [];

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            return $this->installWithoutQuestions();
        }

        return $this->runWizard();
    }

    /**
     * The flag-driven install for deploy scripts. It never asks anything and
     * only touches what the flags name.
     */
    private function installWithoutQuestions(): int
    {
        $this->showCurrentState();
        $this->reportMissingConfigKeys();

        $this->publishConfig();

        if (! $this->option('skip-migrations')) {
            $this->call('migrate', ['--force' => true]);
        }

        if ($this->option('without-webhook')) {
            return $this->disableWebhook();
        }

        if (! $this->option('webhook')) {
            $this->components->warn('Webhook left unchanged. Pass --webhook or --without-webhook.');

            return self::SUCCESS;
        }

        $current = $this->currentApiToken();
        $token = $this->option('token') ? (string) $this->option('token') : $current;

        if ($token !== null && $token !== $current) {
            $this->saveEnvironment(['MAILTRAP_API_TOKEN' => $token], ['manta_mailtrap.api.token' => $token]);
        }

        return $this->call('mailtrap:webhook', array_filter([
            'url' => $this->option('url'),
            '--token' => $token,
            '--replace' => $this->option('replace'),
            '--no-interaction' => true,
        ]));
    }

    /**
     * Walk a first-time user through every setting that decides whether mail
     * is sent, logged and tracked correctly. Each answer is written to .env as
     * soon as it is given, so stopping halfway keeps the finished steps.
     */
    private function runWizard(): int
    {
        intro(' darvis/mailtrap setup ');

        note(implode("\n", [
            'This wizard sets up the package in 8 short steps and explains each one.',
            'Every answer is saved to .env right away. You can run it again at any time',
            'with php artisan mailtrap:install to check or change a setting.',
        ]));

        $steps = [
            'Check the basics' => fn (): bool => $this->checkBasics(),
            'Database tables' => fn (): bool => $this->stepDatabase(),
            'Mailtrap API token' => fn (): bool => $this->stepApiToken(),
            'Sending mail' => fn (): bool => $this->stepSending(),
            'Webhook for delivery events' => fn (): bool => $this->stepWebhook(),
            'Address validation' => fn (): bool => $this->stepValidation(),
            'Inbox page' => fn (): bool => $this->stepInbox(),
            'Test mail' => fn (): bool => $this->stepTestMail(),
        ];

        $number = 0;

        foreach ($steps as $title => $step) {
            $number++;
            $this->newLine();
            $this->line("<fg=cyan;options=bold>Step {$number} of ".count($steps)." · {$title}</>");

            if (! $step()) {
                $this->renderSummary();

                return self::FAILURE;
            }
        }

        $this->publishConfig();
        $this->renderSummary();

        return self::SUCCESS;
    }

    private function checkBasics(): bool
    {
        if (! is_file($this->laravel->environmentFilePath())) {
            $this->components->error('There is no .env file. Run "cp .env.example .env" and "php artisan key:generate" first, then start this wizard again.');
            $this->result('Basics', 'fail', 'no .env file');

            return false;
        }

        $appUrl = (string) config('app.url');

        try {
            DB::connection()->getPdo();
            $this->databaseReachable = true;
        } catch (Throwable $e) {
            $databaseError = $e->getMessage();
        }

        $this->components->twoColumnDetail('.env file', '<fg=green>found</>');
        $this->components->twoColumnDetail('APP_URL', $appUrl === '' ? '<fg=yellow>not set</>' : $appUrl.($this->isLocalUrl($appUrl) ? ' <fg=yellow>(local)</>' : ''));
        $this->components->twoColumnDetail('Database', $this->databaseReachable ? '<fg=green>connected</>' : '<fg=red>cannot connect</>');
        $this->components->twoColumnDetail('Current mailer', (string) config('mail.default'));
        $this->components->twoColumnDetail('Livewire (for the inbox page)', $this->inboxAvailable() ? 'installed' : 'not installed');
        $this->components->twoColumnDetail('config/manta_mailtrap.php', file_exists(config_path('manta_mailtrap.php')) ? 'published' : 'package defaults');

        if (! $this->databaseReachable) {
            $this->components->warn('The database is not reachable: '.($databaseError ?? 'unknown error').'. Check the DB_* values in .env. The wizard continues, but skips the steps that need the database.');
        }

        if ($appUrl === '' || $this->isLocalUrl($appUrl)) {
            $this->components->warn('APP_URL points at this computer. That is fine for development, but Mailtrap cannot send webhook events here. Step 5 explains what to do.');
        }

        $this->reportMissingConfigKeys();

        $this->result('Basics', 'ok', $this->databaseReachable ? 'environment checked' : 'database not reachable');

        return true;
    }

    private function stepDatabase(): bool
    {
        if ($this->option('skip-migrations')) {
            $this->result('Database tables', 'skip', 'skipped with --skip-migrations');

            return true;
        }

        if (! $this->databaseReachable) {
            $this->result('Database tables', 'todo', 'fix the database connection, then run php artisan migrate');

            return true;
        }

        note('The package stores every outgoing mail in mail_logs and a verdict per address in email_validations. A migration creates those tables.');

        if (! confirm('Create the tables now?', true, hint: 'Runs php artisan migrate, which also runs any other pending migrations of your app.')) {
            $this->result('Database tables', 'todo', 'run php artisan migrate');

            return true;
        }

        $this->call('migrate', ['--force' => true]);
        $this->result('Database tables', 'ok', 'migrations ran');

        return true;
    }

    private function stepApiToken(): bool
    {
        note(implode("\n", [
            'Mailtrap uses two different tokens. This step asks for the first one.',
            '',
            '  Account API token (this step): lets the package create the webhook.',
            '    1. Log in at https://mailtrap.io',
            '    2. Go to Settings → API Tokens → Add Token',
            '    3. Give the token Admin access to your account',
            '',
            '  Sending domain token (the next step): the SMTP password for sending mail.',
            'You only need this first token when this site receives the webhook.',
        ]));

        $current = $this->currentApiToken();
        $token = $this->option('token') ? (string) $this->option('token') : null;

        if ($token === null && $current !== null && confirm('Keep the current API token (…'.substr($current, -4).')?', true)) {
            $token = $current;
        }

        while (true) {
            $token ??= trim(password('Paste your Mailtrap API token', hint: 'Leave empty to skip this step for now.')) ?: null;

            if ($token === null) {
                $this->result('API token', 'todo', 'no token; run the wizard again once you have one');

                return true;
            }

            $error = $this->verifyApiToken($token);

            if ($error === null) {
                break;
            }

            $this->components->error($error);

            if (! confirm('Try another token?', true)) {
                $this->result('API token', 'todo', 'the token was not accepted by Mailtrap');

                return true;
            }

            $token = null;
        }

        if ($token !== $current) {
            $this->saveEnvironment(['MAILTRAP_API_TOKEN' => $token], ['manta_mailtrap.api.token' => $token]);
        }

        $this->apiToken = $token;
        $this->components->info('Mailtrap accepted the token.');
        $this->result('API token', 'ok', 'verified with Mailtrap');

        return true;
    }

    private function stepSending(): bool
    {
        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');

        if ($mailer === 'smtp' && str_ends_with($host, 'smtp.mailtrap.io')) {
            $this->sendsThroughMailtrap = true;
            $this->components->info("This site already sends through Mailtrap ({$host}).");
            $this->result('Sending mail', 'ok', "through Mailtrap ({$host})");

            return true;
        }

        note(implode("\n", [
            'The package works with every mailer: it checks and logs each mail on its way out.',
            'If this site sends through Mailtrap, the wizard fills in the SMTP settings for you.',
        ]));

        $choice = select('How does this site send mail?', [
            'mailtrap' => 'Through Mailtrap Email Sending',
            'keep' => "Keep my current mail settings (mailer: {$mailer})",
        ], 'mailtrap');

        if ($choice === 'keep') {
            $this->result('Sending mail', 'ok', "kept mailer {$mailer}");

            return true;
        }

        note(implode("\n", [
            'Sending needs the token of your sending domain, not the account API token from the previous step.',
            'Mailtrap only accepts a token with admin access to the domain as SMTP password.',
            '  1. In Mailtrap, go to Sending Domains and open your domain',
            '  2. Open the Integration tab and choose SMTP',
            '  3. Copy the Password shown there (it is the domain\'s API token)',
        ]));

        $smtpPassword = trim(password(
            'Paste the SMTP password of your sending domain',
            hint: 'Leave empty to keep your current mail settings for now.',
        ));

        if ($smtpPassword === '') {
            $this->result('Sending mail', 'todo', 'no sending domain token; mail settings left unchanged');

            return true;
        }

        $currentFrom = (string) config('mail.from.address');

        $from = text(
            'Sender address (the From of every mail)',
            placeholder: 'noreply@yourdomain.com',
            default: $currentFrom === 'hello@example.com' ? '' : $currentFrom,
            required: true,
            validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.',
            hint: 'Must be on a domain you verified in Mailtrap under Sending Domains, or Mailtrap refuses the mail.',
        );

        $variables = [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => self::MAILTRAP_SMTP_HOST,
            'MAIL_PORT' => '587',
            'MAIL_USERNAME' => 'api',
            'MAIL_PASSWORD' => $smtpPassword,
            'MAIL_FROM_ADDRESS' => $from,
        ];

        // Leftovers from a previous provider, such as MAIL_SCHEME=smtps, break
        // the STARTTLS connection on port 587.
        if ($this->environmentFile()->get('MAIL_SCHEME') !== null) {
            $variables['MAIL_SCHEME'] = 'smtp';
        }

        if ($this->environmentFile()->get('MAIL_ENCRYPTION') !== null) {
            $variables['MAIL_ENCRYPTION'] = 'tls';
        }

        $this->saveEnvironment($variables, [
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => self::MAILTRAP_SMTP_HOST,
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'api',
            'mail.mailers.smtp.password' => $smtpPassword,
            'mail.mailers.smtp.scheme' => isset($variables['MAIL_SCHEME']) ? 'smtp' : config('mail.mailers.smtp.scheme'),
            'mail.mailers.smtp.encryption' => isset($variables['MAIL_ENCRYPTION']) ? 'tls' : config('mail.mailers.smtp.encryption'),
            'mail.from.address' => $from,
        ]);

        Mail::purge('smtp');

        $this->sendsThroughMailtrap = true;
        $this->components->info('Mail settings written to .env.');

        if ($this->laravel->environment('local')) {
            $this->components->warn('This is live sending: mail from this computer now reaches real recipients.');
        }

        $this->result('Sending mail', 'ok', 'through Mailtrap, from '.$from);

        return true;
    }

    private function stepWebhook(): bool
    {
        if ($this->option('without-webhook') || ! $this->sendsThroughMailtrap) {
            note('Mailtrap only sends events for mail that goes through Mailtrap, so this site does not need the webhook. The endpoint is switched off, so the site exposes no unused URL.');
            $this->disableWebhook();
            $this->result('Webhook', 'skip', 'endpoint switched off');

            return true;
        }

        if ($this->apiToken === null) {
            $this->components->warn('Creating the webhook needs the API token from step 3.');
            $this->result('Webhook', 'todo', 'needs an API token first');

            return true;
        }

        note(implode("\n", [
            'With a webhook, Mailtrap tells this site what happened to each mail:',
            'delivered, opened, bounced or marked as spam. The mail log shows it, and',
            'addresses that bounce are marked invalid. Mailtrap signs every call with a',
            'secret, which the wizard stores in .env.',
        ]));

        if (! $this->option('webhook') && ! confirm('Set up the webhook?', true)) {
            $this->disableWebhook();
            $this->result('Webhook', 'skip', 'endpoint switched off');

            return true;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $default = (string) ($this->option('url') ?: $appUrl.'/api/webhooks/mailtrap');
        $showSecret = false;

        if ($this->isLocalUrl($default)) {
            note(implode("\n", [
                "{$default} only exists on this computer, so Mailtrap cannot reach it.",
                'The best way: run this wizard again on the live server.',
                'Or enter the live URL now; the secret is then shown for you to copy to the .env on that server.',
            ]));

            if (select('What do you want to do?', [
                'later' => 'Skip, I will run the wizard on the live server',
                'show' => 'Enter the live URL and show the secret',
            ], 'later') === 'later') {
                $this->result('Webhook', 'todo', 'run php artisan mailtrap:install on the live server');

                return true;
            }

            $default = '';
            $showSecret = true;
        }

        $url = text(
            'Public webhook URL',
            placeholder: 'https://yourdomain.com/api/webhooks/mailtrap',
            default: $default,
            required: true,
            validate: fn (string $value): ?string => $this->validateWebhookUrl($value),
            hint: 'Mailtrap sends the events to this address.',
        );

        $appHost = (string) parse_url($appUrl, PHP_URL_HOST);
        $showSecret = $showSecret || ($appHost !== '' && $appHost !== parse_url($url, PHP_URL_HOST));

        $exitCode = $this->call('mailtrap:webhook', array_filter([
            'url' => $url,
            '--token' => $this->apiToken,
            '--replace' => $this->option('replace'),
            '--show' => $showSecret,
        ]));

        if ($exitCode !== self::SUCCESS) {
            $this->result('Webhook', 'todo', 'not created, see the error above');

            return true;
        }

        $this->result('Webhook', 'ok', $showSecret ? 'created; copy the secret to the live .env' : 'created and secret saved');

        return true;
    }

    private function stepValidation(): bool
    {
        note(implode("\n", [
            'Before a mail goes out, the package can check each recipient: is the address',
            'well-formed, and does its domain accept mail? Sending to dead addresses hurts',
            'your sender reputation, so bad addresses are blocked by default.',
        ]));

        $current = ! config('manta_mailtrap.validation.enabled', true)
            ? 'off'
            : (config('manta_mailtrap.validation.block_invalid', true) ? 'block' : 'log');

        $choice = select('How should recipients be checked?', [
            'block' => 'Check and block bad addresses (recommended)',
            'log' => 'Check, but still send; only log the problem',
            'off' => 'Do not check (no DNS lookups while sending)',
        ], $current);

        if ($choice !== $current) {
            $this->saveEnvironment([
                'MAILTRAP_VALIDATION_ENABLED' => $choice !== 'off',
                'MAILTRAP_BLOCK_INVALID_EMAILS' => $choice !== 'log',
            ], [
                'manta_mailtrap.validation.enabled' => $choice !== 'off',
                'manta_mailtrap.validation.block_invalid' => $choice !== 'log',
            ]);
        }

        $this->result('Address validation', 'ok', match ($choice) {
            'block' => 'check and block',
            'log' => 'check and log only',
            default => 'off',
        });

        return true;
    }

    private function stepInbox(): bool
    {
        if (! $this->inboxAvailable()) {
            note('The inbox page needs livewire/livewire and livewire/flux, which this app does not have (or does not load). Skipped.');
            $this->result('Inbox page', 'skip', 'Livewire not installed');

            return true;
        }

        $path = '/'.ltrim((string) config('manta_mailtrap.ui.route', 'mailtrap'), '/');

        note(implode("\n", [
            "The inbox page at {$path} lists every outgoing mail with its recipients and",
            'subject. That is personal data, so the page must not be public.',
        ]));

        $middleware = (array) config('manta_mailtrap.ui.middleware', ['web']);
        $current = ! config('manta_mailtrap.ui.enabled', true)
            ? 'off'
            : ($middleware === ['web'] || $middleware === ['web', 'auth'] ? 'auth' : 'custom');

        $choice = select('Who may open the inbox?', [
            'auth' => 'Only logged-in users (middleware web, auth)',
            'custom' => 'Custom middleware, e.g. for admins only',
            'off' => 'Nobody, switch the inbox off',
        ], $current);

        if ($choice === 'off') {
            $this->saveEnvironment(['MAILTRAP_UI_ENABLED' => false], ['manta_mailtrap.ui.enabled' => false]);
            $this->result('Inbox page', 'skip', 'switched off');

            return true;
        }

        $list = $choice === 'auth' ? 'web,auth' : text(
            'Middleware, separated by commas',
            placeholder: 'web,auth,admin',
            default: implode(',', $middleware),
            required: true,
            hint: 'Include web, plus the middleware that only lets the right users in.',
        );

        $list = implode(',', array_values(array_filter(array_map('trim', explode(',', $list)))));

        $this->saveEnvironment(
            ['MAILTRAP_UI_ENABLED' => true, 'MAILTRAP_UI_MIDDLEWARE' => $list],
            ['manta_mailtrap.ui.enabled' => true, 'manta_mailtrap.ui.middleware' => explode(',', $list)],
        );

        if (str_contains(",{$list},", ',auth,') && ! Route::has('login')) {
            $this->components->warn('This app has no route named "login", so a guest gets an error page instead of a login form.');
        }

        $layout = (string) config('manta_mailtrap.ui.layout', 'components.layouts.app');

        if (! view()->exists($layout)) {
            $layout = text(
                "The layout {$layout} does not exist. Which Blade layout do your pages use?",
                placeholder: 'components.layouts.app',
                validate: fn (string $value): ?string => $value === '' || view()->exists($value) ? null : "No view named {$value}.",
                hint: 'Dot notation, e.g. layouts.app for resources/views/layouts/app.blade.php. Leave empty to set it later.',
            );

            if ($layout !== '') {
                $this->saveEnvironment(['MAILTRAP_UI_LAYOUT' => $layout], ['manta_mailtrap.ui.layout' => $layout]);
            }
        }

        $this->addTailwindSource();

        $this->result('Inbox page', $layout === '' ? 'todo' : 'ok', $layout === '' ? "at {$path}; set MAILTRAP_UI_LAYOUT" : "at {$path} for {$list}");

        return true;
    }

    /**
     * Tailwind 4 only generates the classes it finds in the files it scans, so
     * without this line the inbox renders unstyled.
     */
    private function addTailwindSource(): void
    {
        $css = resource_path('css/app.css');
        $line = "@source '../../vendor/darvis/mailtrap/resources/views/**/*.blade.php';";

        if (! is_file($css)) {
            return;
        }

        $contents = (string) file_get_contents($css);

        if (str_contains($contents, 'vendor/darvis/mailtrap') || ! str_contains($contents, 'tailwindcss')) {
            return;
        }

        if (! confirm('Add the inbox views to Tailwind in resources/css/app.css?', true, hint: 'Without it the inbox has no styling. Run npm run build afterwards.')) {
            return;
        }

        $lines = explode("\n", $contents);
        $anchor = null;

        foreach ($lines as $index => $existing) {
            if (str_starts_with(trim($existing), '@source') || str_starts_with(trim($existing), '@import')) {
                $anchor = $index;
            }
        }

        array_splice($lines, $anchor === null ? 0 : $anchor + 1, 0, [$line]);
        file_put_contents($css, implode("\n", $lines));

        $this->components->info('Added to resources/css/app.css. Run npm run build to apply it.');
    }

    private function stepTestMail(): bool
    {
        if (! $this->mailLogTableExists()) {
            $this->result('Test mail', 'skip', 'the database tables are missing');

            return true;
        }

        if (! confirm('Send a test mail now to check that everything works?', true)) {
            $this->result('Test mail', 'skip', 'later: php artisan mailtrap:test you@example.com');

            return true;
        }

        $email = text(
            'Send the test mail to',
            placeholder: 'you@example.com',
            required: true,
            validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.',
        );

        if ($this->call('mailtrap:test', ['email' => $email]) !== self::SUCCESS) {
            $this->result('Test mail', 'todo', 'failed, see the error above');

            return true;
        }

        $this->result('Test mail', 'ok', "sent to {$email}");

        return true;
    }

    private function renderSummary(): void
    {
        $this->newLine();
        $this->line('<options=bold>Summary</>');

        foreach ($this->results as [$step, $status, $detail]) {
            $this->components->twoColumnDetail($step, match ($status) {
                'ok' => "<fg=green>✔</> {$detail}",
                'skip' => "<fg=gray>–</> {$detail}",
                'todo' => "<fg=yellow>!</> {$detail}",
                default => "<fg=red>✘</> {$detail}",
            });
        }

        $this->newLine();
        $this->components->bulletList([
            '.env is not in git: run this wizard on every server as well.',
            'Remove old logs daily: add Schedule::command(\'model:prune\', [\'--model\' => \\Darvis\\Mailtrap\\Models\\MailLog::class])->daily(); to routes/console.php',
            'Check the setup at any time: php artisan mailtrap:test you@example.com',
        ]);

        outro(collect($this->results)->contains(fn (array $result): bool => in_array($result[1], ['todo', 'fail'], true))
            ? 'Almost done: see the items marked with ! above.'
            : 'darvis/mailtrap is ready.');
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
     * Ask Mailtrap for the account's webhooks: that call needs the same admin
     * access as creating one, so it proves the token is usable. Returns the
     * error, or null when the token works.
     */
    private function verifyApiToken(string $token): ?string
    {
        try {
            spin(fn () => (new MailtrapWebhookApi($token))->all(), 'Checking the token with Mailtrap…');
        } catch (RuntimeException $e) {
            return $e->getMessage();
        } catch (Throwable $e) {
            return 'Could not reach Mailtrap: '.$e->getMessage();
        }

        return null;
    }

    private function validateWebhookUrl(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return 'Enter a full URL, starting with https://';
        }

        if ($this->isLocalUrl($url)) {
            return 'Mailtrap cannot reach this address. Enter the URL of the live site.';
        }

        return null;
    }

    private function isLocalUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host === ''
            || in_array($host, ['localhost', '127.0.0.1'], true)
            || (bool) preg_match('/\.(test|local|localhost)$/', $host);
    }

    /**
     * Same condition as the service provider: Livewire can be installed while
     * its provider is not loaded, and then the inbox is not registered.
     */
    private function inboxAvailable(): bool
    {
        return class_exists(Livewire::class) && $this->laravel->bound('livewire');
    }

    private function mailLogTableExists(): bool
    {
        if (! $this->databaseReachable) {
            return false;
        }

        try {
            return Schema::hasTable('mail_logs');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Write to .env and apply the same values to the running configuration,
     * so later steps (such as the test mail) already use them.
     *
     * @param  array<string, string|bool>  $variables
     * @param  array<string, mixed>  $config
     */
    private function saveEnvironment(array $variables, array $config): void
    {
        $this->writeEnvironment($variables);

        config()->set($config);
    }

    private function result(string $step, string $status, string $detail): void
    {
        $this->results[] = [$step, $status, $detail];
    }

    /**
     * The published config is never overwritten: it holds the app's own route,
     * middleware and layout. A published section replaces the package section
     * as a whole, so name the keys a newer package version added instead. The
     * code falls back to defaults for them, so this is a notice, not an error.
     */
    private function reportMissingConfigKeys(): void
    {
        $published = config_path('manta_mailtrap.php');

        if (! file_exists($published)) {
            return;
        }

        $missing = array_values(array_diff(
            $this->configKeys(__DIR__.'/../../../config/manta_mailtrap.php'),
            $this->configKeys($published),
        ));

        if ($missing === []) {
            return;
        }

        $this->components->warn('config/manta_mailtrap.php was published from an older version and lacks these keys. The package uses their defaults; copy them from vendor/darvis/mailtrap/config/manta_mailtrap.php to change them:');
        $this->components->bulletList($missing);
    }

    /**
     * @return array<int, string>
     */
    private function configKeys(string $path): array
    {
        try {
            $config = require $path;
        } catch (Throwable) {
            return [];
        }

        if (! is_array($config)) {
            return [];
        }

        // List values such as ui.middleware.0 count as their parent key.
        return array_values(array_unique(array_map(
            fn (string $key): string => (string) preg_replace('/\.\d+(\..*)?$/', '', $key),
            array_keys(Arr::dot($config)),
        )));
    }

    private function publishConfig(): void
    {
        if ($this->option('config') && ! file_exists(config_path('manta_mailtrap.php'))) {
            $this->callSilently('vendor:publish', ['--tag' => 'mailtrap-config']);
            $this->components->info('Config published.');
        }
    }

    private function disableWebhook(): int
    {
        if ($this->environmentFile()->get('MAILTRAP_WEBHOOK_ENABLED') !== 'false') {
            $this->saveEnvironment(['MAILTRAP_WEBHOOK_ENABLED' => false], ['manta_mailtrap.webhook.enabled' => false]);
        }

        $this->components->info('Webhook endpoint disabled.');

        return self::SUCCESS;
    }
}
