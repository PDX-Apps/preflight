<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

use DateTimeImmutable;
use PdxApps\Preflight\Contracts\Clock;

/**
 * The production {@see Clock}: the real wall-clock time.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
