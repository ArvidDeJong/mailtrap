<?php

use Illuminate\Support\Facades\Route;
use Darvis\Mailtrap\Http\Controllers\MailtrapWebhookController;

/*
|--------------------------------------------------------------------------
| Mailtrap Package API Routes
|--------------------------------------------------------------------------
|
| Deze API routes worden automatisch geladen door het Mailtrap package.
| Ze bevatten de webhook endpoints voor externe Mailtrap calls.
|
*/

Route::post('/webhooks/mailtrap', [MailtrapWebhookController::class, 'handle'])
    ->name('webhooks.mailtrap');
