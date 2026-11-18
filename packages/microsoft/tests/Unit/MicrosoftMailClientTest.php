<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mom\Connectors\CouldNotReachConnector;
use Mom\Connectors\Mail\InboundMail;
use Mom\Connectors\Mail\OutgoingMail;
use Mom\Microsoft\MicrosoftGraph;
use Mom\Microsoft\MicrosoftMailClient;
use Mom\Microsoft\Tests\Fixtures\FakeConnection;
use Mom\Microsoft\Tests\Fixtures\GraphMessage;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

function microsoftMailClient(): MicrosoftMailClient
{
    return new MicrosoftMailClient(MicrosoftGraph::forConnection(new FakeConnection));
}

it('turns a Graph message into an email, with ids unwrapped and addresses in lower case', function (): void {
    $mail = MicrosoftMailClient::toInboundMail(GraphMessage::make([
        'hasAttachments' => true,
        'body' => ['contentType' => 'text', 'content' => "It is the one on level 2.\r\n\r\nOn Monday Help Desk wrote:\r\n> Which printer?\r\n"],
        'uniqueBody' => ['contentType' => 'text', 'content' => "It is the one on level 2.\r\n"],
        'internetMessageHeaders' => [
            ['name' => 'In-Reply-To', 'value' => '<sent-1@helpdesk.test>'],
            ['name' => 'References', 'value' => '<message-0@acme.test> <sent-1@helpdesk.test>'],
            ['name' => 'references', 'value' => '<ignored@acme.test>'],
        ],
    ]));

    expect($mail->externalId)->toBe('graph-1')
        ->and($mail->messageId)->toBe('message-1@acme.test')
        ->and($mail->inReplyTo)->toBe('sent-1@helpdesk.test')
        ->and($mail->references)->toBe(['message-0@acme.test', 'sent-1@helpdesk.test'])
        ->and($mail->fromAddress)->toBe('sam@acme.test')
        ->and($mail->fromName)->toBe('Sam Lee')
        ->and($mail->to)->toBe(['support@helpdesk.test'])
        ->and($mail->subject)->toBe('Printer offline')
        ->and($mail->bodyText)->toStartWith('It is the one on level 2.')->toContain('> Which printer?')
        ->and($mail->replyText)->toBe('It is the one on level 2.')
        ->and($mail->receivedAt?->toIso8601String())->toBe('2026-11-18T01:15:00+00:00')
        ->and($mail->hasAttachments)->toBeTrue();
});

it('makes do with a message that has almost nothing in it', function (): void {
    $mail = MicrosoftMailClient::toInboundMail(['id' => 'graph-9', 'internetMessageId' => null, 'receivedDateTime' => 'not a date']);

    expect($mail->externalId)->toBe('graph-9')
        ->and($mail->messageId)->toBeNull()
        ->and($mail->references)->toBe([])
        ->and($mail->fromAddress)->toBeNull()
        ->and($mail->to)->toBe([])
        ->and($mail->bodyText)->toBeNull()
        ->and($mail->replyText)->toBeNull()
        ->and($mail->receivedAt)->toBeNull()
        ->and($mail->hasAttachments)->toBeFalse();
});

it('has no message for a draft or one that has gone', function (): void {
    Http::fake([
        'graph.microsoft.com/v1.0/me/messages/graph-draft*' => Http::response(GraphMessage::make(['id' => 'graph-draft', 'isDraft' => true])),
        'graph.microsoft.com/v1.0/me/messages/graph-gone*' => Http::response(['error' => ['code' => 'ErrorItemNotFound']], 404),
        'graph.microsoft.com/v1.0/me/messages/graph-1*' => Http::response(GraphMessage::make()),
    ]);

    expect(microsoftMailClient()->message('graph-draft'))->toBeNull()
        ->and(microsoftMailClient()->message('graph-gone'))->toBeNull()
        ->and(microsoftMailClient()->message('graph-1'))->toBeInstanceOf(InboundMail::class);
});

