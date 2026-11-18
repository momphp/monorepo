# mom/microsoft

A Microsoft 365 connector for [`mom/connectors`](https://github.com/momphp/connectors). An account is connected by signing in with Microsoft, and its mailbox, or a shared mailbox it can open, is read and sent from through Microsoft Graph.

```php
$type = new MicrosoftConnector;

$client = $type->mailClient($connection);                        // the account's own mailbox
$client = $type->mailClient($connection, 'support@acme.com');    // a shared mailbox it can open

$client->inboxMessagesSince($since, 50);    // ids and arrival times, oldest first
$client->message($id);                      // an InboundMail, or null
$client->send($outgoingMail);               // a SentMail
```

## Requirements

- PHP 8.4+
- Laravel 13.30+
- `mom/connectors`
- A Laravel Socialite driver named `microsoft`, such as [`socialiteproviders/microsoft`](https://socialiteproviders.com/Microsoft/)

## Installation

```bash
composer require mom/microsoft
```

Give Socialite its keys, as for Microsoft sign-in:

```php
// config/services.php
'microsoft' => [
    'client_id' => env('MICROSOFT_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
    'redirect' => '/auth/microsoft/callback',
    'tenant' => env('MICROSOFT_TENANT', 'common'),
],
```

Then register the connector where your app registers the others:

```php
use Mom\Connectors\Capability;
use Mom\Connectors\ConnectorRegistry;
use Mom\Connectors\Mail\ProvidesMail;
use Mom\Microsoft\MicrosoftConnector;

$this->callAfterResolving(ConnectorRegistry::class, function (ConnectorRegistry $registry): void {
    $registry
        ->capability(new Capability('mail', 'Email', ProvidesMail::class))
        ->register(new MicrosoftConnector);
});
```

The package does not register itself, so the order connectors are offered in stays your app's choice. It is available once both keys are set.

## What your app provides

This is a connector, not a whole feature. Your app sends the person to Microsoft and back, stores the connected account and refreshes its tokens. The connector tells it what to ask for:

| Call | Gives |
|---|---|
| `signInDriver()` | `microsoft`, the Socialite driver. |
| `signInOptions()` | One option, `shared_mailboxes`: "This account opens shared mailboxes". |
| `scopes($options)` | `offline_access`, `User.Read`, `Mail.ReadWrite`, `Mail.Send`; or the `.Shared` mail permissions when that option is chosen. Those only exist for work and school accounts. |
| `signInParameters()` | `prompt=select_account`. |
| `tokenUrl()` | The token address for `services.microsoft.tenant`, or `common`. |

Your class for a connected account implements `Mom\Connectors\Connection`. The connector asks it for `accessToken()` and for `hasOption('shared_mailboxes')`.

On the app registration in Microsoft Entra, add your callback address as a redirect URI and the delegated permissions `Mail.ReadWrite`, `Mail.Send`, `Mail.ReadWrite.Shared`, `Mail.Send.Shared` and `offline_access`.

## How it talks to Microsoft

- Graph v1.0, through Laravel's `Http` client, so `Http::fake()` works in your tests.
- A person's own mailbox is reached as `me`, a shared mailbox as `users/{address}` through the person who can open it.
- Message ids are immutable ids (`Prefer: IdType="ImmutableId"`), so they stay the same when a message is moved.
- Reads are retried on throttling and server errors. Sending is never retried: a second attempt could send the same email twice.
- A reply is drafted from the message it answers with `createReply`, so Microsoft threads it and quotes the original. When that message has gone, a new thread is started.
- What the sender wrote, apart from the quoted history, comes from Graph's `uniqueBody`.

Failures are thrown as `Mom\Connectors\CouldNotReachConnector`: a 401 as needing a reconnect, throttling and server errors as temporary, anything else with Microsoft's own reason.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
