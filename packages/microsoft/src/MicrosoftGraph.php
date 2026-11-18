<?php

declare(strict_types=1);

namespace Mom\Microsoft;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Mom\Connectors\Connection;
use Mom\Connectors\CouldNotReachConnector;
use Throwable;

/**
 * Microsoft Graph, for one mailbox. A person's own mailbox is reached as "me";
 * a shared mailbox is reached by its address, through the person who has
 * access to it.
 */
final readonly class MicrosoftGraph
{
    private const string BASE_URL = 'https://graph.microsoft.com/v1.0';

    private const int PAGE_SIZE = 100;

    private function __construct(
        private string $accessToken,
        private ?string $sharedAddress,
    ) {}

    /**
     * Through a connected Microsoft account: its own mailbox, or a shared
     * mailbox it can open when an address is given.
     *
     * @throws CouldNotReachConnector
     */
    public static function forConnection(Connection $connection, ?string $sharedAddress = null): self
    {
        return new self($connection->accessToken(), $sharedAddress);
    }

    /**
     * The mailbox's inbox folder. Asking for it is how we find out whether the
     * mailbox exists and the signed-in person may open it.
     *
     * @return array<string, mixed>
     *
     * @throws CouldNotReachConnector
     */
    public function inbox(): array
    {
        return self::json($this->get($this->path('mailFolders/inbox'), ['$select' => 'id,displayName']));
    }

    /**
     * The inbox's messages received at or after a time, oldest first: just
     * their ids and when they arrived, and no more than the limit.
     *
     * @return list<array{id: string, received_at: CarbonImmutable}>
     *
     * @throws CouldNotReachConnector
     */
    public function inboxMessagesSince(CarbonImmutable $since, int $limit): array
    {
        $messages = [];
        $url = $this->path('mailFolders/inbox/messages');
        $query = [
            '$filter' => 'receivedDateTime ge ' . $since->utc()->format('Y-m-d\TH:i:s\Z'),
            '$orderby' => 'receivedDateTime asc',
            '$select' => 'id,receivedDateTime',
            '$top' => (string) min($limit, self::PAGE_SIZE),
        ];

        while (null !== $url && count($messages) < $limit) {
            $page = self::json($this->get($url, $query));

            foreach (is_array($page['value'] ?? null) ? $page['value'] : [] as $message) {
                $id = is_array($message) ? ($message['id'] ?? null) : null;
                $receivedAt = is_array($message) ? ($message['receivedDateTime'] ?? null) : null;

                if (is_string($id) && is_string($receivedAt) && count($messages) < $limit) {
                    $messages[] = ['id' => $id, 'received_at' => CarbonImmutable::parse($receivedAt)->utc()];
                }
            }

            // The next page's address already carries the query.
            $next = $page['@odata.nextLink'] ?? null;
            $url = is_string($next) && '' !== $next ? $next : null;
            $query = [];
        }

        return $messages;
    }

    /**
     * One message with its headers, and its body as plain text. Null when it
     * is no longer in the mailbox.
     *
     * @return array<string, mixed>|null
     *
     * @throws CouldNotReachConnector
     */
    public function message(string $id): ?array
    {
        $response = $this->get(
            $this->path('messages/' . rawurlencode($id)),
            ['$select' => 'id,subject,from,toRecipients,receivedDateTime,internetMessageId,isDraft,hasAttachments,body,uniqueBody,internetMessageHeaders'],
            ['Prefer' => 'IdType="ImmutableId", outlook.body-content-type="text"'],
        );

        return $response->notFound() ? null : self::json($response);
    }

    /**
     * What is attached to a message: each attachment's id, name, type and
     * size in bytes, whether it is a file (not a linked or embedded item) and
     * whether it is an image shown inside the message. Never the contents.
     *
     * @return list<array{id: string, name: string, content_type: ?string, size: int, is_file: bool, is_inline: bool}>
     *
     * @throws CouldNotReachConnector
     */
    public function attachments(string $messageId): array
    {
        $attachments = [];
        $url = $this->path('messages/' . rawurlencode($messageId) . '/attachments');
        $query = ['$select' => 'id,name,contentType,size,isInline'];

        while (null !== $url) {
            $page = self::json($this->get($url, $query));

            foreach (is_array($page['value'] ?? null) ? $page['value'] : [] as $attachment) {
                $id = is_array($attachment) ? ($attachment['id'] ?? null) : null;

                if ( ! is_array($attachment) || ! is_string($id)) {
                    continue;
                }

                $type = $attachment['@odata.type'] ?? '';
                $name = $attachment['name'] ?? '';
                $contentType = $attachment['contentType'] ?? null;

                $attachments[] = [
                    'id' => $id,
                    'name' => is_string($name) ? $name : '',
                    'content_type' => is_string($contentType) ? $contentType : null,
                    'size' => is_numeric($attachment['size'] ?? null) ? (int) $attachment['size'] : 0,
                    'is_file' => is_string($type) && str_ends_with($type, 'fileAttachment'),
                    'is_inline' => ($attachment['isInline'] ?? false) === true,
                ];
            }

            $next = $page['@odata.nextLink'] ?? null;
            $url = is_string($next) && '' !== $next ? $next : null;
            $query = [];
        }

        return $attachments;
    }

    /**
     * The bytes of a file attached to a message.
     *
     * @throws CouldNotReachConnector
     */
    public function attachmentContents(string $messageId, string $attachmentId): string
    {
        $response = $this->get($this->path('messages/' . rawurlencode($messageId) . '/attachments/' . rawurlencode($attachmentId) . '/$value'));

        if ( ! $response->successful()) {
            self::json($response);
        }

        return $response->body();
    }

    /**
     * A draft reply to a message in the mailbox, which Microsoft threads
     * under it. Null when that message is no longer in the mailbox.
     *
     * @return array<string, mixed>|null
     *
     * @throws CouldNotReachConnector
     */
    public function createReplyDraft(string $messageId): ?array
    {
        $response = $this->write('post', $this->path('messages/' . rawurlencode($messageId) . '/createReply'));

        return $response->notFound() ? null : self::json($response);
    }

    /**
     * A new draft in the mailbox.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     *
     * @throws CouldNotReachConnector
     */
    public function createDraft(array $message): array
    {
        return self::json($this->write('post', $this->path('messages'), $message));
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     *
     * @throws CouldNotReachConnector
     */
    public function updateDraft(string $draftId, array $changes): array
    {
        return self::json($this->write('patch', $this->path('messages/' . rawurlencode($draftId)), $changes));
    }

    /**
     * Sends a draft, which Microsoft then keeps in Sent Items.
     *
     * @throws CouldNotReachConnector
     */
    public function sendDraft(string $draftId): void
    {
        self::json($this->write('post', $this->path('messages/' . rawurlencode($draftId) . '/send')));
    }

    /**
     * Failures worth another attempt: no connection, throttling and server errors.
     */
    private static function isTransient(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException
                && ($exception->response->serverError() || 429 === $exception->response->status()));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws CouldNotReachConnector
     */
    private static function json(Response $response): array
    {
        if ($response->successful()) {
            $body = $response->json();

            return is_array($body) ? $body : [];
        }

        if ($response->serverError() || 429 === $response->status()) {
            throw CouldNotReachConnector::unavailable(MicrosoftConnector::COMPANY);
        }

        if (401 === $response->status()) {
            throw CouldNotReachConnector::needsReconnect(MicrosoftConnector::COMPANY);
        }

        $message = $response->json('error.message');

        throw CouldNotReachConnector::refused(is_string($message) && '' !== $message
            ? $message
            : 'Microsoft refused the request.');
    }

    /**
     * A request that changes the mailbox. It is never retried here: sending
     * one twice could send a customer the same email twice.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws CouldNotReachConnector
     */
    private function write(string $method, string $url, array $body = []): Response
    {
        try {
            return $this->request()->send(mb_strtoupper($method), $url, [] === $body ? [] : ['json' => $body]);
        } catch (ConnectionException $exception) {
            throw CouldNotReachConnector::unavailable(MicrosoftConnector::COMPANY, $exception);
        }
    }

    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     *
     * @throws CouldNotReachConnector
     */
    private function get(string $url, array $query = [], array $headers = []): Response
    {
        try {
            $request = $this->request()
                ->withHeaders($headers)
                ->retry([200, 1000], when: self::isTransient(...), throw: false);

            // Passing an empty query would strip the one a next-page address carries.
            return [] === $query ? $request->get($url) : $request->get($url, $query);
        } catch (ConnectionException $exception) {
            throw CouldNotReachConnector::unavailable(MicrosoftConnector::COMPANY, $exception);
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken($this->accessToken)
            ->withHeaders(['Prefer' => 'IdType="ImmutableId"'])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);
    }

    private function path(string $path): string
    {
        $mailbox = null === $this->sharedAddress ? 'me' : 'users/' . rawurlencode($this->sharedAddress);

        return "{$mailbox}/{$path}";
    }
}
