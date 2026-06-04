<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

use DateTimeImmutable;
use PdxApps\Preflight\Contracts\Clock;

/**
 * A {@see Clock} fixed at one instant — for deterministic timestamps in tests.
 */
final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $instant)
    {
    }

    /**
     * @SuppressWarnings("PHPMD.ShortMethodName") "at" reads naturally for a fixed clock.
     */
    public static function at(string $time): self
    {
        return new self(new DateTimeImmutable($time));
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}
