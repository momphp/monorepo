<?php

declare(strict_types=1);

namespace Mom\Connectors\Tests\Fixtures;

/**
 * An app's own contract for a capability: connected accounts of a type that
 * implements it can be used for weather readings.
 */
interface ProvidesReadings
{
    public function latestReading(): float;
}
