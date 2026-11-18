<?php

declare(strict_types=1);

namespace Mom\Microsoft\Tests\Fixtures;

use Mom\Connectors\Connection;
use Mom\Connectors\CouldNotReachConnector;

/**
 * A connected Microsoft account whose token is whatever the test says.
 */
final class FakeConnection implements Connection
{
    /**
     * @param  list<string>  $options
     */
    public function __construct(
        private readonly string $token = 'access-token',
        private readonly array $options = [],
        private readonly ?CouldNotReachConnector $failure = null,
    ) {}

    public function connectionId(): string
    {
        return 'connection-1';
    }

    public function account(): string
    {
        return 'sam@acme.test';
    }

    public function settings(): array
    {
        return array_fill_keys($this->options, true);
    }

    public function hasOption(string $option): bool
    {
        return in_array($option, $this->options, true);
    }

    public function accessToken(): string
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return $this->token;
    }

    public function credentials(): array
    {
        return [];
    }
}
