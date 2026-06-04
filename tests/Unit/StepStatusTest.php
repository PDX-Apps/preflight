<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Result\StepStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StepStatus::class)]
final class StepStatusTest extends TestCase
{
    public function test_it_is_backed_by_a_stable_string_value(): void
    {
        $this->assertSame('passed', StepStatus::Passed->value);
        $this->assertSame('failed', StepStatus::Failed->value);
        $this->assertSame('skipped', StepStatus::Skipped->value);
        $this->assertSame('missing-tool', StepStatus::MissingTool->value);
    }

    public function test_only_failed_counts_as_a_failure(): void
    {
        $this->assertTrue(StepStatus::Failed->isFailure());
        $this->assertFalse(StepStatus::Passed->isFailure());
        $this->assertFalse(StepStatus::Skipped->isFailure());
        $this->assertFalse(StepStatus::MissingTool->isFailure());
    }

    public function test_did_run_is_true_only_when_the_tool_actually_executed(): void
    {
        $this->assertTrue(StepStatus::Passed->didRun());
        $this->assertTrue(StepStatus::Failed->didRun());
        $this->assertFalse(StepStatus::Skipped->didRun());
        $this->assertFalse(StepStatus::MissingTool->didRun());
    }
}
