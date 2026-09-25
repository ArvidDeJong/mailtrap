---
title: "Inbox page and health check"
description: "The Livewire and Flux inbox page of darvis/mailtrap: the viewMailtrap gate that decides who can open it, its settings, Tailwind setup and mailtrap:test."
nav_order: 8
---

# Inbox page and health check

## What the inbox page is

A page inside your own application that lists the rows of `mail_logs`. It is built with [Livewire](https://livewire.laravel.com) and [Flux](https://fluxui.dev). It is not a view on a Mailtrap inbox and it makes no call to Mailtrap.

On the page you can:

- See all logged mail with a status badge: Sent, Pending, Blocked, Invalid or Failed
- Search by recipient, sender or subject, and filter on status
- Open the details of one log: message id, status, error, linked model, and for a blocked send the file and line that sent it
- Send a test mail to any address
- Delete one log, or all logs older than `MAILTRAP_CLEANUP_AFTER_DAYS` with the Cleanup button

## When the page exists

The page is registered when all of this is true:

- `livewire/livewire` (^3.7.4 or ^4.0) is installed and its service provider is loaded
- `MAILTRAP_UI_ENABLED` is `true` (the default)

The page also needs `livewire/flux` ^2.11 to render; the free edition is enough. Without Livewire the page is not registered and the rest of the package works.

The path is `/mailtrap` (`MAILTRAP_UI_ROUTE`), the route name is `mailtrap.inbox`, and the Livewire component name is `mailtrap-inbox`.

## Who can open the inbox

**Outside the `local` environment: nobody, until you define the `viewMailtrap` gate.** A gate is a named yes-or-no check in Laravel's authorization. This works the way Laravel Horizon, Telescope and Pulse guard their dashboards.

Two checks run, in this order:

1. The middleware from `MAILTRAP_UI_MIDDLEWARE` (default `web`). Put your login here, for example `web,auth`.
2. The package middleware `Darvis\Mailtrap\Http\Middleware\AuthorizeInbox`, which answers `403` unless `Gate::allows('viewMailtrap')`. It is always the last middleware of the route and you cannot remove it through the configuration.

If your application does not define the gate, the package defines it, and that default only allows the `local` environment (`APP_ENV=local`). On a staging or production site every visitor then gets `403`, logged in or not.

To open the inbox on a live site, define the gate yourself. `is_admin` is an example; use whatever marks staff in your `users` table:

```php
// app/Providers/AppServiceProvider.php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewMailtrap', fn (?User $user) => $user?->is_admin === true);
}
```

Write the user as nullable (`?User`). Laravel does not call a gate for a guest when the parameter is not nullable; the guest is then refused without your code running. Your gate always wins over the default, also in `local`: a gate that returns `false` closes the page there too.

A login alone is not enough on a site where visitors can register: every customer has a login. That is why the gate exists next to the middleware. Still set the middleware, so a guest is sent to your login page and does not see a bare `403`:

```env
MAILTRAP_UI_MIDDLEWARE=web,auth
```

With `auth` in the list, a guest is redirected to the route named `login`. If your app has no such route, the guest gets an error page.

The same check runs on every Livewire update request and at the start of every action of the component (`select`, `deleteLog`, `cleanup`, `sendTest`) and in its `boot()` and `render()`. So it also holds when you embed `<livewire:mailtrap-inbox />` in a page of your own, outside the package route.

To switch the page off altogether:

```env
MAILTRAP_UI_ENABLED=false
```

Check it: on the live site, open `/mailtrap` in a private browser window. You must get the login page or `403`. Then log in as a user your gate allows; you must see the inbox.

## Settings

| Variable | Default | What it does |
| --- | --- | --- |
| `MAILTRAP_UI_ENABLED` | `true` | Register the page |
| `MAILTRAP_UI_ROUTE` | `mailtrap` | Path of the page |
| `MAILTRAP_UI_MIDDLEWARE` | `web` | Comma separated middleware that runs before the `viewMailtrap` gate; see above |
| `MAILTRAP_UI_LAYOUT` | `components.layouts.app` | The Blade layout the page is rendered in, in dot notation. `layouts.app` means `resources/views/layouts/app.blade.php`. |
| `MAILTRAP_UI_PER_PAGE` | `25` | Rows per page |

The layout must exist in your app and must load your CSS, Livewire and Flux, like your other Livewire pages.

## Make Tailwind see the package views

Tailwind 4 only generates the classes it finds in the files it scans. Add the package views to `resources/css/app.css`, then run `npm run build`:

```css
@source '../../vendor/darvis/mailtrap/resources/views/**/*.blade.php';
```

The wizard offers to add this line. Without it the inbox has no styling.

## Language

The inbox follows the locale of your application: English, or Dutch when the locale is `nl`. To change a text or add a language, publish the translations and edit or copy `lang/vendor/mailtrap/<locale>/inbox.php`:

```bash
php artisan vendor:publish --tag=mailtrap-lang
```

## Change the view

```bash
php artisan vendor:publish --tag=mailtrap-views
```

This copies the view to `resources/views/vendor/mailtrap/livewire/inbox.blade.php`. A published view does not follow package updates; see [troubleshooting](./troubleshooting.md#the-inbox-looks-wrong-after-an-update).

## Health check: mailtrap:test

`mailtrap:test` sends a test mail and reports the result. It works with every mailer and does not need Livewire.

```bash
php artisan mailtrap:test you@yourdomain.com

# Use another mailer from config/mail.php than the default
php artisan mailtrap:test you@yourdomain.com --mailer=smtp
```

It prints the log row it wrote: message id, sender, recipient, subject, status and error. The expected output is on [Installation](./installation.md#check-that-it-works).

| Exit code | When |
| --- | --- |
| `0` | The mail was handed to the mailer. Also when no log row was found because logging is off; the command then warns `Mail sent, but no log found (is logging disabled?).` |
| `1` | Sending threw an exception, for example a blocked recipient or an SMTP error, or the log row has a status other than `200` |

Exit code `0` means the mailer accepted the mail. It does not prove the mail was delivered; that is what the [webhook](./webhook.md) reports.

## Next steps

- [Sending, blocking and mail logs](./sending-and-logging.md)
- [Troubleshooting](./troubleshooting.md)
