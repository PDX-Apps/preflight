<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Contracts;

use DateTimeImmutable;

/**
 * Supplies the current time. Injected (like {@see ProcessExecutor}) so report timestamps
 * are real in production and fixed in tests.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
