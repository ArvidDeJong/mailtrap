<?php

namespace Darvis\Mailtrap;

use Darvis\Mailtrap\Console\Commands\MailtrapTestCommand;
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
        // Load API routes with proper API prefix and middleware
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');

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

        // Merge config met applicatie config
        $this->mergeConfigFrom(
            __DIR__.'/../config/manta_mailtrap.php',
            'manta_mailtrap'
        );

        // Laad de package views onder de "mailtrap" namespace.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mailtrap');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/mailtrap'),
        ], 'mailtrap-views');

        $this->registerInboxUi();
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