it('starts a thread from a new draft and keeps the id Microsoft gave it', function (): void {
    Http::fake([
        'graph.microsoft.com/v1.0/me/messages' => Http::response(['id' => 'draft-1', 'internetMessageId' => '<sent-9@helpdesk.test>', 'subject' => 'Printer offline [#12]'], 201),
        'graph.microsoft.com/v1.0/me/messages/draft-1/send' => Http::response(null, 202),
    ]);

    $sent = microsoftMailClient()->send(new OutgoingMail(['kim@acme.test'], "We are on it.\n<b>Not markup</b>", 'Printer offline', '[#12]'));

    expect($sent->externalId)->toBe('draft-1')
        ->and($sent->internetMessageId)->toBe('sent-9@helpdesk.test')
        ->and($sent->subject)->toBe('Printer offline [#12]');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => 'POST' === $request->method()
        && str_ends_with($request->url(), '/me/messages')
        && 'Printer offline [#12]' === $request['subject']
        && $request['body'] === ['contentType' => 'html', 'content' => "We are on it.<br>\n&lt;b&gt;Not markup&lt;/b&gt;"]
        && $request['toRecipients'] === [['emailAddress' => ['address' => 'kim@acme.test']]]);
});

it('replies from a draft of the message it answers, so Microsoft threads it', function (): void {
    Http::fake([
        'graph.microsoft.com/v1.0/me/messages/graph-1/createReply' => Http::response(['id' => 'draft-2', 'subject' => 'RE: Printer offline'], 201),
        'graph.microsoft.com/v1.0/me/messages/draft-2' => Http::response(['id' => 'draft-2', 'internetMessageId' => '<sent-10@helpdesk.test>', 'subject' => 'RE: Printer offline [#12]']),
        'graph.microsoft.com/v1.0/me/messages/draft-2/send' => Http::response(null, 202),
    ]);

    $sent = microsoftMailClient()->send(new OutgoingMail(['kim@acme.test'], 'Which printer is it?', 'A ticket title', '[#12]', new InboundMail('graph-1', 'message-1@acme.test')));

    expect($sent->subject)->toBe('RE: Printer offline [#12]')
        ->and($sent->internetMessageId)->toBe('sent-10@helpdesk.test');

    Http::assertSent(fn (Request $request): bool => 'PATCH' === $request->method()
        && str_ends_with($request->url(), '/me/messages/draft-2')
        && 'RE: Printer offline [#12]' === $request['subject']);
});

it('starts a new thread when the message it would answer has gone', function (): void {
    Http::fake([
        'graph.microsoft.com/v1.0/me/messages/graph-gone/createReply' => Http::response(['error' => ['code' => 'ErrorItemNotFound']], 404),
        'graph.microsoft.com/v1.0/me/messages' => Http::response(['id' => 'draft-3', 'subject' => 'A ticket title [#12]'], 201),
        'graph.microsoft.com/v1.0/me/messages/draft-3/send' => Http::response(null, 202),
    ]);

    $sent = microsoftMailClient()->send(new OutgoingMail(['kim@acme.test'], 'Which printer is it?', 'A ticket title', '[#12]', new InboundMail('graph-gone', 'message-1@acme.test')));

    expect($sent->externalId)->toBe('draft-3')
        ->and($sent->subject)->toBe('A ticket title [#12]');
});

it('never sends twice, and says why it could not send', function (): void {
    Http::fake([
        'graph.microsoft.com/v1.0/me/messages' => Http::response(['id' => 'draft-1'], 201),
        'graph.microsoft.com/v1.0/me/messages/draft-1/send' => Http::response(['error' => ['message' => 'Service unavailable']], 503),
    ]);

    expect(fn () => microsoftMailClient()->send(new OutgoingMail(['kim@acme.test'], 'We are on it.', 'Printer offline', '[#12]')))
        ->toThrow(CouldNotReachConnector::class, 'Microsoft could not be reached. It will be tried again.');

    Http::assertSentCount(2);
});

it('sends nothing when Microsoft gives the draft no id', function (): void {
    Http::fake(['graph.microsoft.com/v1.0/me/messages' => Http::response(['subject' => 'Printer offline [#12]'], 201)]);

    expect(fn () => microsoftMailClient()->send(new OutgoingMail(['kim@acme.test'], 'We are on it.', 'Printer offline', '[#12]')))
        ->toThrow(UnexpectedValueException::class, 'Microsoft gave the draft no id.');

    Http::assertSentCount(1);
});
