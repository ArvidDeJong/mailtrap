<?php

namespace Darvis\Mailtrap\Console\Commands\Concerns;

use Darvis\Mailtrap\Support\EnvironmentFile;

trait WritesEnvironment
{
    protected function environmentFile(): EnvironmentFile
    {
        return new EnvironmentFile($this->laravel->environmentFilePath());
    }

    /**
     * Write variables to .env and rebuild any cache that would hide them.
     *
     * A cached configuration never reads .env again, so without the rebuild the
     * new values only take effect after the next deploy. That is exactly how a
     * webhook ends up answering 403 with the secret sitting in .env.
     *
     * @param  array<string, string|bool>  $variables
     */
    protected function writeEnvironment(array $variables): void
    {
        $this->environmentFile()->set($variables);

        if ($this->laravel->configurationIsCached()) {
            $this->callSilently('config:cache');
            $this->components->info('Configuration cache rebuilt.');
        }

        if ($this->laravel->routesAreCached()) {
            $this->callSilently('route:cache');
            $this->components->info('Route cache rebuilt.');
        }
    }
}
