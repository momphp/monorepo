<?php

declare(strict_types=1);

namespace Mom\Connectors\Mail;

use Carbon\CarbonImmutable;

/**
 * An email that arrived in a mailbox, the same whichever mail service it
 * came through. Ids are without their angle brackets and addresses are in
 * lower case, so they compare with what an app has stored.
 */
final readonly class InboundMail
{
    /**
     * @param  ?string  $externalId  the mail service's own id for the message, which is how it is fetched again or replied to there
     * @param  ?string  $messageId  its Message-ID header
     * @param  ?string  $inReplyTo  the Message-ID of the message it answers
     * @param  list<string>  $references  the Message-IDs of the earlier messages in the thread, oldest first
     * @param  list<string>  $to  the addresses it was sent to
     * @param  ?string  $bodyText  the whole message as plain text
     * @param  ?string  $replyText  what the sender wrote, without the earlier messages quoted beneath it
     * @param  ?CarbonImmutable  $receivedAt  when the mail service received it
     * @param  bool  $hasAttachments  whether files came with it
     */
    public function __construct(
        public ?string $externalId,
        public ?string $messageId,
        public ?string $inReplyTo = null,
        public array $references = [],
        public ?string $fromAddress = null,
        public ?string $fromName = null,
        public array $to = [],
        public ?string $subject = null,
        public ?string $bodyText = null,
        public ?string $replyText = null,
        public ?CarbonImmutable $receivedAt = null,
        public bool $hasAttachments = false,
    ) {}
}
