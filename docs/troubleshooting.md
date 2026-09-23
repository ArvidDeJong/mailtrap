---
title: "Troubleshooting"
description: "Fix problems with darvis/mailtrap: a blocked address, a webhook or inbox that answers 403, missing tables, an unstyled inbox, pending logs, cached config."
nav_order: 10
---

# Troubleshooting

Each entry is a symptom, its cause and the fix. Error messages are quoted as the package writes them.

Two general rules first:

- **A changed `.env` has no effect on a server with a cached configuration.** Run `php artisan config:cache` again (or `php artisan config:clear`). The route of the inbox and of the webhook depend on the configuration too, so with a route cache also run `php artisan route:cache`.
- **The package writes nothing to the Laravel log by default.** Set `MAILTRAP_LOG_TO_LARAVEL=true` to see webhook payloads, rejections and errors in `storage/logs/laravel.log`.

## Sending

### Email address is blocked

`mailtrap:test` prints it as `Sending failed: Email address {email} is blocked: {reason}.` The full exception message is `Email address {email} is blocked: {reason}`, thrown as a `Symfony\Component\Mailer\Exception\TransportException`. The address has the verdict `blocked` in `email_validations`. The reason tells you why:

| Reason | Cause | Fix |
| --- | --- | --- |
| `Invalid email format` | The address is not well-formed | Correct the address |
| `No valid mail server found for domain` | The domain has no MX record, or the DNS lookup failed | Check the domain for a typo. If the domain is right, the block expires after `MAILTRAP_VALIDATION_CACHE_DURATION` seconds (default 3600) and the address is checked again. |
| `MX record does not resolve to a valid IP address` | The MX hosts of the domain have no IP address | The same |
| Any other text | Someone called `EmailValidation::markAsBlocked()` | This block never expires. Lift it, see below. |

Lift a block at once:

```php
use Darvis\Mailtrap\Models\EmailValidation;

EmailValidation::markAsValid('user@example.com');
```

To send anyway and only record the reason, set `MAILTRAP_BLOCK_INVALID_EMAILS=false`. To skip the format and DNS check, set `MAILTRAP_VALIDATION_ENABLED=false`.

Use a real address of your own for `mailtrap:test`. An address on a made-up domain fails the DNS check.

### A queued mail ends up in failed_jobs with "is blocked"

The check runs where the mail is really sent. For a queued mailable that is the queue worker, so the `TransportException` fails the job instead of reaching your controller. Check the address before you queue the mail with `EmailValidation::isBlocked($email)`, or listen for the `MailBlocked` event.

### The mail went out but the log is missing

Writing the log never stops a mail. When a row cannot be written (a missing `mail_logs` table, a database error), the package reports the exception to Laravel's exception handler, so it is in `storage/logs/laravel.log` or your error tracker, and the mail is sent anyway. Look there for the cause. A mail without a subject is logged with an empty subject.

### no such table: mail_logs or email_validations

Also: `Base table or view not found`. The package is installed but its migrations did not run. A missing `email_validations` table makes every send fail, because every recipient is looked up there before the mail goes out. A missing `mail_logs` table only costs you the log. Run:

```bash
php artisan migrate
```

### Sending failed with an SMTP or connection error

`mailtrap:test` prints `Sending failed:` followed by the error of the mailer. This comes from Laravel's mailer, not from the package. For Mailtrap SMTP check these values, which are what the wizard writes:

```env
MAIL_MAILER=smtp
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=api
MAIL_PASSWORD=the token of your sending domain
```

- `MAIL_PASSWORD` must be the token of the **sending domain**, not the account token from `MAILTRAP_API_TOKEN`.
- `MAIL_FROM_ADDRESS` must be on the domain you verified in Mailtrap.
- A leftover `MAIL_SCHEME=smtps` or another `MAIL_ENCRYPTION` from a previous provider breaks the connection on port 587. The wizard sets them to `smtp` and `tls` when they are present.

### Sending is slow

The DNS lookups run during the send, once per address without a verdict. Queue your mail, or set `MAILTRAP_VALIDATION_ENABLED=false`.

## Mail logs

### A log stays Pending

A row is pending (`status_code` `null`) from the moment the mail is handed to the mailer until Laravel fires `MessageSent`. It stays pending when the mailer threw an error after the row was written, for example an SMTP failure. Laravel has no event for a failed send, so the package cannot mark that row as failed. The exception of that send tells you why.

A blocked recipient does not leave pending rows: every recipient of that mail gets status `550`.

### Mail sent, but no log found (is logging disabled?)

`mailtrap:test` prints this when the mail went out and no row was written. `MAILTRAP_LOGGING_ENABLED` or `MAILTRAP_LOG_SUCCESSFUL` is `false`, or the server still runs on a cached configuration with those values.

### There are no logs in my tests

`Mail::fake()` and `Event::fake()` without arguments stop Laravel's mail events, so the package sees nothing. See [Testing](./testing.md).

### Old logs are not removed

