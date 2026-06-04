<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\FrozenClock;
use PdxApps\Preflight\Support\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
#[CoversClass(FrozenClock::class)]
final class ClockTest extends TestCase
{
    public function test_the_system_clock_returns_an_immutable_now(): void
    {
        $before = new \DateTimeImmutable();
        $now = new SystemClock()->now();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    public function test_a_frozen_clock_always_returns_the_same_instant(): void
    {
        $clock = FrozenClock::at('2026-01-02T03:04:05+00:00');

        $this->assertSame('2026-01-02T03:04:05+00:00', $clock->now()->format('c'));
        $this->assertSame('2026-01-02T03:04:05+00:00', $clock->now()->format('c'), 'stable across calls');
    }
}
