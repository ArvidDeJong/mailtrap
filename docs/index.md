---
title: Home
nav_order: 1
description: "darvis/mailtrap for Laravel: validates recipients before sending, logs every outgoing mail, processes signed Mailtrap webhook events and ships an inbox UI."
permalink: /
---

# darvis/mailtrap

Mailtrap integration for **Laravel**. It checks every recipient before a mail goes out, logs each send, updates those logs from signed Mailtrap webhook events, and gives you an inbox to inspect it all.

This is an independent open-source package, not an official Mailtrap product.

```bash
composer require darvis/mailtrap
php artisan mailtrap:install
```

Requires PHP 8.2+ and Laravel 11, 12 or 13. The inbox UI also needs Livewire and Flux; everything else works without them.

## Features

- **Validation before sending**: format and MX checks on every To, Cc and Bcc address, with blocking per address
- **Mail logging**: every outgoing mail in `mail_logs`, optionally linked to a model
- **Webhook**: delivery, bounce, spam and reject events from Mailtrap update logs and addresses, with signature verification
- **Inbox UI**: a Livewire/Flux page to search, inspect and clean up logged mail
- **Health check**: `mailtrap:test` sends a test mail, with CI-friendly exit codes
- **Setup wizard**: `mailtrap:install` configures tokens, SMTP, webhook and inbox
- **Any mailer**: validation and logging also work with SES, Microsoft Graph and others

## Quick example

```php
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;

// null when valid, otherwise the reason
$error = EmailValidation::validateEmail('user@example.com');

// Stop all mail to one address (never to the whole domain)
EmailValidation::markAsBlocked('complainer@example.com', 'Spam complaint');

// Query the logs
MailLog::forModel(Invoice::class, $invoice->id)->failed()->get();
```

## Read next

- [Installation & configuration](installation.md): the tokens, the setup wizard and all options
- [Sending, blocking & mail logs](sending-and-logging.md): what happens on every send
- [Webhook](webhook.md): delivery feedback from Mailtrap
- [Inbox UI & health check](inbox.md)
- [Email validation](email-validation.md)
- [FAQ](faq.md)
