<?php

declare(strict_types=1);

namespace Mom\Microsoft\Tests\Fixtures;

final class GraphMessage
{
    /**
     * An email as Microsoft Graph returns it when asked for its headers and
     * a plain-text body.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function make(array $overrides = []): array
    {
        return [
            'id' => 'graph-1',
            'subject' => 'Printer offline',
            'from' => ['emailAddress' => ['name' => 'Sam Lee', 'address' => 'Sam@Acme.test']],
            'toRecipients' => [['emailAddress' => ['name' => 'Support', 'address' => 'Support@Helpdesk.test']]],
            'receivedDateTime' => '2026-11-18T01:15:00Z',
            'internetMessageId' => '<message-1@acme.test>',
            'isDraft' => false,
            'hasAttachments' => false,
            'body' => ['contentType' => 'text', 'content' => "The printer on level 2 is offline.\r\n"],
            'uniqueBody' => ['contentType' => 'text', 'content' => "The printer on level 2 is offline.\r\n"],
            'internetMessageHeaders' => [
                ['name' => 'Received', 'value' => 'from mail.acme.test'],
                ['name' => 'Message-ID', 'value' => '<message-1@acme.test>'],
            ],
            ...$overrides,
        ];
    }
}
