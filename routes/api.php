<?php

use Darvis\Mailtrap\Http\Controllers\MailtrapWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mailtrap Package API Routes
|--------------------------------------------------------------------------
|
| Loaded by the service provider under the "api" prefix, only when
| webhook.enabled is true. The signature middleware is attached there.
|
*/

Route::post('/webhooks/mailtrap', [MailtrapWebhookController::class, 'handle'])
    ->name('webhooks.mailtrap');
