<?php

declare(strict_types=1);

namespace Mom\Microsoft;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Mom\Connectors\Mail\InboundMail;
use Mom\Connectors\Mail\MailClient;
use Mom\Connectors\Mail\MessageId;
use Mom\Connectors\Mail\OutgoingMail;
use Mom\Connectors\Mail\SentMail;
use Throwable;
use UnexpectedValueException;

/**
 * A Microsoft 365 mailbox, through Microsoft Graph.
 */
final readonly class MicrosoftMailClient implements MailClient
{
    public function __construct(
        private MicrosoftGraph $graph,
    ) {}

    /**
     * A message as Microsoft Graph returns it, asked for with its headers
     * and with its body as plain text. Graph tells what the sender wrote
     * apart from the quoted history itself, in the message's unique body.
     *
     * @param  array<string, mixed>  $message
     */
    public static function toInboundMail(array $message): InboundMail
    {
        $headers = self::headers($message);
        $externalId = Arr::get($message, 'id');
        $bodyText = self::text(Arr::get($message, 'body.content'));

        return new InboundMail(
            externalId: is_string($externalId) ? $externalId : null,
            messageId: MessageId::normalize(Arr::get($message, 'internetMessageId')),
            inReplyTo: MessageId::normalize($headers['in-reply-to'] ?? null),
            references: MessageId::all($headers['references'] ?? null),
            fromAddress: self::address(Arr::get($message, 'from.emailAddress.address')),
            fromName: self::text(Arr::get($message, 'from.emailAddress.name')),
            to: self::addresses(Arr::get($message, 'toRecipients')),
            subject: self::text(Arr::get($message, 'subject')),
            bodyText: $bodyText,
            replyText: self::text(Arr::get($message, 'uniqueBody.content')) ?? $bodyText,
            receivedAt: self::date(Arr::get($message, 'receivedDateTime')),
            hasAttachments: true === ($message['hasAttachments'] ?? false),
        );
    }

    public function checkInbox(): void
    {
        $this->graph->inbox();
    }

    public function inboxMessagesSince(CarbonImmutable $since, int $limit): array
    {
        return $this->graph->inboxMessagesSince($since, $limit);
    }

    public function message(string $id): ?InboundMail
    {
        $message = $this->graph->message($id);

        if (null === $message || true === ($message['isDraft'] ?? false)) {
            return null;
        }

        return self::toInboundMail($message);
    }

    public function attachments(string $messageId): array
    {
        return $this->graph->attachments($messageId);
    }

    public function attachmentContents(string $messageId, string $attachmentId): string
    {
        return $this->graph->attachmentContents($messageId, $attachmentId);
    }

    /**
     * A reply is drafted from the message it answers, which makes Microsoft
     * thread it and quote the original; otherwise a new draft starts a thread.
     * The id Microsoft gives the draft is the one the sent message keeps.
     */
    public function send(OutgoingMail $mail): SentMail
    {
        $content = [
            'body' => ['contentType' => 'html', 'content' => $mail->bodyHtml()],
            'toRecipients' => array_map(fn (string $address): array => ['emailAddress' => ['address' => $address]], $mail->to),
        ];

        $answeredId = $mail->answered?->externalId;
        $reply = null === $answeredId ? null : $this->graph->createReplyDraft($answeredId);

        $draft = null === $reply
            ? $this->graph->createDraft(['subject' => $mail->tagged($mail->subject), ...$content])
            : $this->graph->updateDraft(self::string($reply['id'] ?? null), [
                'subject' => $mail->tagged(is_string($reply['subject'] ?? null) ? $reply['subject'] : $mail->subject),
                ...$content,
            ]);

        $this->graph->sendDraft(self::string($draft['id'] ?? null));

        return new SentMail(
            is_string($draft['id'] ?? null) ? $draft['id'] : null,
            MessageId::normalize($draft['internetMessageId'] ?? null),
            is_string($draft['subject'] ?? null) ? $draft['subject'] : null,
        );
    }

    /**
     * The message's headers by lower-cased name; the first of each name wins.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, string>
     */
    private static function headers(array $message): array
    {
        $headers = [];
        $entries = Arr::get($message, 'internetMessageHeaders');

        foreach (is_array($entries) ? $entries : [] as $entry) {
            $name = is_array($entry) ? ($entry['name'] ?? null) : null;
            $value = is_array($entry) ? ($entry['value'] ?? null) : null;

            if (is_string($name) && is_string($value) && ! isset($headers[mb_strtolower($name)])) {
                $headers[mb_strtolower($name)] = $value;
            }
        }

        return $headers;
    }

    private static function address(mixed $address): ?string
    {
        $address = self::text($address);

        return null === $address ? null : mb_strtolower($address);
    }

    /**
     * @return list<string>
     */
    private static function addresses(mixed $recipients): array
    {
        $addresses = [];

        foreach (is_array($recipients) ? $recipients : [] as $recipient) {
            $address = self::address(Arr::get(is_array($recipient) ? $recipient : [], 'emailAddress.address'));

            if (null !== $address) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    private static function text(mixed $value): ?string
    {
        if ( ! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return '' === $value ? null : $value;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ( ! is_string($value) || '' === $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * An id Microsoft just gave us, which a draft always has. One without
     * is an answer we do not understand, and nothing is sent on a guess.
     */
    private static function string(mixed $value): string
    {
        if ( ! is_string($value) || '' === $value) {
            throw new UnexpectedValueException('Microsoft gave the draft no id.');
        }

        return $value;
    }
}
