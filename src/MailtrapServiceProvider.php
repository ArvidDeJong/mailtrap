<?php

namespace Darvis\Mailtrap;

use Darvis\Mailtrap\Console\Commands\MailtrapTestCommand;
use Darvis\Mailtrap\Http\Middleware\VerifyMailtrapWebhookSignature;
use Darvis\Mailtrap\Livewire\MailtrapInbox;
use Darvis\Mailtrap\Providers\MailServiceProvider;
use Darvis\Mailtrap\Services\MailtrapService;
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

        // Registreer de MailServiceProvider
        $this->app->register(MailServiceProvider::class);

        // Bind de Mailtrap service in de container
        $this->app->singleton(MailtrapService::class, function ($app) {
            return new MailtrapService;
        });

        // Alias voor gemakkelijke toegang
        $this->app->alias(MailtrapService::class, 'mailtrap');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerWebhookRoute();

        // Publiceer migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'mailtrap-migrations');

        // Load migrations automatisch als package wordt gebruikt
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Registreer de Artisan-commando's (alleen relevant in de console).
        if ($this->app->runningInConsole()) {
            $this->commands([
                MailtrapTestCommand::class,
            ]);
        }

        // Publiceer config bestanden
        $this->publishes([
            __DIR__.'/../config/manta_mailtrap.php' => config_path('manta_mailtrap.php'),
        ], 'mailtrap-config');

        // Laad de package views onder de "mailtrap" namespace.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mailtrap');

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
        if (! config('manta_mailtrap.webhook.enabled', true)) {
            return;
        }

        Route::prefix('api')
            ->middleware(['api', VerifyMailtrapWebhookSignature::class])
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Registreer de Livewire/Flux inbox-component en bijbehorende route.
     *
     * De UI is optioneel en wordt alleen geregistreerd wanneer Livewire
     * aanwezig is in de host-applicatie en de UI in de config is ingeschakeld.
     */
    protected function registerInboxUi(): void
    {
        if (! config('manta_mailtrap.ui.enabled', true)) {
            return;
        }

        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('mailtrap-inbox', MailtrapInbox::class);

        // Routes pas registreren als alle providers gebooted zijn, zodat de
        // Route::livewire() macro van Livewire gegarandeerd beschikbaar is.
        $this->app->booted(function (): void {
            $path = '/'.ltrim((string) config('manta_mailtrap.ui.route', 'mailtrap'), '/');
            $middleware = config('manta_mailtrap.ui.middleware', ['web']);

            $route = Route::hasMacro('livewire')
                ? Route::livewire($path, MailtrapInbox::class)
                : Route::get($path, MailtrapInbox::class);

            $route->middleware($middleware)->name('mailtrap.inbox');
        });
    }
}
