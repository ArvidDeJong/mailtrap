<?php

namespace Darvis\Mailtrap;

use Illuminate\Support\ServiceProvider;
use Darvis\Mailtrap\Services\MailtrapService;
use Darvis\Mailtrap\Providers\MailServiceProvider;

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
            return new MailtrapService();
        });
        
        // Alias voor gemakkelijke toegang
        $this->app->alias(MailtrapService::class, 'mailtrap');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load API routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Publiceer migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'mailtrap-migrations');

        // Load migrations automatisch als package wordt gebruikt
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publiceer config bestanden
        $this->publishes([
            __DIR__.'/../config/manta_mailtrap.php' => config_path('manta_mailtrap.php'),
        ], 'mailtrap-config');

        // Merge config met applicatie config
        $this->mergeConfigFrom(
            __DIR__.'/../config/manta_mailtrap.php', 'manta_mailtrap'
        );
    }
}
