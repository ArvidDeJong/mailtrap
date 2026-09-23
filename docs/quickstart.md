---
title: "Quick start"
description: "A complete Laravel example with darvis/mailtrap: send a mailable, link its mail log to a model, handle a blocked recipient and read the log back."
nav_order: 4
---

# Quick start

This example sends a welcome mail to a user, links the mail log to that user, and shows what happens when the address is blocked. It assumes you finished the [installation](./installation.md) and that your app has the default `App\Models\User` model.

You do not call the package to send mail. It listens to Laravel's mail events, so every `Mail::send()` in the application is validated and logged.

## 1. Create the mailable

A mailable is Laravel's class for one kind of mail. The three `X-Mail-*` headers are what links the log to a model; they are optional.

```php
<?php
// app/Mail/WelcomeMail.php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class WelcomeMail extends Mailable
{
    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welcome');
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-Mail-Type' => 'welcome',
            'X-Mail-Model' => User::class,
            'X-Mail-Model-ID' => (string) $this->user->id,
        ]);
    }
}
```

```html
<!-- resources/views/mail/welcome.blade.php -->
<p>Welcome! Your account is ready.</p>
```

The package copies the headers to the `type`, `model` and `model_id` columns of the log row.

## 2. Send it and handle a blocked address

```php
<?php
// routes/web.php

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Mailer\Exception\TransportException;

Route::post('/users/{user}/welcome', function (User $user) {
    try {
        Mail::to($user->email)->send(new WelcomeMail($user));
    } catch (TransportException $e) {
        return back()->with('error', $e->getMessage());
    }

    return back()->with('status', 'Welcome mail sent.');
})->middleware('auth');
```

When this runs, the package checks the format of `$user->email` and looks up a mail server for its domain. If the check passes, a row is written to `mail_logs` and the mail is sent. When the mailer confirms, the row gets status `200`.

If the check fails, or the address was blocked by hand, nothing is sent. A row with status `550` is written and a `TransportException` is thrown with this message:

```text
Email address user@example.com is blocked: No valid mail server found for domain
```

Catch that exception wherever a failed send must not break the request. With `Mail::to(...)->queue(...)` the check runs in the queue worker, so there the exception fails the job and does not reach your route.

## 3. Read the log

```php
<?php
// anywhere in your app, or in php artisan tinker

use App\Models\User;
use Darvis\Mailtrap\Models\MailLog;

$user = User::first();

$log = MailLog::forModel(User::class, $user->id)->latest('id')->first();

$log->recipient;      // "user@example.com"
$log->subject;        // "Welcome"
$log->type;           // "welcome"
$log->status_code;    // "200" sent, null pending, "550" blocked
$log->error_message;  // null, or the reason
$log->related;        // the User model
```

`forModel()` is a query scope, a named filter on the model. The other scopes are on [Sending, blocking and mail logs](./sending-and-logging.md#query-the-mail-logs).

## 4. Look at it in the browser

If Livewire and Flux are installed, open `/mailtrap` to see the same log in the [inbox page](./inbox.md). It opens in the `local` environment; on a live site you first define [the `viewMailtrap` gate](./inbox.md#who-can-open-the-inbox).

## Next steps

- [Sending, blocking and mail logs](./sending-and-logging.md): every rule of the send
- [Testing](./testing.md): test this route without DNS lookups
- [Webhook](./webhook.md): let Mailtrap report delivery and bounces
