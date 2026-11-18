<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Mom\Connectors\CouldNotReachConnector;
use Mom\Microsoft\MicrosoftGraph;
use Mom\Microsoft\Tests\Fixtures\FakeConnection;
use Mom\Microsoft\Tests\Fixtures\GraphMessage;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('lists the inbox oldest first from a time, following pages until it has enough', function (): void {
    Http::fake(fn (Request $request) => match (true) {
        str_contains(urldecode($request->url()), '$skip=2') => Http::response(['value' => [
            ['id' => 'graph-3', 'receivedDateTime' => '2026-11-18T01:03:00Z'],
            ['id' => 'graph-4', 'receivedDateTime' => '2026-11-18T01:04:00Z'],
        ], '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?%24skip=4']),
        default => Http::response(['value' => [
            ['id' => 'graph-1', 'receivedDateTime' => '2026-11-18T01:01:00Z'],
            ['id' => 'graph-2', 'receivedDateTime' => '2026-11-18T01:02:00+00:00'],
        ], '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?%24skip=2']),
    });

    $messages = MicrosoftGraph::forConnection(new FakeConnection)->inboxMessagesSince(CarbonImmutable::parse('2026-11-18 11:00:00', 'Australia/Brisbane'), 3);

    expect(array_column($messages, 'id'))->toBe(['graph-1', 'graph-2', 'graph-3'])
        ->and($messages[1]['received_at']->toIso8601ZuluString())->toBe('2026-11-18T01:02:00Z');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => ($request['$filter'] ?? null) === 'receivedDateTime ge 2026-11-18T01:00:00Z'
        && 'receivedDateTime asc' === $request['$orderby']
        && 'id,receivedDateTime' === $request['$select']
        && '3' === $request['$top']);
});

it('reads a message with its headers and a plain-text body', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/messages/graph-1*' => Http::response(GraphMessage::make())]);

    expect(MicrosoftGraph::forConnection(new FakeConnection)->message('graph-1'))->toBe(GraphMessage::make());

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Prefer', 'IdType="ImmutableId", outlook.body-content-type="text"')
        && str_contains($request['$select'], 'internetMessageHeaders')
        && str_contains($request['$select'], 'uniqueBody'));
});

it('has no message when it has gone from the mailbox', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/messages/graph-1*' => Http::response(['error' => ['code' => 'ErrorItemNotFound']], 404)]);

    expect(MicrosoftGraph::forConnection(new FakeConnection)->message('graph-1'))->toBeNull();
});

it('lists what is attached to a message without fetching the files', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/messages/graph-1/attachments?*' => Http::response(['value' => [
        ['@odata.type' => '#microsoft.graph.fileAttachment', 'id' => 'file-1', 'name' => 'quote.pdf', 'contentType' => 'application/pdf', 'size' => 1200, 'isInline' => false],
        ['@odata.type' => '#microsoft.graph.fileAttachment', 'id' => 'file-2', 'name' => 'logo.png', 'contentType' => 'image/png', 'size' => 300, 'isInline' => true],
        ['@odata.type' => '#microsoft.graph.itemAttachment', 'id' => 'item-1', 'name' => 'Forwarded message', 'contentType' => null, 'size' => 4000, 'isInline' => false],
        ['name' => 'An attachment with no id'],
    ]])]);

    expect(MicrosoftGraph::forConnection(new FakeConnection)->attachments('graph-1'))->toBe([
        ['id' => 'file-1', 'name' => 'quote.pdf', 'content_type' => 'application/pdf', 'size' => 1200, 'is_file' => true, 'is_inline' => false],
        ['id' => 'file-2', 'name' => 'logo.png', 'content_type' => 'image/png', 'size' => 300, 'is_file' => true, 'is_inline' => true],
        ['id' => 'item-1', 'name' => 'Forwarded message', 'content_type' => null, 'size' => 4000, 'is_file' => false, 'is_inline' => false],
    ]);

    Http::assertSent(fn (Request $request): bool => 'id,name,contentType,size,isInline' === $request['$select']);
});

