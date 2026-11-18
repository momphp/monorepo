<?php

declare(strict_types=1);

namespace Mom\Connectors\Tests\Fixtures;

/**
 * A second capability contract, which only the postbox implements.
 */
interface ProvidesPost
{
    public function letters(): int;
}
