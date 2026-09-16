<?php

namespace Darvis\Mailtrap\Events;

use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched for every event in a signed Mailtrap webhook, after the package
 * has processed it.
 *
 * Also fired for events the package does not act on itself, such as
 * "unsubscribe", "soft bounce" and "suspension", so the host application can.
 */
class MailtrapEventReceived
{
    use Dispatchable;

    /**
     * @param  string  $type  The Mailtrap event name, e.g. "delivery", "bounce" or "unsubscribe".
     * @param  array<string, mixed>  $payload  The event exactly as Mailtrap sent it.
     * @param  MailLog|null  $mailLog  The updated or created log; null for events the package does not act on.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $email,
        public readonly array $payload,
        public readonly ?MailLog $mailLog,
    ) {}
}
