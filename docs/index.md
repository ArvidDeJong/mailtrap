---
title: "Home"
nav_order: 1
description: "darvis/mailtrap is an unofficial Mailtrap integration for Laravel: recipient checks before sending, a mail log, signed webhook events and an inbox page."
permalink: /
---

# darvis/mailtrap

`darvis/mailtrap` is a Laravel package that checks every recipient before a mail goes out and logs every outgoing mail in the database. When the application sends through [Mailtrap](https://mailtrap.io) Email Sending, it also processes Mailtrap's signed webhook events (delivery, bounce, spam, reject) and updates the logs with them.

**This is an unofficial package.** It is independent open-source software, not an official Mailtrap product, and it is not made, supported or endorsed by Mailtrap. Questions about this package go to its [GitHub issues](https://github.com/ArvidDeJong/mailtrap/issues), not to Mailtrap support.

## Who it is for

Laravel developers who want to know what happened to each mail their application sent: to whom, when, about which model, and whether it was delivered or bounced. Validation and logging work with every Laravel mailer. Only the delivery feedback needs a Mailtrap account.

## What it does not do

- It is not a Mailtrap API client and not a mail transport. Laravel's own mailer sends the mail; for Mailtrap that is SMTP.
- It does not read or show a Mailtrap Email Testing (sandbox) inbox. The inbox page of this package lists the application's own `mail_logs` table.
- It does not verify that a mailbox exists. The check is the address format plus a DNS lookup for a mail server on the domain.
- It does not stop future mail to an address that bounced. A bounce is recorded as `invalid`; only a `blocked` address stops a send. See [which verdict stops a send](sending-and-logging.md#which-verdict-stops-a-send).
- It does not store the body of a mail. A log holds sender, recipient, subject, status and error.

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- Only for the inbox page: `livewire/livewire` ^3.7.4 or ^4.0 and `livewire/flux` ^2.11 (the free edition is enough)

## Install

```bash
composer require darvis/mailtrap
php artisan mailtrap:install
php artisan mailtrap:test you@example.com
```

`mailtrap:install` is a setup wizard that also runs the migrations. The inbox page answers `403` outside the `local` environment until you define the `viewMailtrap` gate; see [who can open the inbox](inbox.md#who-can-open-the-inbox).

## Pages

- [Installation](installation.md): requirements and the steps from `composer require` to a first logged mail
- [Environment variables](environment.md): every `.env` variable, what it does, and where you find the Mailtrap tokens and the webhook secret
- [Quick start](quickstart.md): one complete example, a mailable that is logged and linked to a model
- [Sending, blocking and mail logs](sending-and-logging.md): what happens on every send, the verdicts, the log scopes and the events
- [Email validation](email-validation.md): every method of the `EmailValidation` model, for one address and for a list
- [Webhook](webhook.md): the endpoint, signature checking, status codes and the `mailtrap:webhook` command
- [Inbox page and health check](inbox.md): who can open the inbox, its settings, and `mailtrap:test`
- [Testing](testing.md): test code that sends mail without DNS lookups or a real mail server
- [Troubleshooting](troubleshooting.md): error messages and symptoms, with cause and fix
- [FAQ](faq.md): short answers to common questions
