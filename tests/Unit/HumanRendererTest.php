<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Render\HumanRenderer;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(HumanRenderer::class)]
final class HumanRendererTest extends TestCase
{
    private function render(RunResult $result): string
    {
        $output = new BufferedOutput();
        (new HumanRenderer())->render($result, $output);

        return $output->fetch();
    }

    public function test_a_passing_run_lists_each_step_and_an_all_passed_summary(): void
    {
        $result = new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 1.2),
            StepResult::passed('phpstan', 'PHPStan', durationSeconds: 3.0),
        ]);

        $text = $this->render($result);

        $this->assertStringContainsString('Pint', $text);
        $this->assertStringContainsString('PHPStan', $text);
        $this->assertStringContainsString('passed', $text);
    }

    public function test_it_shows_a_steps_metrics(): void
    {
        $result = new RunResult([
            StepResult::passed('test', 'Tests', durationSeconds: 1.0, metrics: ['patch coverage 100.00% (5/5 changed lines)']),
        ]);

        $this->assertStringContainsString('patch coverage 100.00% (5/5 changed lines)', $this->render($result));
    }

    public function test_a_failing_step_lists_its_findings_with_location_and_message(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'Undefined variable $x', 'app/Foo.php', 12, 5, 'variable.undefined');
        $result = new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 2.0, exitCode: 1),
        ]);

        $text = $this->render($result);

        $this->assertStringContainsString('app/Foo.php', $text);
        $this->assertStringContainsString('12', $text);
        $this->assertStringContainsString('Undefined variable $x', $text);
        $this->assertStringContainsString('variable.undefined', $text);
        $this->assertStringContainsString('FAIL', strtoupper($text));
    }

    public function test_a_step_that_fixed_files_lists_them_as_changed(): void
    {
        $result = new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 1.0, changed: ['app/A.php', 'app/B.php']),
        ]);

        $text = $this->render($result);

        $this->assertStringContainsString('app/A.php', $text);
        $this->assertStringContainsString('app/B.php', $text);
        $this->assertMatchesRegularExpression('/fixed|changed/i', $text);
    }

    public function test_a_skipped_step_is_shown_with_its_reason(): void
    {
        $result = new RunResult([
            StepResult::skipped('test', 'Tests', reason: 'cannot scope to a file subset'),
        ]);

        $text = $this->render($result);

        $this->assertStringContainsString('Tests', $text);
        $this->assertStringContainsString('skip', strtolower($text));
        $this->assertStringContainsString('cannot scope to a file subset', $text);
    }

    public function test_a_missing_tool_step_is_shown_with_its_install_hint(): void
    {
        $result = new RunResult([
            StepResult::missingTool('psalm', 'Psalm', reason: 'Run: composer require --dev vimeo/psalm'),
        ]);

        $text = $this->render($result);

        $this->assertStringContainsString('Psalm', $text);
        $this->assertStringContainsString('composer require --dev vimeo/psalm', $text);
    }

    public function test_the_summary_counts_passed_failed_and_skipped(): void
    {
        $result = new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 1.0),
            StepResult::failed('phpstan', 'PHPStan', findings: [], durationSeconds: 1.0, exitCode: 1),
            StepResult::skipped('test', 'Tests', reason: 'skip'),
        ]);

        $text = $this->render($result);

        $this->assertMatchesRegularExpression('/1\s+passed/i', $text);
        $this->assertMatchesRegularExpression('/1\s+failed/i', $text);
        $this->assertMatchesRegularExpression('/1\s+skipped/i', $text);
    }
}
