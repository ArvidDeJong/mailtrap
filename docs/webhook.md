# Webhook

Mailtrap reports delivery events to the package, which updates the mail logs and the
validation status of each address.

- **Endpoint**: `POST /api/webhooks/mailtrap`
- **Route name**: `webhooks.mailtrap`

Set `MAILTRAP_WEBHOOK_ENABLED=false` to not register the route at all. That is
recommended when you send through another transport, since delivery events only come
from Mailtrap.

## Events

| Mailtrap event | Effect on the address | Default response code |
| --- | --- | --- |
| `delivery`, `open`, `click` | `markAsValid()` | |
| `bounce` | `markAsInvalid()` | 550 |
| `spam` | `markAsInvalid()` | 400 |
| `reject` | `markAsInvalid()` | 450 |

`invalid` does not block future sends; see
[validation states](./sending-and-logging.md#validation-states). Every signed event, also
types not listed here, is dispatched as `MailtrapEventReceived`.

Each outgoing mail carries its log id to Mailtrap as the custom variable `x_message_id`
(header `X-MT-Custom-Variables`, merged with any variables you set yourself). Mailtrap
returns it in every webhook event, so the event updates the right log row.

Once the payload is readable, the endpoint answers `200`, so one failing event does not
make Mailtrap retry the whole batch. Failed events are counted as skipped. See [webhook-response-codes.php](./examples/webhook-response-codes.php)
for how response codes in the payload are handled.

## Signature verification

Mailtrap signs every webhook with an HMAC-SHA256 of the raw request body, hex encoded,
in the `Mailtrap-Signature` header. The package verifies it and **fails closed**: with
verification enabled and no secret configured, every call is rejected with `403`.

```env
MAILTRAP_WEBHOOK_SECRET=your_32_char_hex_signing_secret
```

> **Why this matters:** a `bounce` event marks an address invalid. Left unverified,
> anyone who knows the URL can post events for arbitrary addresses.

To accept unsigned calls anyway (not recommended, the endpoint writes to
`email_validations` and `mail_logs`):

```env
MAILTRAP_WEBHOOK_VERIFY_SIGNATURE=false
```

## Creating the webhook and its secret

Mailtrap generates the secret itself and only returns it once, when the webhook is
created. `mailtrap:webhook` creates the webhook through the Mailtrap API and writes the
secret to `.env`. The [setup wizard](./installation.md#setup-wizard) runs it for you.

```bash
php artisan mailtrap:webhook                       # URL: APP_URL/api/webhooks/mailtrap
php artisan mailtrap:webhook https://example.com/api/webhooks/mailtrap --show
```

| Option | Effect |
| --- | --- |
| `--token=` | API token with admin access; defaults to `MAILTRAP_API_TOKEN` |
| `--stream=` | `transactional` (default) or `bulk` |
| `--domain-id=` | Only events for one sending domain |
| `--replace` | Delete an existing webhook for the same URL first; its secret cannot be read back |
| `--show` | Print the secret instead of writing it, for when you run the command on another machine |

The webhook subscribes to `delivery`, `open`, `click`, `bounce`, `spam_complaint` and
`reject` with the JSON payload format. When the configuration or routes are cached,
both caches are rebuilt so the new secret is live immediately.

You can also copy the secret from the webhook detail panel in Mailtrap.

## Next Steps

- [Sending, Blocking & Mail Logs](./sending-and-logging.md)
- [Inbox UI & Health Check](./inbox.md)