`model:prune` only finds models in `app/Models`. Schedule it with the model name, see [Remove old logs](./sending-and-logging.md#remove-old-logs). With `MAILTRAP_CLEANUP_AFTER_DAYS=0` nothing is removed.

## Webhook

### The webhook answers 403

The JSON body says which check failed:

| `message` | Cause | Fix |
| --- | --- | --- |
| `Webhook signing secret is not configured` | `MAILTRAP_WEBHOOK_SECRET` is empty, or it is in `.env` while the server runs on an older cached configuration | Set the secret, then `php artisan config:cache` |
| `Missing webhook signature` | The call has no `Mailtrap-Signature` header. It did not come from Mailtrap, or it is your own test call. | Sign the call, see [Testing](./testing.md#test-the-webhook-with-a-signed-request) |
| `Invalid webhook signature` | The secret in `.env` is not the secret of this webhook. This happens after the webhook was created again, or when the secret of another webhook or an API token was pasted. | Run `php artisan mailtrap:webhook --replace` on the live server, or copy the secret from the webhook's detail panel in Mailtrap |

With `MAILTRAP_LOG_TO_LARAVEL=true` the log shows `Mailtrap webhook rejected: no signing secret configured. …`, `Mailtrap webhook rejected: missing Mailtrap-Signature header.` or `Mailtrap webhook rejected: signature mismatch.`

### The webhook answers 404

The route is not registered. `MAILTRAP_WEBHOOK_ENABLED` is `false` (the wizard writes that when the site does not send through Mailtrap), or the route cache is older than that setting. Set it to `true` and run `php artisan route:cache` if you cache routes.

### The webhook answers 400: No valid events found in webhook payload

The JSON body has no `events` list. Mailtrap sends one; a hand-made test call must too.

### The webhook answers 200 but nothing changes

Look at `stats` in the answer. An event counted under `skipped` was malformed (no `email` or `event`), a duplicate within the call, a type the package does not act on (`unsubscribe`, `soft bounce`, `suspension`), or it failed while it was stored. Set `MAILTRAP_LOG_TO_LARAVEL=true`; a failure is logged as `Failed to process Mailtrap event`.

### mailtrap:webhook says "Mailtrap cannot reach …"

The URL is local: `localhost`, `127.0.0.1` or a host ending in `.test`, `.local` or `.localhost`. Run the command on the live server, or pass the live URL with `--show` and copy the printed secret to that server.

### Mailtrap API returned 401 or 403

`mailtrap:webhook` and `mailtrap:install` print `Mailtrap API returned 401: … Check MAILTRAP_API_TOKEN.` for a wrong token and `Mailtrap API returned 403: … The API token needs admin access to the account.` for a token with too few rights. Create an account token with Admin access. The sending domain token from `MAIL_PASSWORD` does not work here.

### No Mailtrap API token. Set MAILTRAP_API_TOKEN or pass --token.

`mailtrap:webhook` found no token in the option, the configuration or `.env`. Add `MAILTRAP_API_TOKEN` to `.env` or pass `--token=`.

## Inbox page

### /mailtrap gives 403

The `viewMailtrap` gate refused the visitor. Without a gate of your own, the inbox only opens in the `local` environment. Define the gate in `AppServiceProvider::boot()`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewMailtrap', fn (?User $user) => $user?->is_admin === true);
```

If you have a gate and still get `403`:

- You are not logged in as a user the gate allows.
- The gate's parameter is not nullable (`User $user`). Laravel then refuses a guest without calling your gate. Write `?User $user`.
- `MAILTRAP_UI_MIDDLEWARE` has no `auth`, so nobody is sent to the login page and the gate sees a guest.
- A button in the inbox answers `403` after a while: your session ended. Log in again.

See [who can open the inbox](./inbox.md#who-can-open-the-inbox).

### /mailtrap gives 404

- Livewire is not installed, or its service provider is not loaded. The page is only registered with Livewire.
- `MAILTRAP_UI_ENABLED=false`.
- `MAILTRAP_UI_ROUTE` has another value.
- The route cache is older than the package. Run `php artisan route:cache`.

### View [components.layouts.app] not found

The inbox renders inside the layout from `MAILTRAP_UI_LAYOUT`, default `components.layouts.app`. Your app keeps its layout somewhere else. Set the variable to your layout in dot notation, for example `layouts.app` for `resources/views/layouts/app.blade.php`.

### Route [login] not defined

The middleware list contains `auth` and a guest opened the page, but the app has no route named `login`. Add a login route with that name, or use the middleware of your own login system.

### The inbox has no styling

Tailwind does not scan the package views. Add the `@source` line from [Make Tailwind see the package views](./inbox.md#make-tailwind-see-the-package-views) and run `npm run build`.

### The inbox looks wrong after an update

You published the view with `--tag=mailtrap-views`, and the copy in `resources/views/vendor/mailtrap` is older than the package. Delete that folder to use the package view again, or publish again with `--force` and redo your changes.

## Configuration

### config/manta_mailtrap.php was published from an older version and lacks these keys

`mailtrap:install` prints this with a list of keys. It is a notice: the package uses the defaults for them. Copy the keys from `vendor/darvis/mailtrap/config/manta_mailtrap.php` if you want to change them.

### config('mailtrap') is empty

The config key is `manta_mailtrap`, and the file is `config/manta_mailtrap.php`.

### No environment file found at …

`mailtrap:install` and `mailtrap:webhook` write to `.env`. Create it first: `cp .env.example .env` and `php artisan key:generate`.

## Still stuck

Open an issue at [github.com/ArvidDeJong/mailtrap](https://github.com/ArvidDeJong/mailtrap/issues). This is an unofficial package; Mailtrap support cannot help with it.
