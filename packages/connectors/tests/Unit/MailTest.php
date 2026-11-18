<?php

declare(strict_types=1);

use Mom\Connectors\CouldNotReachConnector;
use Mom\Connectors\Mail\InboundMail;
use Mom\Connectors\Mail\MessageId;
use Mom\Connectors\Mail\OutgoingMail;
use Symfony\Component\Mime\Address;

it('unwraps a Message-ID and has none for anything that is not one', function (mixed $id, ?string $normalized): void {
    expect(MessageId::normalize($id))->toBe($normalized);
})->with([
    'in angle brackets' => ['<msg-1@acme.test>', 'msg-1@acme.test'],
    'with space around it' => ["  <msg-1@acme.test>\r\n", 'msg-1@acme.test'],
    'already bare' => ['msg-1@acme.test', 'msg-1@acme.test'],
    'empty' => ['<>', null],
    'not text' => [123, null],
    'nothing' => [null, null],
]);

it('reads every id in a References header', function (): void {
    expect(MessageId::all("<a@acme.test>\r\n <b@acme.test>, <c@acme.test>"))->toBe(['a@acme.test', 'b@acme.test', 'c@acme.test'])
        ->and(MessageId::all(null))->toBe([])
        ->and(MessageId::all(''))->toBe([]);
});

it('puts the marker in a subject once', function (): void {
    $mail = new OutgoingMail(['kim@acme.test'], 'Hello', 'Printer offline', '[#12]');

    expect($mail->tagged('Printer offline'))->toBe('Printer offline [#12]')
        ->and($mail->tagged('RE: Printer offline [#12]'))->toBe('RE: Printer offline [#12]')
        ->and($mail->subjectLine())->toBe('Printer offline [#12]');
});

it('answers under the subject of the message it replies to', function (string $answered, string $subject): void {
    $mail = new OutgoingMail(['kim@acme.test'], 'Hello', 'A ticket title', '[#12]', new InboundMail('outside-1', 'msg-1@acme.test', subject: $answered));

    expect($mail->subjectLine())->toBe($subject);
})->with([
    'a first message' => ['Printer offline', 'Re: Printer offline [#12]'],
    'already a reply' => ['RE: Printer offline', 'RE: Printer offline [#12]'],
    'already marked' => ['Re: Printer offline [#12]', 'Re: Printer offline [#12]'],
]);

it('builds a new thread as a whole email with an id of its own', function (): void {
    $mail = new OutgoingMail(['kim@acme.test'], "We are on it.\n<b>Not markup</b>", 'Printer offline', '[#12]');
    $email = $mail->toEmail(new Address('help@helpdesk.test', 'Help Desk'));

    expect($email->getFrom()[0]->toString())->toBe('"Help Desk" <help@helpdesk.test>')
        ->and($email->getTo()[0]->getAddress())->toBe('kim@acme.test')
        ->and($email->getSubject())->toBe('Printer offline [#12]')
        ->and($email->getTextBody())->toBe("We are on it.\n<b>Not markup</b>")
        ->and($email->getHtmlBody())->toBe("We are on it.<br>\n&lt;b&gt;Not markup&lt;/b&gt;")
        ->and($email->getHeaders()->has('In-Reply-To'))->toBeFalse()
        ->and(OutgoingMail::messageId($email))->toEndWith('@helpdesk.test')
        ->and($email->getHeaders()->getHeaderBody('Message-ID'))->toBe([OutgoingMail::messageId($email)]);
});

it('builds a reply that names the message it answers and the thread before it', function (): void {
    $answered = new InboundMail('outside-1', 'msg-1@acme.test', references: ['msg-0@acme.test'], subject: 'Printer offline');
    $headers = (new OutgoingMail(['kim@acme.test'], 'Which printer?', 'A ticket title', '[#12]', $answered))
        ->toEmail(new Address('help@helpdesk.test'))
        ->getHeaders();

    expect($headers->getHeaderBody('In-Reply-To'))->toBe(['msg-1@acme.test'])
        ->and($headers->getHeaderBody('References'))->toBe(['msg-0@acme.test', 'msg-1@acme.test']);
});

it('says how a connector could not be reached', function (): void {
    $needsReconnect = CouldNotReachConnector::needsReconnect('Microsoft');
    $unavailable = CouldNotReachConnector::unavailable('Google', new RuntimeException('timed out'));
    $refused = CouldNotReachConnector::refused('Access is denied.');

    expect($needsReconnect->getMessage())->toBe('Microsoft no longer accepts the sign-in for this connector. Connect it again.')
        ->and($needsReconnect->needsReconnect)->toBeTrue()
        ->and($needsReconnect->isTemporary)->toBeFalse()
        ->and($needsReconnect->company)->toBe('Microsoft')
        ->and($unavailable->getMessage())->toBe('Google could not be reached. It will be tried again.')
        ->and($unavailable->isTemporary)->toBeTrue()
        ->and($unavailable->needsReconnect)->toBeFalse()
        ->and($unavailable->getPrevious()?->getMessage())->toBe('timed out')
        ->and($refused->getMessage())->toBe('Access is denied.')
        ->and($refused->isTemporary)->toBeFalse()
        ->and($refused->needsReconnect)->toBeFalse()
        ->and($refused->company)->toBeNull();
});
