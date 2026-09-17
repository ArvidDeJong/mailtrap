---
title: "Inbox UI & health check"
description: "The Livewire and Flux inbox for inspecting logged mail in Laravel, and the mailtrap:test health check command."
nav_order: 5
---

# Inbox UI & Health Check

## Inbox UI

A Mailtrap-style inbox to inspect outgoing mail, built with Livewire and Flux UI.

> **Requirements:** `livewire/livewire` (^3.7.4 or ^4.0) and `livewire/flux` (^2.11, the
> free edition is enough) in the host application. Without Livewire, or with
> `MAILTRAP_UI_ENABLED=false`, the inbox is not registered and the rest of the package
> keeps working.

The inbox lives at the configured route (default `/mailtrap`) and offers:

- A paginated list of all logged mail with status badges (sent, pending, failed, blocked)
- Search by recipient, sender or subject, and a status filter
- A detail view per message (message id, status, error, source file and line, linked model)
- **Send test mail**
- **Delete** a single log, or **Cleanup** logs older than `MAILTRAP_CLEANUP_AFTER_DAYS`

### Configuration

```env
MAILTRAP_UI_ENABLED=true
MAILTRAP_UI_ROUTE=mailtrap
MAILTRAP_UI_MIDDLEWARE=web,auth        # comma-separated, default: web
MAILTRAP_UI_LAYOUT=components.layouts.app
MAILTRAP_UI_PER_PAGE=25
```

> Protect the route with middleware: the inbox shows recipients, subjects and error
> details. For example `web,auth`, or your own staff or admin middleware.

### Tailwind

Add the package views to your Tailwind sources so their classes are compiled (Tailwind v4,
in `resources/css/app.css`):

```css
@source '../../vendor/darvis/mailtrap/resources/views/**/*.blade.php';
```

To override the views, publish them:

```bash
php artisan vendor:publish --tag=mailtrap-views
```

## Health check: `mailtrap:test`

Sends a test mail and reports the result. The command exits with `0` on success and `1`
on failure, so it fits in CI pipelines and uptime monitoring.

```bash
php artisan mailtrap:test you@example.com

# Use a specific mailer instead of the default
php artisan mailtrap:test you@example.com --mailer=microsoft-graph
```

It reads back the mail log and prints a summary (message id, sender, recipient, subject,
status, error message). A recorded non-success status, such as a blocked recipient, also
counts as a failure.

## Next Steps

- [Sending, Blocking & Mail Logs](./sending-and-logging.md)
- [Installation & Configuration](./installation.md)
