<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Render\GithubRenderer;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(GithubRenderer::class)]
final class GithubRendererTest extends TestCase
{
    private function render(RunResult $result): string
    {
        $output = new BufferedOutput();
        new GithubRenderer()->render($result, $output);

        return $output->fetch();
    }

    public function test_a_passing_run_emits_no_annotations(): void
    {
        $result = new RunResult([StepResult::passed('pint', 'Pint', durationSeconds: 0.5)]);

        $this->assertSame('', trim($this->render($result)));
    }

    public function test_an_error_becomes_an_error_workflow_command_with_file_line_col(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'Undefined variable $x', 'app/Foo.php', 12, 5, 'variable.undefined');
        $result = new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]);

        $line = trim($this->render($result));
        $this->assertStringStartsWith('::error ', $line);
        $this->assertStringContainsString('file=app/Foo.php', $line);
        $this->assertStringContainsString('line=12', $line);
        $this->assertStringContainsString('col=5', $line);
        $this->assertStringContainsString('Undefined variable $x', $line);
        $this->assertStringContainsString('[phpstan]', $line);
    }

    public function test_a_warning_becomes_a_warning_workflow_command(): void
    {
        $finding = new Finding('pint', Severity::Warning, 'Style issue', 'app/Bar.php', fixable: true);
        $result = new RunResult([
            StepResult::failed('pint', 'Pint', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]);

        $this->assertStringStartsWith('::warning ', trim($this->render($result)));
    }

    public function test_messages_are_escaped_per_the_workflow_command_spec(): void
    {
        // Newlines and the % / : / , delimiters must be percent-encoded.
        $finding = new Finding('phpstan', Severity::Error, "line one\nline two, 50% off", 'a.php', 1);
        $result = new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]);

        $line = trim($this->render($result));
        $this->assertStringContainsString('%0A', $line, 'newline encoded');
        $this->assertStringContainsString('%25', $line, 'percent encoded');
        $this->assertStringNotContainsString("\n", substr($line, 2), 'no raw newline inside the command');
    }

    public function test_a_finding_without_a_file_still_emits_an_annotation(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'general error', null, null);
        $result = new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]);

        $line = trim($this->render($result));
        // No file/line -> no properties, so the command has no space before `::`.
        $this->assertStringStartsWith('::error::', $line);
        $this->assertStringContainsString('general error', $line);
    }
}
