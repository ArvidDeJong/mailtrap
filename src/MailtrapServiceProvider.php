<?php

namespace Darvis\Mailtrap;

use Darvis\Mailtrap\Console\Commands\MailtrapInstallCommand;
use Darvis\Mailtrap\Console\Commands\MailtrapTestCommand;
use Darvis\Mailtrap\Console\Commands\MailtrapWebhookCommand;
use Darvis\Mailtrap\Http\Middleware\AuthorizeInbox;
use Darvis\Mailtrap\Http\Middleware\VerifyMailtrapWebhookSignature;
use Darvis\Mailtrap\Livewire\MailtrapInbox;
use Darvis\Mailtrap\Providers\MailServiceProvider;
use Darvis\Mailtrap\Services\MailtrapService;
use Darvis\Mailtrap\Support\MailtrapConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class MailtrapServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the config before anything in boot() reads it; route and UI
        // registration both depend on these values being present.
        $this->mergeConfigFrom(
            __DIR__.'/../config/manta_mailtrap.php',
            'manta_mailtrap'
        );

        // Validation and logging of outgoing mail.
        $this->app->register(MailServiceProvider::class);

        // Deprecated: MailtrapService and the app('mailtrap') alias are removed in 2.0.
        $this->app->singleton(MailtrapService::class);
        $this->app->alias(MailtrapService::class, 'mailtrap');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerWebhookRoute();

        // Publish the migrations.
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'mailtrap-migrations');

        // Load the migrations automatically, so publishing them is optional.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register the Artisan commands (only relevant in the console).
        if ($this->app->runningInConsole()) {
            $this->commands([
                MailtrapInstallCommand::class,
                MailtrapTestCommand::class,
                MailtrapWebhookCommand::class,
            ]);
        }

        // Publish the config file.
        $this->publishes([
            __DIR__.'/../config/manta_mailtrap.php' => config_path('manta_mailtrap.php'),
        ], 'mailtrap-config');

        // Load the package views and translations under the "mailtrap" namespace.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mailtrap');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'mailtrap');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/mailtrap'),
        ], 'mailtrap-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/mailtrap'),
        ], 'mailtrap-views');

        $this->registerInboxUi();
    }

    /**
     * Register the Mailtrap webhook endpoint.
     *
     * The route is only registered when the webhook is enabled, so an app that
     * does not receive Mailtrap events exposes no endpoint at all. Signature
     * verification runs as route middleware rather than inside the controller.
     */
    protected function registerWebhookRoute(): void
    {
        if (! MailtrapConfig::webhookEnabled()) {
            return;
        }

        Route::prefix('api')
            ->middleware(['api', VerifyMailtrapWebhookSignature::class])
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Register the Livewire/Flux inbox component and its route.
     *
     * The UI is optional and only registered when Livewire is loaded in the
     * host application and the UI is enabled in the config. Checking the
     * container rather than class_exists() also covers an app that has the
     * Livewire package installed but its provider excluded from discovery.
     */
    protected function registerInboxUi(): void
    {
        if (! MailtrapConfig::uiEnabled()) {
            return;
        }

        if (! class_exists(Livewire::class) || ! $this->app->bound('livewire')) {
            return;
        }

        Livewire::component('mailtrap-inbox', MailtrapInbox::class);

        // Livewire update requests do not run the route middleware again, unless
        // it is persistent. Without this the gate would only guard the page load.
        Livewire::addPersistentMiddleware([AuthorizeInbox::class]);

        // Register the route once every provider has booted, so Livewire's
        // Route::livewire() macro is guaranteed to be available.
        $this->app->booted(function (): void {
            $this->defineInboxGate();

            $path = MailtrapConfig::uiPath();
            $middleware = MailtrapConfig::uiMiddleware();

            $route = Route::hasMacro('livewire')
                ? Route::livewire($path, MailtrapInbox::class)
                : Route::get($path, MailtrapInbox::class);

            // The gate comes last, so it sees the user the configured middleware logged in.
            $route->middleware([...$middleware, AuthorizeInbox::class])->name('mailtrap.inbox');
        });
    }

    /**
     * Define the `viewMailtrap` gate, unless the host application has its own.
     *
     * The default only allows the local environment. Runs after every provider
     * has booted, so a gate from the host's AppServiceProvider is seen here. The
     * user is nullable, otherwise Laravel never calls the gate for a guest.
     */
    protected function defineInboxGate(): void
    {
        if (Gate::has(AuthorizeInbox::ABILITY)) {
            return;
        }

        Gate::define(
            AuthorizeInbox::ABILITY,
            fn (?Authenticatable $user = null): bool => $this->app->environment('local'),
        );
    }
}
