<?php

declare(strict_types=1);

namespace Mom\Connectors\Mail;

use Carbon\CarbonImmutable;
use Mom\Connectors\CouldNotReachConnector;

/**
 * One mailbox at an outside mail service. Reading mail, saving attachments
 * and sending replies all go through this, so none of them knows which
 * service a mailbox is at.
 */
interface MailClient
{
    /**
     * Opens the inbox, which is how we find out whether the mailbox exists
     * and the connected account may read it.
     *
     * @throws CouldNotReachConnector
     */
    public function checkInbox(): void;

    /**
     * The inbox's messages received at or after a time, oldest first: just
     * their ids and when they arrived, and no more than the limit.
     *
     * @return list<array{id: string, received_at: CarbonImmutable}>
     *
     * @throws CouldNotReachConnector
     */
    public function inboxMessagesSince(CarbonImmutable $since, int $limit): array;

    /**
     * One message, with what the sender wrote set apart from the history
     * quoted beneath it. Null when it is a draft or no longer in the mailbox.
     *
     * @throws CouldNotReachConnector
     */
    public function message(string $id): ?InboundMail;

    /**
     * What is attached to a message: each attachment's id, name, type and
     * size in bytes, whether it is a file (not a linked or embedded item) and
     * whether it is an image shown inside the message. Never the contents.
     *
     * @return list<array{id: string, name: string, content_type: ?string, size: int, is_file: bool, is_inline: bool}>
     *
     * @throws CouldNotReachConnector
     */
    public function attachments(string $messageId): array;

    /**
     * The bytes of a file attached to a message.
     *
     * @throws CouldNotReachConnector
     */
    public function attachmentContents(string $messageId, string $attachmentId): string;

    /**
     * Sends a reply once. When it answers a message in the mailbox it is
     * threaded under that message. It is never retried here: sending twice
     * could send a customer the same email twice.
     *
     * @throws CouldNotReachConnector
     */
    public function send(OutgoingMail $mail): SentMail;
}
