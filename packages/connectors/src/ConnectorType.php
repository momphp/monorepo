<?php

declare(strict_types=1);

namespace Mom\Connectors;

use ReflectionClass;

/**
 * A kind of outside account an app can connect. To add one, extend this in
 * a folder of its own and register it with the ConnectorRegistry.
 *
 * A type says how an account is connected, by implementing ConnectsBySignIn
 * or ConnectsByForm, and what a connected account can be used for, by
 * implementing the contract of each capability the app has registered.
 */
abstract class ConnectorType
{
    /**
     * What a connected account of this type is stored as. Never change it
     * once accounts are connected.
     */
    abstract public function key(): string;

    /**
     * What people see the type as, such as "Microsoft 365".
     */
    abstract public function label(): string;

    /**
     * What it connects, in a line, for the page that offers it.
     */
    abstract public function description(): string;

    /**
     * Who runs the service, as error messages name it.
     */
    public function company(): string
    {
        return $this->label();
    }

    /**
     * Whether this installation is set up to connect accounts of this type.
     * A sign-in type needs its keys; anything else is ready as it is.
     */
    public function isAvailable(): bool
    {
        if ( ! $this instanceof ConnectsBySignIn) {
            return true;
        }

        $driver = $this->signInDriver();

        return filled(config("services.{$driver}.client_id")) && filled(config("services.{$driver}.client_secret"));
    }

    /**
     * The type's logo as an image address a page can show as it is. By
     * default this is the logo.svg file beside the class; none means the
     * page shows a plain icon.
     */
    public function logo(): ?string
    {
        $path = dirname((string) (new ReflectionClass($this))->getFileName()) . '/logo.svg';
        $svg = is_file($path) ? file_get_contents($path) : false;

        return false === $svg ? null : 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Whether a connected account of this type can be used for something:
     * whether the type implements that capability's contract.
     *
     * @param  class-string  $contract
     */
    final public function provides(string $contract): bool
    {
        return is_a($this, $contract);
    }
}
