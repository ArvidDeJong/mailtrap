<?php

namespace Darvis\Mailtrap\Tests;

use Darvis\Mailtrap\MailtrapServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Livewire is installed in this repository, but a host app can exclude its
 * provider from discovery. The package must then boot without the inbox.
 */
class LivewireNotLoadedTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [MailtrapServiceProvider::class];
    }

    public function test_it_boots_without_the_inbox_when_livewire_is_not_loaded(): void
    {
        $this->assertFalse(Route::has('mailtrap.inbox'));
        $this->assertTrue(Route::has('webhooks.mailtrap'));
    }
}
