<?php

declare(strict_types=1);

use Mom\Connectors\ConnectedAccount;
use Mom\Connectors\CouldNotConnect;
use Mom\Connectors\Tests\Fixtures\Postbox\PostboxConnector;
use Mom\Connectors\Tests\Fixtures\ProvidesPost;
use Mom\Connectors\Tests\Fixtures\ProvidesReadings;
use Mom\Connectors\Tests\Fixtures\WeatherStation\WeatherStationConnector;

it('says a sign-in type is available only when both of its keys are set', function (?string $id, ?string $secret, bool $available): void {
    config(['services.postbox.client_id' => $id, 'services.postbox.client_secret' => $secret]);

    expect((new PostboxConnector)->isAvailable())->toBe($available);
})->with([
    'both keys' => ['id', 'secret', true],
    'no secret' => ['id', null, false],
    'no id' => ['', 'secret', false],
    'neither' => [null, null, false],
]);

it('says a form type is always available', function (): void {
    expect((new WeatherStationConnector)->isAvailable())->toBeTrue();
});

it('hands over the logo beside the class as an image, and nothing when there is none', function (): void {
    $logo = (new WeatherStationConnector)->logo();

    expect($logo)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(mb_substr((string) $logo, 26), true))->toContain('<svg')
        ->and((new PostboxConnector)->logo())->toBeNull();
});

it('names the company that runs the service, which is the label unless the type says otherwise', function (): void {
    expect((new PostboxConnector)->company())->toBe('The Post Office')
        ->and((new WeatherStationConnector)->company())->toBe('Weather station');
});

it('provides what its contracts say', function (): void {
    expect((new WeatherStationConnector)->provides(ProvidesReadings::class))->toBeTrue()
        ->and((new WeatherStationConnector)->provides(ProvidesPost::class))->toBeFalse();
});

it('asks a sign-in for more when an option is chosen', function (): void {
    expect((new PostboxConnector)->scopes([]))->toBe(['letters'])
        ->and((new PostboxConnector)->scopes(['parcels']))->toBe(['letters', 'parcels']);
});

it('says what to save when a form connects, and which field to look at when it does not', function (): void {
    $type = new WeatherStationConnector;

    $account = $type->connect(['station' => 'roof-1', 'api_key' => 'right-key']);

    expect($account)->toBeInstanceOf(ConnectedAccount::class)
        ->and($account->account)->toBe('roof-1')
        ->and($account->name)->toBe('Roof')
        ->and($account->settings)->toBe(['station' => 'roof-1'])
        ->and($account->credentials)->toBe(['api_key' => 'right-key']);

    try {
        $type->connect(['station' => 'roof-1', 'api_key' => 'wrong-key']);
        $this->fail('The wrong key was accepted.');
    } catch (CouldNotConnect $exception) {
        expect($exception->field)->toBe('api_key')
            ->and($exception->getMessage())->toBe('The station did not accept that key.');
    }
});
