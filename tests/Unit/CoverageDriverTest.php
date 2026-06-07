<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\CoverageDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoverageDriver::class)]
final class CoverageDriverTest extends TestCase
{
    public function test_only_xdebug_needs_the_coverage_mode_env(): void
    {
        $this->assertSame(['XDEBUG_MODE' => 'coverage'], CoverageDriver::Xdebug->env());
        $this->assertSame([], CoverageDriver::Pcov->env());
        $this->assertSame([], CoverageDriver::Phpdbg->env());
    }

    public function test_each_driver_has_a_human_label(): void
    {
        $this->assertSame('PCOV', CoverageDriver::Pcov->label());
        $this->assertSame('phpdbg', CoverageDriver::Phpdbg->label());
        $this->assertSame('Xdebug', CoverageDriver::Xdebug->label());
    }

    public function test_detect_returns_a_driver_or_null_for_this_environment(): void
    {
        // Environment-dependent, so assert only the contract: a valid driver, or null.
        $driver = CoverageDriver::detect();

        $this->assertTrue($driver === null || $driver instanceof CoverageDriver);
    }
}
