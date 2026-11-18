<?php

declare(strict_types=1);

namespace Mom\Connectors;

/**
 * Every connector type and capability an app knows. Register them from a
 * service provider:
 *
 *     $this->callAfterResolving(ConnectorRegistry::class, function (ConnectorRegistry $registry): void {
 *         $registry->capability(new Capability('mail', 'Email', ProvidesMail::class));
 *         $registry->register(new MicrosoftConnector);
 *     });
 */
final class ConnectorRegistry
{
    /** @var array<string, ConnectorType> */
    private array $types = [];

    /** @var array<string, Capability> */
    private array $capabilities = [];

    /**
     * The registry the app is running with.
     */
    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Adds a type, or replaces the one registered under the same key.
     */
    public function register(ConnectorType ...$types): self
    {
        foreach ($types as $type) {
            $this->types[$type->key()] = $type;
        }

        return $this;
    }

    /**
     * Adds something connected accounts can be used for, or replaces the
     * capability registered under the same key.
     */
    public function capability(Capability ...$capabilities): self
    {
        foreach ($capabilities as $capability) {
            $this->capabilities[$capability->key] = $capability;
        }

        return $this;
    }

    /**
     * @return list<ConnectorType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }

    public function find(?string $key): ?ConnectorType
    {
        return null === $key ? null : ($this->types[$key] ?? null);
    }

    /**
     * The type under a key, or a stand-in that can do nothing when the type
     * is no longer installed, so an account left behind can still be listed.
     */
    public function get(?string $key): ConnectorType
    {
        return $this->find($key) ?? new UnknownConnectorType($key ?? '');
    }

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return array_values($this->capabilities);
    }

    /**
     * What a connected account of a type can be used for: every registered
     * capability whose contract the type implements.
     *
     * @return list<Capability>
     */
    public function capabilitiesOf(ConnectorType $type): array
    {
        return array_values(array_filter(
            $this->capabilities,
            fn (Capability $capability): bool => $type->provides($capability->contract),
        ));
    }

    /**
     * Every type that has a capability, by the capability's key.
     *
     * @return list<ConnectorType>
     */
    public function providing(string $capability): array
    {
        $contract = ($this->capabilities[$capability] ?? null)?->contract;

        return null === $contract
            ? []
            : array_values(array_filter($this->types, fn (ConnectorType $type): bool => $type->provides($contract)));
    }

    /**
     * A type for the page that offers it: what it is, and what the page
     * needs to connect one, which is a sign-in's options or a form's fields.
     *
     * @return array{value: string, label: string, description: string, logo: ?string, capabilities: list<string>, available: bool, connection: 'form'|'sign_in'|null, options: list<array{key: string, label: string, hint: ?string}>, fields: list<array<string, mixed>>, account_field: ?string, form_introduction: ?string}
     */
    public function describe(ConnectorType $type): array
    {
        return [
            'value' => $type->key(),
            'label' => $type->label(),
            'description' => $type->description(),
            'logo' => $type->logo(),
            'capabilities' => array_map(fn (Capability $capability): string => $capability->label, $this->capabilitiesOf($type)),
            'available' => $type->isAvailable(),
            'connection' => match (true) {
                $type instanceof ConnectsBySignIn => 'sign_in',
                $type instanceof ConnectsByForm => 'form',
                default => null,
            },
            'options' => $type instanceof ConnectsBySignIn
                ? array_map(fn (SignInOption $option): array => $option->toArray(), $type->signInOptions())
                : [],
            'fields' => $type instanceof ConnectsByForm
                ? array_map(fn (Field $field): array => $field->toArray(), $type->fields())
                : [],
            'account_field' => $type instanceof ConnectsByForm ? $type->accountField() : null,
            'form_introduction' => $type instanceof ConnectsByForm ? $type->formIntroduction() : null,
        ];
    }

    /**
     * Every type, described for the page that offers them.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return array_map($this->describe(...), $this->all());
    }
}
