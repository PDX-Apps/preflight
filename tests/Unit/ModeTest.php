<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mode::class)]
final class ModeTest extends TestCase
{
    public function test_it_is_backed_by_a_stable_string_value(): void
    {
        $this->assertSame('check', Mode::Check->value);
        $this->assertSame('fix', Mode::Fix->value);
    }

    public function test_is_fix_distinguishes_the_two_modes(): void
    {
        $this->assertTrue(Mode::Fix->isFix());
        $this->assertFalse(Mode::Check->isFix());
    }

    public function test_it_can_be_built_from_a_string_value(): void
    {
        $this->assertSame(Mode::Fix, Mode::from('fix'));
    }
}
