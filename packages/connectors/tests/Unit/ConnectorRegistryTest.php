<?php

declare(strict_types=1);

use Mom\Connectors\Capability;
use Mom\Connectors\ConnectorRegistry;
use Mom\Connectors\ConnectorsServiceProvider;
use Mom\Connectors\Tests\Fixtures\Postbox\PostboxConnector;
use Mom\Connectors\Tests\Fixtures\ProvidesPost;
use Mom\Connectors\Tests\Fixtures\ProvidesReadings;
use Mom\Connectors\Tests\Fixtures\WeatherStation\WeatherStationConnector;
use Mom\Connectors\UnknownConnectorType;

beforeEach(fn () => $this->app->register(ConnectorsServiceProvider::class));

/**
 * A registry with both fixture types and both capabilities.
 */
function registryWithFixtures(): ConnectorRegistry
{
    return (new ConnectorRegistry)
        ->capability(new Capability('readings', 'Readings', ProvidesReadings::class), new Capability('post', 'Post', ProvidesPost::class))
        ->register(new WeatherStationConnector, new PostboxConnector);
}

it('is one registry for the whole app', function (): void {
    ConnectorRegistry::instance()->register(new PostboxConnector);

    expect(ConnectorRegistry::instance())->toBe(app(ConnectorRegistry::class))
        ->and(ConnectorRegistry::instance()->find('postbox'))->toBeInstanceOf(PostboxConnector::class);
});

it('keeps types in the order they were registered and finds them by key', function (): void {
    $registry = registryWithFixtures();

    expect(array_map(fn ($type): string => $type->key(), $registry->all()))->toBe(['weather_station', 'postbox'])
        ->and($registry->find('postbox'))->toBeInstanceOf(PostboxConnector::class)
        ->and($registry->find('carrier_pigeon'))->toBeNull()
        ->and($registry->find(null))->toBeNull();
});

it('replaces a type registered under the same key', function (): void {
    $registry = registryWithFixtures();
    $first = $registry->find('postbox');

    $registry->register(new PostboxConnector);

    expect($registry->all())->toHaveCount(2)
        ->and($registry->find('postbox'))->not->toBe($first);
});

it('stands in for a type that is not installed with one that can do nothing', function (): void {
    $registry = registryWithFixtures();
    $type = $registry->get('carrier_pigeon');

    expect($type)->toBeInstanceOf(UnknownConnectorType::class)
        ->and($type->key())->toBe('carrier_pigeon')
        ->and($type->label())->toBe('Carrier Pigeon')
        ->and($type->isAvailable())->toBeFalse()
        ->and($type->logo())->toBeNull()
        ->and($registry->capabilitiesOf($type))->toBe([])
        ->and($registry->describe($type)['connection'])->toBeNull()
        ->and($registry->get(null)->label())->toBe('Unknown');
});

it('works out what a type can be used for from the contracts it implements', function (): void {
    $registry = registryWithFixtures();
    $labels = fn (string $key): array => array_map(fn (Capability $capability): string => $capability->label, $registry->capabilitiesOf($registry->get($key)));

    expect($labels('weather_station'))->toBe(['Readings'])
        ->and($labels('postbox'))->toBe(['Readings', 'Post'])
        ->and(array_map(fn ($type): string => $type->key(), $registry->providing('post')))->toBe(['postbox'])
        ->and(array_map(fn ($type): string => $type->key(), $registry->providing('readings')))->toBe(['weather_station', 'postbox'])
        ->and($registry->providing('telepathy'))->toBe([]);
});

it('describes a form type for the page that connects it', function (): void {
    expect(registryWithFixtures()->describe(new WeatherStationConnector))->toMatchArray([
        'value' => 'weather_station',
        'label' => 'Weather station',
        'description' => 'Readings from the roof.',
        'capabilities' => ['Readings'],
        'available' => true,
        'connection' => 'form',
        'options' => [],
        'account_field' => 'station',
        'form_introduction' => 'The key is printed on the station.',
        'fields' => [
            ['name' => 'station', 'label' => 'Station', 'kind' => 'text', 'required' => true, 'placeholder' => null, 'hint' => null, 'default' => null, 'options' => [], 'group' => null, 'span' => 4],
            ['name' => 'api_key', 'label' => 'API key', 'kind' => 'password', 'required' => true, 'placeholder' => null, 'hint' => null, 'default' => null, 'options' => [], 'group' => 'Access', 'span' => 6],
        ],
    ]);
});

it('describes a sign-in type for the page that connects it', function (): void {
    config(['services.postbox.client_id' => 'id', 'services.postbox.client_secret' => 'secret']);

    expect(registryWithFixtures()->describe(new PostboxConnector))->toMatchArray([
        'value' => 'postbox',
        'logo' => null,
        'capabilities' => ['Readings', 'Post'],
        'available' => true,
        'connection' => 'sign_in',
        'options' => [['key' => 'parcels', 'label' => 'This account also takes parcels', 'hint' => 'Large boxes only.']],
        'fields' => [],
        'account_field' => null,
        'form_introduction' => null,
    ]);
});

it('lists every type for the page that offers them', function (): void {
    expect(array_column(registryWithFixtures()->catalog(), 'value'))->toBe(['weather_station', 'postbox']);
});
