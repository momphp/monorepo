<?php

declare(strict_types=1);

namespace Mom\Connectors\Tests\Fixtures\Postbox;

use Mom\Connectors\ConnectorType;
use Mom\Connectors\ConnectsBySignIn;
use Mom\Connectors\SignInOption;
use Mom\Connectors\Tests\Fixtures\ProvidesPost;
use Mom\Connectors\Tests\Fixtures\ProvidesReadings;

/**
 * A type connected by signing in, with no logo, that has two capabilities.
 */
final class PostboxConnector extends ConnectorType implements ConnectsBySignIn, ProvidesPost, ProvidesReadings
{
    public function key(): string
    {
        return 'postbox';
    }

    public function label(): string
    {
        return 'Postbox';
    }

    public function description(): string
    {
        return 'Letters from the street.';
    }

    public function company(): string
    {
        return 'The Post Office';
    }

    public function signInDriver(): string
    {
        return 'postbox';
    }

    public function signInOptions(): array
    {
        return [new SignInOption('parcels', 'This account also takes parcels', 'Large boxes only.', 'Parcels')];
    }

    public function scopes(array $options): array
    {
        return in_array('parcels', $options, true) ? ['letters', 'parcels'] : ['letters'];
    }

    public function signInParameters(): array
    {
        return ['prompt' => 'select_account'];
    }

    public function tokenUrl(): string
    {
        return 'https://postbox.test/token';
    }

    public function letters(): int
    {
        return 3;
    }

    public function latestReading(): float
    {
        return 9.0;
    }
}
