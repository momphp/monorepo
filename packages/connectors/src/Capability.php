<?php

declare(strict_types=1);

namespace Mom\Connectors;

/**
 * Something a connected account can be used for, such as email. The app
 * names each one and the contract a connector type implements to have it:
 *
 *     $registry->capability(new Capability('mail', 'Email', ProvidesMail::class));
 *
 * The contract is the app's own interface. A feature asks for the accounts
 * whose type implements it, and never for a particular type.
 */
final readonly class Capability
{
    /**
     * @param  string  $key  what the capability is known as in code, such as "mail"
     * @param  string  $label  what people see it as, such as "Email"
     * @param  class-string  $contract  the interface a connector type implements to have it
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $contract,
    ) {}
}
