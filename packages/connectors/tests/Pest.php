<?php

declare(strict_types=1);

use Mom\Connectors\Tests\TestCase;

// Used when running this package's tests on their own (`composer test` in packages/connectors).
// The monorepo binds its own test case in tests/Pest.php at the repository root.
uses(TestCase::class)->in(__DIR__);
