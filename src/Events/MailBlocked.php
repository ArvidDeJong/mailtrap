<?php

namespace Darvis\Mailtrap\Events;

use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Foundation\Events\Dispatchable;
use Symfony\Component\Mime\Email;

/**
 * Dispatched just before a send is aborted because a recipient is blocked.
 */
class MailBlocked
{
    use Dispatchable;

    /**
     * @param  MailLog|null  $mailLog  Null when logging.log_failed is off.
     */
    public function __construct(
        public readonly string $email,
        public readonly string $reason,
        public readonly Email $message,
        public readonly ?MailLog $mailLog,
    ) {}
}
