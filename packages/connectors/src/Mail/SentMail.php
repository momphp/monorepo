<?php

declare(strict_types=1);

namespace Mom\Connectors\Mail;

/**
 * What a mail service says about a message it just sent for us.
 */
final readonly class SentMail
{
    /**
     * @param  ?string  $externalId  the service's own id for the message
     * @param  ?string  $internetMessageId  the Message-ID the customer's answer will refer to
     * @param  ?string  $subject  the subject it went out with
     */
    public function __construct(
        public ?string $externalId,
        public ?string $internetMessageId,
        public ?string $subject,
    ) {}
}
