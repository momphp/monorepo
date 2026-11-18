<?php

declare(strict_types=1);

namespace Mom\Connectors\Mail;

use Mom\Connectors\Connection;
use Mom\Connectors\CouldNotReachConnector;

/**
 * What a connector type implements for its accounts to be used for email:
 * a mailbox can then be added through them.
 */
interface ProvidesMail
{
    /**
     * The mail client for a connected account's own mailbox, or for a
     * shared mailbox it can open when an address is given.
     *
     * @throws CouldNotReachConnector
     */
    public function mailClient(Connection $connection, ?string $sharedAddress = null): MailClient;

    /**
     * Whether this account can open mailboxes other than its own.
     */
    public function opensSharedMailboxes(Connection $connection): bool;
}
