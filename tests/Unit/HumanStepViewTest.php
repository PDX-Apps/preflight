<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Render\HumanStepView;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(HumanStepView::class)]
final class HumanStepViewTest extends TestCase
{
    private function step(StepResult $step): string
    {
        $output = new BufferedOutput();
        (new HumanStepView())->step($step, $output);

        return $output->fetch();
    }

    public function test_a_passing_step_shows_its_label_and_timing(): void
    {
        $text = $this->step(StepResult::passed('pint', 'Pint', durationSeconds: 0.25));

        $this->assertStringContainsString('PASS', $text);
        $this->assertStringContainsString('Pint', $text);
        $this->assertStringContainsString('250ms', $text);
    }

    public function test_a_failing_step_lists_its_findings(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'Undefined variable $x', 'app/Foo.php', 12, 5, 'variable.undefined');
        $text = $this->step(StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 2.0, exitCode: 1));

        $this->assertStringContainsString('FAIL', $text);
        $this->assertStringContainsString('app/Foo.php:12:5', $text);
        $this->assertStringContainsString('Undefined variable $x', $text);
        $this->assertStringContainsString('variable.undefined', $text);
        $this->assertStringContainsString('2.00s', $text);
    }

    public function test_a_skipped_step_shows_its_reason_and_no_timing(): void
    {
        $text = $this->step(StepResult::skipped('psalm', 'Psalm', 'scoped out of this run'));

        $this->assertStringContainsString('SKIP', $text);
        $this->assertStringContainsString('scoped out of this run', $text);
    }

    public function test_a_missing_tool_step_shows_its_reason(): void
    {
        $text = $this->step(StepResult::missingTool('psalm', 'Psalm', 'install vimeo/psalm'));

        $this->assertStringContainsString('MISS', $text);
        $this->assertStringContainsString('install vimeo/psalm', $text);
    }

    public function test_a_passing_step_lists_fixed_files_and_metrics(): void
    {
        $text = $this->step(StepResult::passed(
            'pint',
            'Pint',
            durationSeconds: 0.5,
            changed: ['app/A.php'],
            metrics: ['line coverage 98.00%'],
        ));

        $this->assertStringContainsString('fixed', $text);
        $this->assertStringContainsString('app/A.php', $text);
        $this->assertStringContainsString('line coverage 98.00%', $text);
    }

    public function test_summary_reports_counts_and_a_passing_verdict(): void
    {
        $output = new BufferedOutput();
        (new HumanStepView())->summary(new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 1.0),
            StepResult::skipped('psalm', 'Psalm', 'scoped out'),
        ]), $output);

        $text = $output->fetch();
        $this->assertStringContainsString('1 passed', $text);
        $this->assertStringContainsString('0 failed', $text);
        $this->assertStringContainsString('1 skipped', $text);
        $this->assertStringContainsString('All checks passed', $text);
    }

    public function test_summary_reports_a_failing_verdict(): void
    {
        $output = new BufferedOutput();
        (new HumanStepView())->summary(new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [], durationSeconds: 1.0, exitCode: 1),
        ]), $output);

        $this->assertStringContainsString('Checks failed', $output->fetch());
    }
}
