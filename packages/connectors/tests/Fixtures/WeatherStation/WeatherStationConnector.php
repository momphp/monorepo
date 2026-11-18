<?php

declare(strict_types=1);

namespace Mom\Connectors\Tests\Fixtures\WeatherStation;

use Mom\Connectors\ConnectedAccount;
use Mom\Connectors\ConnectorType;
use Mom\Connectors\ConnectsByForm;
use Mom\Connectors\CouldNotConnect;
use Mom\Connectors\Field;
use Mom\Connectors\Tests\Fixtures\ProvidesReadings;

/**
 * A type connected by a form, with a logo.svg beside it.
 */
final class WeatherStationConnector extends ConnectorType implements ConnectsByForm, ProvidesReadings
{
    public function key(): string
    {
        return 'weather_station';
    }

    public function label(): string
    {
        return 'Weather station';
    }

    public function description(): string
    {
        return 'Readings from the roof.';
    }

    public function formIntroduction(): string
    {
        return 'The key is printed on the station.';
    }

    public function fields(): array
    {
        return [
            Field::text('station', 'Station')->required()->span(4),
            Field::password('api_key', 'API key')->required()->group('Access'),
        ];
    }

    public function accountField(): string
    {
        return 'station';
    }

    public function connect(array $values): ConnectedAccount
    {
        if ('right-key' !== $values['api_key']) {
            throw CouldNotConnect::because('api_key', 'The station did not accept that key.');
        }

        return new ConnectedAccount((string) $values['station'], 'Roof', ['station' => $values['station']], ['api_key' => $values['api_key']]);
    }

    public function latestReading(): float
    {
        return 21.5;
    }
}
