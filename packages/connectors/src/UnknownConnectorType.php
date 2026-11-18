<?php

declare(strict_types=1);

namespace Mom\Connectors;

use Illuminate\Support\Str;

/**
 * Stands in for a type that is no longer installed. A connected account of that type
 * is still listed and can be disconnected, and can be used for nothing.
 */
final class UnknownConnectorType extends ConnectorType
{
    public function __construct(
        private readonly string $key,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return '' === $this->key ? (string) __('Unknown') : Str::headline($this->key);
    }

    public function description(): string
    {
        return (string) __('This kind of connector is no longer installed.');
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function logo(): ?string
    {
        return null;
    }
}
