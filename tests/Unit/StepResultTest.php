<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Result\StepStatus;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StepResult::class)]
final class StepResultTest extends TestCase
{
    public function test_passed_factory_marks_the_step_successful(): void
    {
        $result = StepResult::passed('phpstan', 'PHPStan Analysis', durationSeconds: 1.5, output: 'ok');

        $this->assertSame('phpstan', $result->name);
        $this->assertSame('PHPStan Analysis', $result->label);
        $this->assertSame(StepStatus::Passed, $result->status);
        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame(1.5, $result->durationSeconds);
        $this->assertSame(0, $result->exitCode);
        $this->assertSame([], $result->findings);
    }

    public function test_failed_factory_carries_findings_and_exit_code(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'boom', 'app/A.php', 1);

        $result = StepResult::failed(
            'phpstan',
            'PHPStan Analysis',
            findings: [$finding],
            durationSeconds: 2.0,
            exitCode: 1,
            output: 'raw',
        );

        $this->assertSame(StepStatus::Failed, $result->status);
        $this->assertTrue($result->isFailure());
        $this->assertSame([$finding], $result->findings);
        $this->assertSame(1, $result->exitCode);
        $this->assertNull($result->skipReason);
    }

    public function test_skipped_factory_records_a_reason_and_is_not_a_failure(): void
    {
        $result = StepResult::skipped('test', 'Tests', reason: 'excluded via --skip');

        $this->assertSame(StepStatus::Skipped, $result->status);
        $this->assertFalse($result->isFailure());
        $this->assertSame('excluded via --skip', $result->skipReason);
        $this->assertNull($result->exitCode);
    }

    public function test_missing_tool_factory_records_a_reason_and_is_not_a_failure(): void
    {
        $result = StepResult::missingTool('psalm', 'Psalm', reason: 'install vimeo/psalm');

        $this->assertSame(StepStatus::MissingTool, $result->status);
        $this->assertFalse($result->isFailure());
        $this->assertSame('install vimeo/psalm', $result->skipReason);
    }

    public function test_it_serializes_to_a_stable_array_shape(): void
    {
        $finding = new Finding('pint', Severity::Warning, 'style', 'app/A.php', 2, fixable: true);
        $result = StepResult::failed('format', 'Pint', findings: [$finding], durationSeconds: 0.5, exitCode: 1, output: 'x');

        $this->assertSame([
            'name' => 'format',
            'label' => 'Pint',
            'status' => 'failed',
            'durationSeconds' => 0.5,
            'exitCode' => 1,
            'skipReason' => null,
            'findings' => [$finding->toArray()],
            'changed' => [],
            'metrics' => [],
        ], $result->toArray());
    }

    public function test_it_carries_metrics_for_a_passing_step(): void
    {
        $result = StepResult::passed('test', 'Tests', durationSeconds: 1.0, metrics: ['patch coverage 100.00% (5/5 changed lines)']);

        $this->assertSame(['patch coverage 100.00% (5/5 changed lines)'], $result->metrics);
        $this->assertSame(['patch coverage 100.00% (5/5 changed lines)'], $result->toArray()['metrics']);
    }
}
