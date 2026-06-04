<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Severity::class)]
final class SeverityTest extends TestCase
{
    public function test_it_is_backed_by_a_stable_string_value(): void
    {
        $this->assertSame('error', Severity::Error->value);
        $this->assertSame('warning', Severity::Warning->value);
        $this->assertSame('info', Severity::Info->value);
    }

    public function test_error_outranks_warning_which_outranks_info(): void
    {
        $this->assertGreaterThan(Severity::Warning->weight(), Severity::Error->weight());
        $this->assertGreaterThan(Severity::Info->weight(), Severity::Warning->weight());
    }

    public function test_is_failure_treats_only_errors_as_failures_by_default(): void
    {
        $this->assertTrue(Severity::Error->isFailure());
        $this->assertFalse(Severity::Warning->isFailure());
        $this->assertFalse(Severity::Info->isFailure());
    }
}
