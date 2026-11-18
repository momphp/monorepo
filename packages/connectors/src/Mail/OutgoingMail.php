<?php

declare(strict_types=1);

namespace Mom\Connectors\Mail;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * A reply an app wants sent from a mailbox, ready for a mail client.
 */
final readonly class OutgoingMail
{
    /**
     * @param  list<string>  $to  the addresses it goes to
     * @param  string  $bodyText  what was written, as plain text
     * @param  string  $subject  the subject when this starts a thread
     * @param  string  $subjectTag  a marker every subject carries so an answer can be matched, such as a ticket's "[#123]"
     * @param  ?InboundMail  $answered  the message in the mailbox this answers; none when it starts a thread
     */
    public function __construct(
        public array $to,
        public string $bodyText,
        public string $subject,
        public string $subjectTag,
        public ?InboundMail $answered = null,
    ) {}

    /**
     * The Message-ID an email built here was given.
     */
    public static function messageId(Email $email): ?string
    {
        $ids = $email->getHeaders()->getHeaderBody('Message-ID');
        $id = is_array($ids) ? ($ids[0] ?? null) : $ids;

        return is_string($id) && '' !== $id ? $id : null;
    }

    /**
     * A subject with the marker in it, such as "Printer offline [#123]",
     * unless it is already there.
     */
    public function tagged(string $subject): string
    {
        return str_contains($subject, $this->subjectTag) ? $subject : mb_trim("{$subject} {$this->subjectTag}");
    }

    /**
     * The subject this goes out with: its own for a new thread, or the
     * subject of the message it answers with "Re:" before it.
     */
    public function subjectLine(): string
    {
        $answered = $this->answered?->subject;

        if (null === $answered) {
            return $this->tagged($this->subject);
        }

        return $this->tagged(1 === preg_match('/^re:/i', mb_trim($answered)) ? $answered : "Re: {$answered}");
    }

    /**
     * The whole message, for the mail services that are handed one to send
     * as it is. A reply names the message it answers and the thread before
     * it, so mail programs show it in the same conversation. It is given a
     * Message-ID here, so the id is known whether or not the service says.
     */
    public function toEmail(Address $from): Email
    {
        $email = (new Email)
            ->from($from)
            ->to(...$this->to)
            ->subject($this->subjectLine())
            ->text($this->bodyText)
            ->html($this->bodyHtml());

        $answeredId = $this->answered?->messageId;

        if (null !== $answeredId) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $answeredId);
            $email->getHeaders()->addIdHeader('References', [...$this->answered->references, $answeredId]);
        }

        $email->getHeaders()->addIdHeader('Message-ID', $email->generateMessageId());

        return $email;
    }

    /**
     * What was written as simple HTML, keeping its line breaks.
     */
    public function bodyHtml(): string
    {
        return nl2br(e($this->bodyText), false);
    }
}
