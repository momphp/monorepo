<?php

declare(strict_types=1);

use Mom\Connectors\ConnectorsServiceProvider;
use Mom\Connectors\PublicHost;

beforeEach(fn () => $this->app->register(ConnectorsServiceProvider::class));

it('allows a server on the internet and refuses one only this machine can reach', function (string $host, bool $allowed): void {
    expect(PublicHost::allows($host))->toBe($allowed);
})->with([
    'a public address' => ['203.0.113.10', true],
    'a public IPv6 address' => ['2001:4860:4860::8888', true],
    'this machine' => ['127.0.0.1', false],
    'this machine, by name' => ['localhost', false],
    'this machine over IPv6' => ['::1', false],
    'a private network' => ['10.0.0.5', false],
    'another private network' => ['192.168.1.20', false],
    'a link-local address, such as a cloud metadata service' => ['169.254.169.254', false],
    'a private IPv6 address in brackets' => ['[fd00::1]', false],
    'a name that leads nowhere' => ['no-such-host.invalid', false],
    'nothing' => ['', false],
]);

it('allows private servers when the installation says so', function (): void {
    config(['connectors.allow_private_hosts' => true]);

    expect(PublicHost::allows('10.0.0.5'))->toBeTrue()
        ->and(PublicHost::allows('mail.internal'))->toBeTrue()
        ->and(PublicHost::allows(''))->toBeFalse();
});
