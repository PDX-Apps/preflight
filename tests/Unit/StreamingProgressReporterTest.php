<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Console\StreamingProgressReporter;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Tests\Support\FakeStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(StreamingProgressReporter::class)]
final class StreamingProgressReporterTest extends TestCase
{
    private function step(): FakeStep
    {
        return new FakeStep('pint', StepPlan::exitCode('pint', ['true']), label: 'Pint');
    }

    public function test_on_a_plain_stream_it_prints_only_each_finished_step(): void
    {
        $output = new BufferedOutput(); // not decorated
        $reporter = new StreamingProgressReporter($output);

        $reporter->stepStarted($this->step());
        $reporter->stepFinished(StepResult::passed('pint', 'Pint', durationSeconds: 0.25));

        $text = $output->fetch();
        // No transient "running" line and no terminal control codes on a pipe.
        $this->assertStringNotContainsString('running', $text);
        $this->assertStringNotContainsString("\033", $text);
        $this->assertStringContainsString('PASS', $text);
        $this->assertStringContainsString('Pint', $text);
    }

    public function test_on_a_terminal_it_shows_a_transient_running_line_then_erases_it(): void
    {
        $output = new BufferedOutput();
        $output->setDecorated(true);
        $reporter = new StreamingProgressReporter($output);

        $reporter->stepStarted($this->step());
        $reporter->stepFinished(StepResult::passed('pint', 'Pint', durationSeconds: 0.25));

        $text = $output->fetch();
        $this->assertStringContainsString('running Pint', $text);
        $this->assertStringContainsString("\033[2K", $text, 'the transient line is erased before the result');
        $this->assertStringContainsString('PASS', $text);
    }
}
