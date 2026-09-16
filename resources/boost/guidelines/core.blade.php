## darvis/mailtrap

This package hooks into every outgoing mail of the application. It validates recipients, logs each send to `mail_logs`, and updates those logs and the address verdicts from signed Mailtrap webhook events.

- Config lives under the key `manta_mailtrap` (file `config/manta_mailtrap.php`), not `mailtrap`.
- Before sending, To, Cc and Bcc recipients are checked. A recipient whose `EmailValidation` status is `blocked` aborts the whole send with `Symfony\Component\Mailer\Exception\TransportException`, unless `validation.block_invalid` is false. Catch that exception where a failed send must not break the request.
- Address verdicts in `Darvis\Mailtrap\Models\EmailValidation` use the constants `VALID`, `INVALID` and `BLOCKED`. `INVALID` comes from bounce, spam and reject events and does not stop sending. Only `BLOCKED` does, and it applies to the address, not its domain.
- Link a mail to a domain model with headers, then query the log through the `MailLog` scopes instead of comparing `status_code` by hand:

@verbatim
<code-snippet name="Tag a mail and read its log" lang="php">
// In a Mailable
public function headers(): \Illuminate\Mail\Mailables\Headers
{
    return new \Illuminate\Mail\Mailables\Headers(text: [
        'X-Mail-Type' => 'invoice',
        'X-Mail-Model' => \App\Models\Invoice::class,
        'X-Mail-Model-ID' => (string) $this->invoice->id,
    ]);
}

use Darvis\Mailtrap\Models\MailLog;

MailLog::forModel(\App\Models\Invoice::class, $invoice->id)->failed()->get();
$log->related; // the Invoice
</code-snippet>
@endverbatim

- Setup and checks: `php artisan mailtrap:install` (interactive setup wizard: migrations, API token, Mailtrap SMTP, webhook, validation, inbox, test mail; flag-driven with `--no-interaction`), `php artisan mailtrap:webhook` (creates the webhook and writes `MAILTRAP_WEBHOOK_SECRET`), `php artisan mailtrap:test you@example.com` (exit code 0 when the mail went out).
- The webhook `POST /api/webhooks/mailtrap` rejects unsigned calls with 403, and it also does that while no secret is set. After changing `.env`, run `php artisan config:cache` again on servers with a cached config.
- To react to mail events, listen for `Darvis\Mailtrap\Events\MailBlocked` (before a blocked send is aborted) or `Darvis\Mailtrap\Events\MailtrapEventReceived` (every webhook event, including `unsubscribe`, which the package itself ignores). Don't add a second webhook route for this.
- Outgoing mail gets an `X-MT-Custom-Variables` header containing `x_message_id`. When you set your own custom variables, add them to that JSON; don't replace the header.
- `Darvis\Mailtrap\Services\MailtrapService` and `app('mailtrap')` are deprecated; use `EmailValidation::validateEmail()` instead.