it('fetches a file attached to a message as it is', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/messages/graph-1/attachments/file-1/$value' => Http::response("\x89PNG\r\n")]);

    expect(MicrosoftGraph::forConnection(new FakeConnection)->attachmentContents('graph-1', 'file-1'))->toBe("\x89PNG\r\n");
});

it('says why a file attached to a message could not be fetched', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/messages/graph-1/attachments/file-1/$value' => Http::response(['error' => ['code' => 'ErrorItemNotFound', 'message' => 'The specified object was not found in the store.']], 404)]);

    expect(fn () => MicrosoftGraph::forConnection(new FakeConnection)->attachmentContents('graph-1', 'file-1'))
        ->toThrow(CouldNotReachConnector::class, 'The specified object was not found in the store.');
});

it('passes on why Microsoft refused', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/mailFolders/inbox*' => Http::response(['error' => ['code' => 'ErrorAccessDenied', 'message' => 'Access is denied.']], 403)]);

    expect(fn () => MicrosoftGraph::forConnection(new FakeConnection)->inbox())
        ->toThrow(CouldNotReachConnector::class, 'Access is denied.');
});

it('asks for a reconnect when the token is no longer accepted', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/mailFolders/inbox*' => Http::response(['error' => ['code' => 'InvalidAuthenticationToken']], 401)]);

    expect(fn () => MicrosoftGraph::forConnection(new FakeConnection)->inbox())
        ->toThrow(CouldNotReachConnector::class, 'Microsoft no longer accepts the sign-in for this connector. Connect it again.');
});

it('tries again when Microsoft is busy, then reads the mailbox', function (): void {
    Sleep::fake();
    Http::fake(['graph.microsoft.com/v1.0/me/mailFolders/inbox*' => Http::sequence()
        ->push(['error' => ['code' => 'serviceNotAvailable']], 503)
        ->push(['id' => 'inbox-id'])]);

    expect(MicrosoftGraph::forConnection(new FakeConnection)->inbox())->toBe(['id' => 'inbox-id']);

    Http::assertSentCount(2);
});

it('gives up for now when Microsoft stays unavailable', function (Closure $response): void {
    Sleep::fake();
    Http::fake(['graph.microsoft.com/v1.0/me/mailFolders/inbox*' => $response()]);

    expect(fn () => MicrosoftGraph::forConnection(new FakeConnection)->inbox())
        ->toThrow(CouldNotReachConnector::class, 'Microsoft could not be reached. It will be tried again.');

    Http::assertSentCount(3);
})->with([
    'a server error' => [fn () => Http::response(['error' => ['code' => 'serviceNotAvailable']], 503)],
    'throttling' => [fn () => Http::response(['error' => ['code' => 'TooManyRequests']], 429)],
    'no connection' => [fn () => Http::failedConnection()],
]);

it('reads a person\'s own mailbox as "me" with the connection\'s token', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/mailFolders/inbox*' => Http::response(['id' => 'inbox-id'])]);

    expect(MicrosoftGraph::forConnection(new FakeConnection('the-token'))->inbox())->toBe(['id' => 'inbox-id']);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer the-token')
        && $request->hasHeader('Prefer', 'IdType="ImmutableId"'));
});

it('reads a shared mailbox by its address', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/users/support%40acme.test/mailFolders/inbox*' => Http::response(['id' => 'inbox-id'])]);

    expect(MicrosoftGraph::forConnection(new FakeConnection, 'support@acme.test')->inbox())->toBe(['id' => 'inbox-id']);
});

it('asks Microsoft nothing when the app cannot give a token', function (): void {
    Http::fake();
    $connection = new FakeConnection(failure: CouldNotReachConnector::needsReconnect('Microsoft'));

    expect(fn () => MicrosoftGraph::forConnection($connection))->toThrow(CouldNotReachConnector::class);

    Http::assertNothingSent();
});
