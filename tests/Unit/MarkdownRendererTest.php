<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Render\MarkdownRenderer;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(MarkdownRenderer::class)]
final class MarkdownRendererTest extends TestCase
{
    private function render(RunResult $result): string
    {
        $output = new BufferedOutput();
        (new MarkdownRenderer())->render($result, $output);

        return $output->fetch();
    }

    public function test_a_passing_run_shows_a_pass_header_and_a_table_but_no_findings_section(): void
    {
        $md = $this->render(new RunResult([StepResult::passed('pint', 'Pint', durationSeconds: 0.5)]));

        $this->assertStringContainsString('## Preflight — ✓ all checks passed', $md);
        $this->assertStringContainsString('| Step | Status | Findings | Time |', $md);
        $this->assertStringContainsString('| Pint | ✅ | 0 | 0.50s |', $md);
        $this->assertStringNotContainsString('### Findings', $md);
        $this->assertStringNotContainsString('### Coverage', $md);
    }

    public function test_it_renders_a_coverage_section_from_step_metrics(): void
    {
        $md = $this->render(new RunResult([
            StepResult::passed('test', 'Tests', durationSeconds: 2.3, metrics: ['patch coverage 100.00% (5/5 changed lines)']),
        ]));

        $this->assertStringContainsString('### Coverage', $md);
        $this->assertStringContainsString('- **Tests** — patch coverage 100.00% (5/5 changed lines)', $md);
    }

    public function test_a_failing_run_shows_a_fail_header_and_a_findings_section(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'Undefined variable $x', 'app/Foo.php', 12, 5, 'variable.undefined');
        $md = $this->render(new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.4, exitCode: 1),
        ]));

        $this->assertStringContainsString('## Preflight — ✗ checks failed', $md);
        $this->assertStringContainsString('| PHPStan | ❌ | 1 | 1.40s |', $md);
        $this->assertStringContainsString('### Findings', $md);
        $this->assertStringContainsString('`app/Foo.php:12` — **[phpstan]** Undefined variable $x (`variable.undefined`)', $md);
    }

    public function test_skipped_and_missing_tool_steps_show_dashes(): void
    {
        $md = $this->render(new RunResult([
            StepResult::skipped('composer-audit', 'Composer Audit', 'narrowed run'),
            StepResult::missingTool('phpstan', 'PHPStan', 'not installed'),
        ]));

        $this->assertStringContainsString('| Composer Audit | ⏭️ skipped | – | – |', $md);
        $this->assertStringContainsString('| PHPStan | ⚠️ not installed | – | – |', $md);
    }

    public function test_a_finding_without_a_file_omits_the_location(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'General failure');
        $md = $this->render(new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]));

        $this->assertStringContainsString('- **[phpstan]** General failure', $md);
    }
}
