<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Targeting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Targeting::class)]
final class TargetingTest extends TestCase
{
    public function test_it_is_backed_by_a_stable_string_value(): void
    {
        $this->assertSame('files', Targeting::Files->value);
        $this->assertSame('paths', Targeting::Paths->value);
        $this->assertSame('whole', Targeting::Whole->value);
    }

    public function test_accepts_files_only_for_file_level_targeting(): void
    {
        $this->assertTrue(Targeting::Files->acceptsFiles());
        $this->assertFalse(Targeting::Paths->acceptsFiles());
        $this->assertFalse(Targeting::Whole->acceptsFiles());
    }

    public function test_can_scope_when_not_whole(): void
    {
        $this->assertTrue(Targeting::Files->canScope());
        $this->assertTrue(Targeting::Paths->canScope());
        $this->assertFalse(Targeting::Whole->canScope());
    }
}
