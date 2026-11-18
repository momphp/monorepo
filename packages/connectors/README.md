# mom/connectors

A small SDK for describing the outside accounts a Laravel app connects, such as a Microsoft 365 sign-in, a Google account or a mail server.

Each kind of account is one class. It says what it is called, how an account is connected (by signing in, or by a form whose fields it declares) and what a connected account can be used for. The app asks a registry what exists and draws its pages from the answers, so adding a connector does not mean adding a page, a route or a request.

```php
$registry = ConnectorRegistry::instance();

$registry->catalog();                       // every type, described for a page
$registry->get('microsoft')->label();       // "Microsoft 365"
$registry->providing('mail');               // the types whose accounts can be used for email
```

## What this package is, and is not

It is the **core** only: the classes a connector is written against, and the registry.

Your app still owns everything about a *connected* account: the table it is stored in, the encrypted credentials, the routes that send someone to sign in and take them back, token refresh, and the page. The package gives those parts one shape to work with.

## Requirements

- PHP 8.4+
- Laravel 13.30+

## Installation

```bash
composer require mom/connectors
```

The service provider is discovered automatically. It registers `ConnectorRegistry` as a singleton.

## Quick start

### 1. Name what connected accounts can be used for

A capability is a key, a label and one of **your** interfaces. A feature asks for accounts whose type implements that interface, never for a particular type.

```php
namespace App\Mail\Contracts;

interface ProvidesMail
{
    public function mailClient(Connection $connection): MailClient;
}
```

### 2. Write a connector type

Put each type in a folder of its own. A `logo.svg` beside the class is picked up as its logo.

A type connected by **signing in** (OAuth 2, through Laravel Socialite):

```php
namespace App\Connectors\Microsoft;

use Mom\Connectors\ConnectorType;
use Mom\Connectors\ConnectsBySignIn;
use Mom\Connectors\SignInOption;

final class MicrosoftConnector extends ConnectorType implements ConnectsBySignIn, ProvidesMail
{
    public function key(): string { return 'microsoft'; }
    public function label(): string { return 'Microsoft 365'; }
    public function description(): string { return 'Outlook and Exchange Online mailboxes.'; }

    public function signInDriver(): string { return 'microsoft'; }   // config('services.microsoft.*')

    public function signInOptions(): array
    {
        return [new SignInOption('shared_mailboxes', 'This account opens shared mailboxes', summary: 'Shared mailboxes')];
    }

    public function scopes(array $options): array
    {
        return in_array('shared_mailboxes', $options, true)
            ? ['offline_access', 'Mail.ReadWrite.Shared']
            : ['offline_access', 'Mail.ReadWrite'];
    }

    public function signInParameters(): array { return ['prompt' => 'select_account']; }
    public function tokenUrl(): string { return 'https://login.microsoftonline.com/common/oauth2/v2.0/token'; }

    public function mailClient(Connection $connection): MailClient { /* … */ }
}
```

A type connected by **a form**:

```php
use Mom\Connectors\ConnectedAccount;
use Mom\Connectors\ConnectsByForm;
use Mom\Connectors\CouldNotConnect;
use Mom\Connectors\Field;

final class ImapConnector extends ConnectorType implements ConnectsByForm, ProvidesMail
{
    public function key(): string { return 'imap'; }
    public function label(): string { return 'IMAP/SMTP'; }
    public function description(): string { return 'Any mail provider, by its server settings.'; }
    public function formIntroduction(): string { return 'Your mail provider lists these settings.'; }
    public function accountField(): string { return 'address'; }

    public function fields(): array
    {
        return [
            Field::email('address', 'Email address')->required(),
            Field::text('host', 'Server')->required()->group('Incoming mail')->span(4),
            Field::number('port', 'Port')->required()->default(993)->group('Incoming mail')->span(2),
            Field::password('password', 'Password')->required()->group('Incoming mail'),
        ];
    }

    public function connect(array $values): ConnectedAccount
    {
        if (! PublicHost::allows($values['host'])) {
            throw CouldNotConnect::because('host', "We can't connect to that server.");
        }

        // Sign in to the server here; throw CouldNotConnect::because('password', '…') when it refuses.

        return new ConnectedAccount(
            account: $values['address'],
            name: $values['address'],
            settings: ['host' => $values['host'], 'port' => $values['port']],   // may be shown again
            credentials: ['password' => $values['password']],                     // store encrypted
        );
    }
}
```

### 3. Register them

```php
use Mom\Connectors\Capability;
use Mom\Connectors\ConnectorRegistry;

public function register(): void
{
    $this->callAfterResolving(ConnectorRegistry::class, function (ConnectorRegistry $registry): void {
        $registry
            ->capability(new Capability('mail', 'Email', ProvidesMail::class))
            ->register(new MicrosoftConnector, new ImapConnector);
    });
}
```

## Using the registry

| Call | Gives |
|---|---|
| `all()` | Every type, in the order registered. |
| `find($key)` | The type, or `null`. |
| `get($key)` | The type, or an `UnknownConnectorType` that can do nothing. Use it for stored accounts, so one whose type was uninstalled still lists. |
| `capabilitiesOf($type)` | The capabilities whose contract the type implements. |
| `providing('mail')` | The types that have a capability. |
| `describe($type)` / `catalog()` | A type, or every type, as an array for a page: label, description, logo, capabilities, whether it is available, and its sign-in options or form fields. |

`$type->provides(ProvidesMail::class)` asks one type directly.

A sign-in type is **available** when `services.{driver}.client_id` and `client_secret` are set. A form type is always available.

## Form fields

`Field::text()`, `email()`, `password()`, `number()` and `select($name, $label, $options)` start a field. Each call below returns a new field:

| Call | Does |
|---|---|
| `required()` | Makes it required. |
| `placeholder()`, `hint()` | Text in and under the field. |
| `default()` | What it starts as on an empty form. |
| `group('Title')` | Shows it in a titled box with the others of the group. |
| `span(1–6)` | How many of a group's six columns it takes. |
| `rules([...])` | Validation rules on top of the ones its kind brings. |
| `message('regex', '…')` | What to say when a rule fails. |

`$field->validationRules()` gives the rules for a form request, `$field->messages` the custom messages, and `$field->toArray()` what a page needs to draw it. A password is a secret: `isSecret()` is true, and what was typed should never be sent back to the page.

## Servers someone typed in

A form may name a server your app then connects to. `PublicHost::allows($host)` refuses hosts that resolve to this machine, a private network or a link-local address, so a connector cannot be pointed at services only your server can reach. Check it when the form is saved and again whenever you connect.

To allow such servers, for example on a self-hosted install:

```dotenv
CONNECTORS_ALLOW_PRIVATE_HOSTS=true
```

Publish the config file with `php artisan vendor:publish --tag=connectors-config`.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
