@verbatim
## mom/connectors

`mom/connectors` describes the outside accounts an app connects (a Microsoft 365 sign-in, a Google account, a mail server). Each kind of account is one class extending `Mom\Connectors\ConnectorType`, registered with `Mom\Connectors\ConnectorRegistry`. The package is the core only: the app owns the table connected accounts are stored in, the routes, token refresh and the page.

### Adding a connector type

- Put the class in a folder of its own. A `logo.svg` beside it is picked up as its logo.
- Implement `key()`, `label()` and `description()`. Never change a key once accounts are connected.
- Say how an account is connected by implementing exactly one of:
  - `ConnectsBySignIn`: `signInDriver()` (a Socialite driver, with its keys in `config('services.{driver}')`), `signInOptions()`, `scopes()`, `signInParameters()` and `tokenUrl()`.
  - `ConnectsByForm`: `formIntroduction()`, `fields()`, `accountField()` and `connect()`.
- Say what a connected account can be used for by implementing the app's contract for each capability, such as `ProvidesMail`.
- Register it where the app registers the others. Don't add a route, a form request or a page for it: the existing ones are driven by the registry.

```php
$this->callAfterResolving(ConnectorRegistry::class, function (ConnectorRegistry $registry): void {
    $registry
        ->capability(new Capability('mail', 'Email', ProvidesMail::class))
        ->register(new MicrosoftConnector, new ImapConnector);
});
```

### Forms

- Build fields with `Field::text()`, `email()`, `password()`, `number()` and `select()`, then `required()`, `placeholder()`, `hint()`, `default()`, `group()`, `span()`, `rules()` and `message()`. Each call returns a new field.
- `connect(array $values)` receives values that already passed the fields' rules. It must try them against the service and return a `ConnectedAccount`, or throw `CouldNotConnect::because($field, $reason)` naming the field to look at.
- In `ConnectedAccount`, put secrets in `credentials` and everything else in `settings`. `settings` may be sent back to the form; `credentials` must be stored encrypted and never leave the server.
- When a field names a server to connect to, check `PublicHost::allows($host)` before connecting.

### Connected accounts

- A type is handed a `Mom\Connectors\Connection`, never the app's own class for a connected account. Ask it for `accessToken()` (sign-in types) or `credentials()` (form types) when needed; don't keep either.
- The app's class for a connected account implements `Connection`. Token refresh and decrypting credentials stay in the app.
- When the outside service will not let the type in, throw `CouldNotReachConnector::needsReconnect($company)`, `::unavailable($company, $previous)` or `::refused($reason)`. Don't invent other exceptions for this.

### Email

- A type whose accounts can be used for email implements `Mom\Connectors\Mail\ProvidesMail` and returns a `MailClient`.
- A `MailClient` returns messages as `InboundMail` (ids without angle brackets, addresses in lower case) and takes an `OutgoingMail` to send. `send()` must never retry: a second attempt could send the same email twice.
- Convert between `InboundMail` and the app's stored message in one place in the app, not inside a connector.

### Asking the registry

- Use `ConnectorRegistry::instance()->get($key)` for a stored account's type. It returns an `UnknownConnectorType` instead of null when the type is no longer installed.
- Ask for accounts by capability (`providing('mail')`, `$type->provides(ProvidesMail::class)`), never by a particular type's key or class.
- Use `catalog()` and `describe($type)` to give a page what it needs. Don't hard-code a type's label, options or fields in a page.
@endverbatim
