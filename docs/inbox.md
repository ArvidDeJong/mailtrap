---
title: "Inbox page and health check"
description: "The Livewire and Flux inbox page of darvis/mailtrap: who can open it by default, how to protect it, layout and Tailwind setup, and mailtrap:test."
nav_order: 7
---

# Inbox page and health check

## What the inbox page is

A page inside your own application that lists the rows of `mail_logs`. It is built with [Livewire](https://livewire.laravel.com) and [Flux](https://fluxui.dev). It is not a view on a Mailtrap inbox and it makes no call to Mailtrap.

On the page you can:

- See all logged mail with a status badge: Sent, Pending, Blocked, Invalid or Failed
- Search by recipient, sender or subject, and filter on status
- Open the details of one log: message id, status, error, source file and line, linked model
- Send a test mail to any address
- Delete one log, or all logs older than `MAILTRAP_CLEANUP_AFTER_DAYS` with the Cleanup button

## When the page exists

The page is registered when all of this is true:

- `livewire/livewire` (^3.7.4 or ^4.0) is installed and its service provider is loaded
- `MAILTRAP_UI_ENABLED` is `true` (the default)

The page also needs `livewire/flux` ^2.11 to render; the free edition is enough. Without Livewire the page is not registered and the rest of the package works.

The path is `/mailtrap` (`MAILTRAP_UI_ROUTE`), the route name is `mailtrap.inbox`, and the Livewire component name is `mailtrap-inbox`.

## Who can open the inbox

**With the package defaults: everyone.** The default middleware is `web`, which has no login check. The component has no gate or policy of its own. So on a site where Livewire is installed and nothing else is configured, any visitor who opens `/mailtrap` can read recipients, subjects and error messages, delete logs, and send a test mail from your server to any address.

The only protection is the middleware list in `MAILTRAP_UI_MIDDLEWARE`. Set it before the site is reachable from the internet:

```env
MAILTRAP_UI_MIDDLEWARE=web,auth
```

`auth` lets every logged-in user in. If your users are customers, use middleware that only lets staff through. Middleware is a check that runs before a page is shown; `admin` below stands for a middleware alias from your own app:

```env
MAILTRAP_UI_MIDDLEWARE=web,auth,admin
```

Or switch the page off:

```env
MAILTRAP_UI_ENABLED=false
```

The interactive `mailtrap:install` wizard asks this question and writes `web,auth` by default. `mailtrap:install --no-interaction` does not.

With `auth` in the list, a guest is redirected to the route named `login`. If your app has no such route, the guest gets an error page.

Check it: log out, open `/mailtrap` in a private browser window. You must not see the inbox.

## Settings

| Variable | Default | What it does |
| --- | --- | --- |
| `MAILTRAP_UI_ENABLED` | `true` | Register the page |
| `MAILTRAP_UI_ROUTE` | `mailtrap` | Path of the page |
| `MAILTRAP_UI_MIDDLEWARE` | `web` | Comma separated middleware; see above |
| `MAILTRAP_UI_LAYOUT` | `components.layouts.app` | The Blade layout the page is rendered in, in dot notation. `layouts.app` means `resources/views/layouts/app.blade.php`. |
| `MAILTRAP_UI_PER_PAGE` | `25` | Rows per page |

The layout must exist in your app and must load your CSS, Livewire and Flux, like your other Livewire pages.

## Make Tailwind see the package views

Tailwind 4 only generates the classes it finds in the files it scans. Add the package views to `resources/css/app.css`, then run `npm run build`:

```css
@source '../../vendor/darvis/mailtrap/resources/views/**/*.blade.php';
```

The wizard offers to add this line. Without it the inbox has no styling.

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
