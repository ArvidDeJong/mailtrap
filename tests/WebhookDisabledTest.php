<?php

namespace Darvis\Mailtrap\Tests;

use Illuminate\Support\Facades\Route;

/**
 * Written as a PHPUnit class rather than in Pest style: the webhook route is
 * registered while the app boots, so the config has to be in place before
 * setUp() runs, which a Pest closure cannot do.
 */
class WebhookDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('manta_mailtrap.webhook.enabled', false);
    }

    public function test_it_registers_no_webhook_route_when_the_webhook_is_disabled(): void
    {
        $this->assertFalse(Route::has('webhooks.mailtrap'));

        $this->postJson('/api/webhooks/mailtrap', ['events' => []])->assertNotFound();
    }
}
