<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Scope\StepSelection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StepSelection::class)]
final class StepSelectionTest extends TestCase
{
    public function test_it_is_empty_when_no_flags_are_given(): void
    {
        $selection = StepSelection::fromCli(null, null);

        $this->assertTrue($selection->isEmpty());
        $this->assertSame([], $selection->only);
        $this->assertSame([], $selection->skip);
    }

    public function test_it_parses_a_comma_separated_only_list_trimming_blanks(): void
    {
        $selection = StepSelection::fromCli('phpstan, test , ', null);

        $this->assertFalse($selection->isEmpty());
        $this->assertSame(['phpstan', 'test'], $selection->only);
        $this->assertSame([], $selection->skip);
    }

    public function test_it_parses_a_skip_list(): void
    {
        $selection = StepSelection::fromCli(null, 'phpmd,psalm');

        $this->assertSame([], $selection->only);
        $this->assertSame(['phpmd', 'psalm'], $selection->skip);
    }

    public function test_only_and_skip_together_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Use either --only or --skip, not both.');

        StepSelection::fromCli('phpstan', 'phpmd');
    }
}
