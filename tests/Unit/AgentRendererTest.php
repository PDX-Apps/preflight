<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Render\AgentRenderer;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(AgentRenderer::class)]
final class AgentRendererTest extends TestCase
{
    private function render(RunResult $result, bool $verbose = false): string
    {
        $output = new BufferedOutput(
            verbosity: $verbose ? BufferedOutput::VERBOSITY_VERBOSE : BufferedOutput::VERBOSITY_NORMAL,
            decorated: true, // even if asked to decorate, output must be plain
        );
        (new AgentRenderer())->render($result, $output);

        return $output->fetch();
    }

    public function test_a_passing_run_says_so_concisely(): void
    {
        $result = new RunResult([StepResult::passed('pint', 'Pint', durationSeconds: 0.5)]);

        $text = $this->render($result);

        $this->assertStringContainsString('PASS', strtoupper($text));
        $this->assertStringNotContainsString('Pint', $text, 'a clean run need not enumerate steps');
    }

    public function test_verbose_mode_additionally_lists_each_step_status(): void
    {
        $result = new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 0.5),
            StepResult::skipped('test', 'Tests', reason: 'no tool'),
        ]);

        $text = $this->render($result, verbose: true);

        $this->assertStringContainsString('pint', $text);
        $this->assertStringContainsString('test', $text);
        $this->assertStringNotContainsString("\033", $text, 'still no ansi in verbose mode');
    }

    public function test_it_lists_each_finding_as_one_grep_friendly_line(): void
    {
        $a = new Finding('phpstan', Severity::Error, 'Undefined variable $x', 'app/Foo.php', 12, 5, 'variable.undefined');
        $b = new Finding('pint', Severity::Warning, 'Code style issues found.', 'app/Bar.php', fixable: true);
        $result = new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$a], durationSeconds: 1.0, exitCode: 1),
            StepResult::failed('pint', 'Pint', findings: [$b], durationSeconds: 1.0, exitCode: 1),
        ]);

        $text = $this->render($result);
        $lines = array_values(array_filter(explode("\n", $text)));

        // A FAIL anchor line, then findings most-severe-first (error before warning).
        $this->assertStringContainsString('FAIL', $lines[0]);
        $this->assertStringContainsString('app/Foo.php:12:5', $lines[1]);
        $this->assertStringContainsString('Undefined variable $x', $lines[1]);
        $this->assertStringContainsString('app/Bar.php', $text);
    }

    public function test_output_contains_no_ansi_escape_codes(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'boom', 'app/Foo.php', 1);
        $result = new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]);

        $this->assertStringNotContainsString("\033", $this->render($result));
    }

    public function test_it_reports_fixed_files(): void
    {
        $result = new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 1.0, changed: ['app/A.php']),
        ]);

        $text = $this->render($result);

        $this->assertStringContainsString('app/A.php', $text);
        $this->assertMatchesRegularExpression('/fixed/i', $text);
    }
}
