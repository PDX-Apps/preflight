<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\FindingExcluder;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Result\StepStatus;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FindingExcluder::class)]
final class FindingExcluderTest extends TestCase
{
    private function error(string $file): Finding
    {
        return new Finding('phpstan', Severity::Error, 'boom', $file, 1);
    }

    /**
     * @param list<Finding> $findings
     */
    private function failed(array $findings): StepResult
    {
        return StepResult::failed('phpstan', 'PHPStan', findings: $findings, durationSeconds: 1.0, exitCode: 1);
    }

    private function only(RunResult $result): StepResult
    {
        return $result->steps[0];
    }

    public function test_no_patterns_returns_the_result_unchanged(): void
    {
        $result = new RunResult([$this->failed([$this->error('app/Providers/AppServiceProvider.php')])]);

        $this->assertSame($result, (new FindingExcluder([]))->apply($result));
    }

    public function test_it_drops_findings_under_a_directory_prefix(): void
    {
        $result = new RunResult([$this->failed([
            $this->error('app/Providers/AppServiceProvider.php'),
            $this->error('app/Http/Kernel.php'),
        ])]);

        $filtered = $this->only((new FindingExcluder(['app/Providers']))->apply($result));

        $this->assertCount(1, $filtered->findings);
        $this->assertSame('app/Http/Kernel.php', $filtered->findings[0]->file);
    }

    public function test_it_matches_a_glob_pattern(): void
    {
        $result = new RunResult([$this->failed([$this->error('app/Actions/Fortify/CreateNewUser.php')])]);

        $filtered = $this->only((new FindingExcluder(['app/Actions/Fortify/*']))->apply($result));

        $this->assertSame([], $filtered->findings);
    }

    public function test_a_step_whose_only_errors_were_excluded_becomes_passing(): void
    {
        $result = new RunResult([$this->failed([
            $this->error('app/Providers/AppServiceProvider.php'),
            $this->error('database/seeders/DatabaseSeeder.php'),
        ])]);

        $filtered = $this->only((new FindingExcluder(['app/Providers', 'database']))->apply($result));

        $this->assertSame(StepStatus::Passed, $filtered->status, 'all errors came from excluded paths');
        $this->assertTrue($filtered->isSuccess());
    }

    public function test_a_step_keeps_failing_when_an_error_remains(): void
    {
        $result = new RunResult([$this->failed([
            $this->error('app/Providers/AppServiceProvider.php'),
            $this->error('app/Http/Kernel.php'),
        ])]);

        $filtered = $this->only((new FindingExcluder(['app/Providers']))->apply($result));

        $this->assertSame(StepStatus::Failed, $filtered->status);
    }

    public function test_a_failure_with_no_findings_is_left_untouched(): void
    {
        // A crash (e.g. tool error) has no findings to filter — it must stay failed.
        $crash = $this->failed([]);
        $result = new RunResult([$crash]);

        $this->assertSame($crash, $this->only((new FindingExcluder(['app/Providers']))->apply($result)));
    }

    public function test_findings_without_a_file_are_kept(): void
    {
        $global = new Finding('test', Severity::Error, 'suite failed');
        $result = new RunResult([$this->failed([$global, $this->error('app/Providers/X.php')])]);

        $filtered = $this->only((new FindingExcluder(['app/Providers']))->apply($result));

        $this->assertSame([$global], $filtered->findings);
        $this->assertSame(StepStatus::Failed, $filtered->status, 'a fileless error still fails the step');
    }

    public function test_a_passing_step_drops_excluded_warnings_but_stays_passing(): void
    {
        $warning = new Finding('pint', Severity::Warning, 'style', 'app/Providers/X.php', 3);
        $passed = StepResult::passed('pint', 'Pint', durationSeconds: 0.5, findings: [$warning]);

        $filtered = $this->only((new FindingExcluder(['app/Providers']))->apply(new RunResult([$passed])));

        $this->assertSame([], $filtered->findings);
        $this->assertSame(StepStatus::Passed, $filtered->status);
    }

    public function test_skipped_and_missing_tool_steps_are_untouched(): void
    {
        $skipped = StepResult::skipped('a', 'A', 'scoped out');
        $missing = StepResult::missingTool('psalm', 'Psalm', 'install it');
        $result = new RunResult([$skipped, $missing]);

        $applied = (new FindingExcluder(['app']))->apply($result);

        $this->assertSame($skipped, $applied->steps[0]);
        $this->assertSame($missing, $applied->steps[1]);
    }
}
