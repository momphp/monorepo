<?php

declare(strict_types=1);

namespace Mom\Connectors\Tests;

use Mom\Connectors\ConnectorsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ConnectorsServiceProvider::class];
    }
}
