---
title: "Webhook"
description: "Receive Mailtrap delivery, bounce, spam and reject events in Laravel: the endpoint, how Mailtrap-Signature is checked, status codes and mailtrap:webhook."
nav_order: 7
---

# Webhook

A webhook is an HTTP call that another service makes to your application when something happens. Mailtrap calls this package's endpoint with delivery events, and the package updates the mail logs and the verdict of each address with them.

- **Endpoint**: `POST /api/webhooks/mailtrap`
- **Route name**: `webhooks.mailtrap`
- **Middleware**: `api` and `Darvis\Mailtrap\Http\Middleware\VerifyMailtrapWebhookSignature`

The events only exist for mail that goes through Mailtrap. The endpoint is on by default, also when the site sends through another mailer. It then answers `403` to every call, because there is no signing secret. Set `MAILTRAP_WEBHOOK_ENABLED=false` if you want the route not registered at all.

## Create the webhook and its secret

Mailtrap generates the signing secret and returns it when the webhook is created. `mailtrap:webhook` creates the webhook through the Mailtrap account API and writes the secret to `.env`. The [setup wizard](./installation.md#install-step-by-step) runs this command for you.

It needs `MAILTRAP_API_TOKEN`, an account token with Admin access. Run it on the live server, because Mailtrap must be able to reach the URL:

```bash
php artisan mailtrap:webhook
```

Without an argument the URL is `APP_URL/api/webhooks/mailtrap`. The command writes `MAILTRAP_WEBHOOK_ENABLED=true` and `MAILTRAP_WEBHOOK_SECRET=…` to `.env`. It rebuilds the configuration cache and the route cache when they are in use. It prints `Webhook created and signing secret written to .env.`

To create the webhook from another machine, pass the public URL and `--show`. The secret is then printed, not written, and you copy it to the `.env` of the live server:

```bash
php artisan mailtrap:webhook https://example.com/api/webhooks/mailtrap --show
```

| Argument or option | Effect |
| --- | --- |
| `url` | Public webhook URL; defaults to `APP_URL/api/webhooks/mailtrap`. `localhost`, `127.0.0.1` and hosts ending in `.test`, `.local` or `.localhost` are refused. |
| `--token=` | Account API token with Admin access; defaults to `MAILTRAP_API_TOKEN` |
| `--stream=` | `transactional` or `bulk`. Defaults to `bulk` when `MAIL_HOST` is `bulk.smtp.mailtrap.io`, otherwise `transactional`. A webhook only receives events of its own stream. |
| `--domain-id=` | Only receive events for this Mailtrap sending domain |
| `--replace` | Delete an existing webhook for the same URL without asking. Without it the command asks first. |
| `--show` | Print the signing secret and do not write `.env` |

The webhook is created with the JSON payload format and subscribes to `delivery`, `open`, `click`, `bounce`, `spam_complaint` and `reject`.

You can also create the webhook in the Mailtrap dashboard and copy its signing secret to `MAILTRAP_WEBHOOK_SECRET` yourself.

## How the signature is checked

The middleware computes `hash_hmac('sha256', $rawRequestBody, $secret)` and compares it, with `hash_equals()`, to the value of the `Mailtrap-Signature` header. The secret is `MAILTRAP_WEBHOOK_SECRET`. The hash is taken over the raw body exactly as it was received, not over re-encoded JSON.

The check **fails closed**. A call is rejected with HTTP `403` and one of these JSON messages:

| Situation | Response body |
| --- | --- |
| `MAILTRAP_WEBHOOK_SECRET` is empty | `{"status":"error","message":"Webhook signing secret is not configured"}` |
| No `Mailtrap-Signature` header | `{"status":"error","message":"Missing webhook signature"}` |
| The signature does not match | `{"status":"error","message":"Invalid webhook signature"}` |

So a fresh install without a secret accepts no webhook calls at all.

`MAILTRAP_WEBHOOK_VERIFY_SIGNATURE=false` switches the check off, and every call is accepted. Do not do that on a public site: the endpoint writes to `email_validations` and `mail_logs`, so anyone who knows the URL could then post events for any address.

## What each event does

One call carries a list of events under the key `events`. For each event the package reads `event` (the type) and `email`.

| `event` | Verdict of the address | Status code when the event has no `response_code` |
| --- | --- | --- |
| `delivery` | `valid` (`markAsValid()`) | `200` |
| `open` | `valid` | `200` |
| `click` | `valid` | `200` |
| `bounce` | `invalid` (`markAsInvalid()`) | `550` |
| `spam` | `invalid` | `400` |
| `reject` | `invalid` | `450` |
| anything else, such as `unsubscribe`, `soft bounce`, `suspension` | unchanged | counted as skipped |

When the event has a `response_code`, that code is stored; the default from the table is only the fallback. For `bounce`, `spam` and `reject` the reason is the `response` field, then the `reason` field, then `Mailtrap reported a {event} event`.

`invalid` does not block future sends. See [which verdict stops a send](./sending-and-logging.md#which-verdict-stops-a-send).

Then the mail log is updated:

1. The package looks for the row with the same message id and recipient. The message id is the custom variable `x_message_id` from the event, or else the event's `message_id`.
2. That row gets the status code and, for a failure, the reason in `error_message`.
3. When no row matches, a new one is created with `type` `webhook`, so mail that was not sent by this application still shows up.
4. An event without any message id only updates the verdict of the address.

Every outgoing mail carries its message id to Mailtrap in the `X-MT-Custom-Variables` header, as the variable `x_message_id`. Variables you set in that header yourself are kept. When the JSON would be longer than 1000 bytes, the package leaves the header alone.

After that, the `MailtrapEventReceived` event is dispatched. See [React to a blocked send or a webhook event](./sending-and-logging.md#react-to-a-blocked-send-or-a-webhook-event).

### An example

This call:

```json
{
  "events": [
    {
      "event": "bounce",
      "email": "receiver@example.com",
      "message_id": "1df37d17-0286-4d8b-8edf-bc4ec5be86e6",
      "event_id": "bede7236-2284-43d6-a953-1fdcafd0fdbc",
      "response": "5.5.1 User Unknown",
      "response_code": 550
    }
  ]
}
```

stores the verdict `invalid` with reason `5.5.1 User Unknown` and status code `550` for `receiver@example.com`, and sets `status_code` `550` and that `error_message` on the matching mail log.

## What the endpoint answers

| Status | When | Body |
| --- | --- | --- |
| `200` | The payload has an `events` list, whatever happened to the single events | `status` `success`, `message` `Webhook processed`, and `stats` |
| `400` | There is no `events` list | `status` `error`, `message` `No valid events found in webhook payload` |
| `403` | The signature check failed | See above |

`stats` holds `valid_emails`, `invalid_emails`, `skipped`, `total_processed`, `total_events` and `processing_time_ms`. An event that fails, is malformed, repeats an earlier event of the same call, or has a type the package does not act on is counted in `skipped`. The answer stays `200`, so one bad event does not make Mailtrap send the whole call again.

## See what arrives

Set `MAILTRAP_LOG_TO_LARAVEL=true` to write every received payload and every rejection to the Laravel log. It is off by default, so a rejected call leaves no trace until you switch it on. The payload contains email addresses.

## Next steps

- [Testing](./testing.md#test-the-webhook-with-a-signed-request): post a signed request in a test
- [Troubleshooting](./troubleshooting.md#the-webhook-answers-403)
