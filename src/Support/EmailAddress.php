<?php

declare(strict_types=1);

namespace Darvis\Mailtrap\Support;

/**
 * The one spelling under which the package stores and looks up an address.
 *
 * Mail systems treat Dead@Example.org and dead@example.org as one mailbox, but
 * SQLite and PostgreSQL compare strings as written. Without this a block on one
 * spelling would not stop the other.
 *
 * @internal
 */
final class EmailAddress
{
    public static function normalise(string $email): string
    {
        return strtolower(trim($email));
    }
}
