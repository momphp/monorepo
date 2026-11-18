<?php

declare(strict_types=1);

namespace Mom\Connectors;

use Illuminate\Support\ServiceProvider;

class ConnectorsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/connectors.php', 'connectors');

        $this->app->singleton(ConnectorRegistry::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/connectors.php' => config_path('connectors.php'),
        ], 'connectors-config');
    }
}
