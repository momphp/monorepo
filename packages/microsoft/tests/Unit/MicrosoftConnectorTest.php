<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mom\Connectors\ConnectorType;
use Mom\Connectors\ConnectsBySignIn;
use Mom\Connectors\Mail\ProvidesMail;
use Mom\Microsoft\MicrosoftConnector;
use Mom\Microsoft\MicrosoftMailClient;
use Mom\Microsoft\Tests\Fixtures\FakeConnection;

it('is a sign-in connector that provides mail', function (): void {
    $type = new MicrosoftConnector;

    expect($type)->toBeInstanceOf(ConnectorType::class)->toBeInstanceOf(ConnectsBySignIn::class)->toBeInstanceOf(ProvidesMail::class)
        ->and($type->key())->toBe('microsoft')
        ->and($type->label())->toBe('Microsoft 365')
        ->and($type->company())->toBe('Microsoft')
        ->and($type->signInDriver())->toBe('microsoft')
        ->and($type->signInParameters())->toBe(['prompt' => 'select_account'])
        ->and($type->logo())->toStartWith('data:image/svg+xml;base64,');
});

it('is available only when the Microsoft keys are set', function (): void {
    config(['services.microsoft.client_id' => null, 'services.microsoft.client_secret' => null]);

    expect((new MicrosoftConnector)->isAvailable())->toBeFalse();

    config(['services.microsoft.client_id' => 'id', 'services.microsoft.client_secret' => 'secret']);

    expect((new MicrosoftConnector)->isAvailable())->toBeTrue();
});

it('asks for shared-mailbox permissions only when that option is chosen', function (): void {
    $type = new MicrosoftConnector;

    expect($type->signInOptions())->toHaveCount(1)
        ->and($type->signInOptions()[0]->key)->toBe('shared_mailboxes')
        ->and($type->scopes([]))->toBe(['offline_access', 'User.Read', 'Mail.ReadWrite', 'Mail.Send'])
        ->and($type->scopes(['shared_mailboxes']))->toBe(['offline_access', 'User.Read', 'Mail.ReadWrite.Shared', 'Mail.Send.Shared']);
});

it('exchanges tokens at the configured tenant, or the common one', function (): void {
    config(['services.microsoft.tenant' => null]);

    expect((new MicrosoftConnector)->tokenUrl())->toBe('https://login.microsoftonline.com/common/oauth2/v2.0/token');

    config(['services.microsoft.tenant' => 'acme.onmicrosoft.test']);

    expect((new MicrosoftConnector)->tokenUrl())->toBe('https://login.microsoftonline.com/acme.onmicrosoft.test/oauth2/v2.0/token');
});

it('opens shared mailboxes only for a connection signed in with that option', function (): void {
    $type = new MicrosoftConnector;

    expect($type->opensSharedMailboxes(new FakeConnection))->toBeFalse()
        ->and($type->opensSharedMailboxes(new FakeConnection(options: ['shared_mailboxes'])))->toBeTrue();
});

it('gives a mail client for the connection\'s own mailbox or a shared one', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'graph.microsoft.com/v1.0/me/mailFolders/inbox*' => Http::response(['id' => 'own']),
        'graph.microsoft.com/v1.0/users/support%40acme.test/mailFolders/inbox*' => Http::response(['id' => 'shared']),
    ]);
    $type = new MicrosoftConnector;

    $own = $type->mailClient(new FakeConnection);
    $own->checkInbox();
    $type->mailClient(new FakeConnection, 'support@acme.test')->checkInbox();

    expect($own)->toBeInstanceOf(MicrosoftMailClient::class);

    Http::assertSentCount(2);
});
