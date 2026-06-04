<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunResult::class)]
final class RunResultTest extends TestCase
{
    public function test_a_run_with_no_failed_steps_is_successful(): void
    {
        $result = new RunResult([
            StepResult::passed('format', 'Pint', durationSeconds: 0.5),
            StepResult::skipped('test', 'Tests', reason: 'skipped'),
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
    }

    public function test_a_run_with_any_failed_step_is_a_failure(): void
    {
        $result = new RunResult([
            StepResult::passed('format', 'Pint', durationSeconds: 0.5),
            StepResult::failed('analyse', 'PHPStan', findings: [], durationSeconds: 1.0, exitCode: 1),
        ]);

        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isSuccess());
    }

    public function test_it_collects_findings_across_steps_sorted_most_severe_first(): void
    {
        $info = new Finding('phpmd', Severity::Info, 'info');
        $error = new Finding('phpstan', Severity::Error, 'error');
        $warning = new Finding('pint', Severity::Warning, 'warning');

        $result = new RunResult([
            StepResult::failed('phpmd', 'PHPMD', findings: [$info], durationSeconds: 0.1, exitCode: 1),
            StepResult::failed('analyse', 'PHPStan', findings: [$error, $warning], durationSeconds: 0.2, exitCode: 1),
        ]);

        $this->assertSame([$error, $warning, $info], $result->findings());
    }

    public function test_it_partitions_steps_by_outcome(): void
    {
        $passed = StepResult::passed('format', 'Pint', durationSeconds: 0.5);
        $failed = StepResult::failed('analyse', 'PHPStan', findings: [], durationSeconds: 1.0, exitCode: 1);
        $skipped = StepResult::skipped('test', 'Tests', reason: 'skipped');

        $result = new RunResult([$passed, $failed, $skipped]);

        $this->assertSame([$failed], $result->failed());
        $this->assertSame([$passed], $result->passed());
        $this->assertSame([$skipped], $result->skipped());
    }

    public function test_total_duration_is_the_sum_of_step_durations(): void
    {
        $result = new RunResult([
            StepResult::passed('a', 'A', durationSeconds: 0.5),
            StepResult::passed('b', 'B', durationSeconds: 1.25),
        ]);

        $this->assertSame(1.75, $result->totalDurationSeconds());
    }

    public function test_it_serializes_to_a_stable_array_shape(): void
    {
        $step = StepResult::passed('format', 'Pint', durationSeconds: 0.5);
        $result = new RunResult([$step]);

        $this->assertSame([
            'success' => true,
            'steps' => [$step->toArray()],
            'findings' => [],
        ], $result->toArray());
    }
}
