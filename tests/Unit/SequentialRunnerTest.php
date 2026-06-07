<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\ExitCodeParser;
use PdxApps\Preflight\Parsing\JUnitParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Result\StepStatus;
use PdxApps\Preflight\Runner\SequentialRunner;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PdxApps\Preflight\Tests\Support\FakeStep;
use PdxApps\Preflight\Tests\Support\NoFindingsParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequentialRunner::class)]
final class SequentialRunnerTest extends TestCase
{
    private function context(?TargetSet $targets = null): Context
    {
        return new Context('/project', $targets ?? TargetSet::wholeProject());
    }

    public function test_it_runs_each_step_and_marks_a_zero_exit_passed(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess()->queueSuccess();
        $runner = new SequentialRunner($executor);

        $steps = [
            new FakeStep('a', StepPlan::command('a', ['a'])),
            new FakeStep('b', StepPlan::command('b', ['b'])),
        ];

        $result = $runner->run($steps, $this->context(), Mode::Check);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $result->steps);
        $this->assertSame(StepStatus::Passed, $result->steps[0]->status);
        $this->assertSame([['a'], ['b']], $executor->commands());
    }

    public function test_plan_notes_ride_along_on_a_passing_step_without_failing_it(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess();
        $runner = new SequentialRunner($executor);

        $note = new \PdxApps\Preflight\Finding('test', \PdxApps\Preflight\Severity::Warning, 'coverage skipped: no driver');
        $plan = StepPlan::command('test', ['phpunit'])->note($note);

        $result = $runner->run([new FakeStep('test', $plan)], $this->context(), Mode::Check);

        $this->assertTrue($result->isSuccess(), 'an advisory note must not fail the step');
        $this->assertSame(StepStatus::Passed, $result->steps[0]->status);
        $this->assertSame([$note], $result->steps[0]->findings);
    }

    public function test_parser_metrics_are_carried_onto_the_step_result(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess();
        $runner = new SequentialRunner($executor);

        $parser = new class () implements \PdxApps\Preflight\Contracts\OutputParser {
            public function parse(\PdxApps\Preflight\Process\ProcessResult $result): \PdxApps\Preflight\Parsing\ParseResult
            {
                return new \PdxApps\Preflight\Parsing\ParseResult([], [], ['patch coverage 100.00% (3/3 changed lines)']);
            }
        };
        $plan = StepPlan::command('test', ['phpunit'])->parseWith($parser);

        $result = $runner->run([new FakeStep('test', $plan)], $this->context(), Mode::Check);

        $this->assertSame(['patch coverage 100.00% (3/3 changed lines)'], $result->steps[0]->metrics);
    }

    public function test_a_nonzero_exit_marks_the_step_failed_and_carries_parsed_findings(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(1, 'boom');
        $runner = new SequentialRunner($executor);

        $plan = StepPlan::command('a', ['a'])->parseWith(new ExitCodeParser('a', 'it failed'));
        $result = $runner->run([new FakeStep('a', $plan)], $this->context(), Mode::Check);

        $this->assertTrue($result->isFailure());
        $step = $result->steps[0];
        $this->assertSame(StepStatus::Failed, $step->status);
        $this->assertSame(1, $step->exitCode);
        $this->assertCount(1, $step->findings);
        $this->assertSame('it failed', $step->findings[0]->message);
    }

    public function test_a_step_with_a_missing_vendor_tool_is_skipped_as_missing_tool(): void
    {
        $executor = new FakeProcessExecutor();
        $runner = new SequentialRunner($executor);

        $step = new FakeStep('psalm', StepPlan::command('psalm', ['psalm']), tool: Tool::vendorBin('psalm', 'vimeo/psalm'));
        $result = $runner->run([$step], $this->context(), Mode::Check);

        $this->assertSame(StepStatus::MissingTool, $result->steps[0]->status);
        $this->assertStringContainsString('vimeo/psalm', (string) $result->steps[0]->skipReason);
        $this->assertSame([], $executor->executed, 'a missing tool must not be executed');
        $this->assertTrue($result->isSuccess(), 'a missing tool does not fail the run');
    }

    public function test_a_whole_step_is_skipped_when_the_run_is_narrowed_to_a_file_subset(): void
    {
        $executor = new FakeProcessExecutor();
        $runner = new SequentialRunner($executor);

        $step = new FakeStep('test', StepPlan::command('test', ['paratest']), targeting: Targeting::Whole);
        $narrowed = TargetSet::narrowed([Target::file('app/Foo.php')]);

        $result = $runner->run([$step], $this->context($narrowed), Mode::Check);

        $this->assertSame(StepStatus::Skipped, $result->steps[0]->status);
        $this->assertSame([], $executor->executed);
    }

    public function test_before_commands_run_before_the_main_command(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess()->queueSuccess();
        $runner = new SequentialRunner($executor);

        $plan = StepPlan::command('test', ['paratest'])->before(['php', 'artisan', 'config:clear']);
        $runner->run([new FakeStep('test', $plan)], $this->context(), Mode::Check);

        $this->assertSame([['php', 'artisan', 'config:clear'], ['paratest']], $executor->commands());
    }

    public function test_a_failing_before_command_fails_the_step_without_running_the_main_command(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(1, '', 'cannot clear');
        $runner = new SequentialRunner($executor);

        $plan = StepPlan::command('test', ['paratest'])->before(['php', 'artisan', 'config:clear']);
        $result = $runner->run([new FakeStep('test', $plan)], $this->context(), Mode::Check);

        $this->assertSame(StepStatus::Failed, $result->steps[0]->status);
        $this->assertSame([['php', 'artisan', 'config:clear']], $executor->commands(), 'main command must not run');
    }

    public function test_fail_fast_skips_remaining_steps_after_the_first_failure(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(1);
        $runner = new SequentialRunner($executor, failFast: true);

        $steps = [
            new FakeStep('a', StepPlan::command('a', ['a'])),
            new FakeStep('b', StepPlan::command('b', ['b'])),
        ];

        $result = $runner->run($steps, $this->context(), Mode::Check);

        $this->assertSame(StepStatus::Failed, $result->steps[0]->status);
        $this->assertSame(StepStatus::Skipped, $result->steps[1]->status);
        $this->assertCount(1, $executor->executed, 'second step must not run under fail-fast');
    }

    public function test_without_fail_fast_all_steps_run_even_after_a_failure(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(1)->queueSuccess();
        $runner = new SequentialRunner($executor);

        $steps = [
            new FakeStep('a', StepPlan::command('a', ['a'])),
            new FakeStep('b', StepPlan::command('b', ['b'])),
        ];

        $result = $runner->run($steps, $this->context(), Mode::Check);

        $this->assertSame(StepStatus::Failed, $result->steps[0]->status);
        $this->assertSame(StepStatus::Passed, $result->steps[1]->status);
    }

    public function test_the_main_command_includes_resolved_path_args_for_the_steps_targeting(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess();
        $runner = new SequentialRunner($executor);

        // The step appends context path args to its base command.
        $plan = fn (Context $c, Mode $m): StepPlan => StepPlan::command('phpcs', ['phpcs', ...$c->pathsFor(Targeting::Files)]);
        $narrowed = TargetSet::narrowed([Target::file('app/Foo.php'), Target::file('app/Bar.php')]);

        $runner->run([new FakeStep('phpcs', $plan)], $this->context($narrowed), Mode::Check);

        $this->assertSame([['phpcs', 'app/Foo.php', 'app/Bar.php']], $executor->commands());
    }

    public function test_judge_by_findings_passes_a_nonzero_exit_when_the_parser_found_nothing(): void
    {
        // Some tools (e.g. PHPMD on recent PHP) exit non-zero even when clean, due to
        // deprecation noise. judgeByFindings() makes the parser's findings authoritative.
        // The parser here returns no findings for empty output (exit code notwithstanding).
        $executor = (new FakeProcessExecutor())->queueFailure(3, '');
        $runner = new SequentialRunner($executor);

        $plan = StepPlan::command('phpmd', ['phpmd'])
            ->parseWith(new NoFindingsParser())
            ->judgeByFindings();
        $result = $runner->run([new FakeStep('phpmd', $plan)], $this->context(), Mode::Check);

        $this->assertSame(StepStatus::Passed, $result->steps[0]->status, 'no findings = pass despite exit 3');
    }

    public function test_judge_by_findings_fails_when_the_parser_found_something(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(3, 'x');
        $runner = new SequentialRunner($executor);

        // A parser that always yields one finding.
        $plan = StepPlan::command('phpmd', ['phpmd'])
            ->parseWith(new ExitCodeParser('phpmd', 'mess found'))
            ->judgeByFindings();
        $result = $runner->run([new FakeStep('phpmd', $plan)], $this->context(), Mode::Check);

        $this->assertSame(StepStatus::Failed, $result->steps[0]->status);
        $this->assertCount(1, $result->steps[0]->findings);
    }

    public function test_judge_by_findings_off_keeps_exit_code_authoritative(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(1, '{"files":[]}');
        $runner = new SequentialRunner($executor);

        // Default: no judgeByFindings -> a non-zero exit fails even with no findings.
        $plan = StepPlan::command('phpmd', ['phpmd']);
        $result = $runner->run([new FakeStep('phpmd', $plan)], $this->context(), Mode::Check);

        $this->assertSame(StepStatus::Failed, $result->steps[0]->status);
    }

    public function test_report_file_placeholder_is_substituted_with_a_real_temp_path(): void
    {
        $executor = new FakeProcessExecutor();
        $runner = new SequentialRunner($executor);

        $plan = StepPlan::command('test', ['phpunit', '--log-junit=' . StepPlan::REPORT_FILE])->readingReportFile();
        $runner->run([new FakeStep('test', $plan)], $this->context(), Mode::Check);

        $arg = $executor->commands()[0][1];
        $this->assertStringStartsWith('--log-junit=', $arg);
        $this->assertStringNotContainsString(StepPlan::REPORT_FILE, $arg, 'placeholder was replaced');
        $this->assertStringNotContainsString('{REPORT_FILE}', $arg);
    }

    public function test_deprecation_lines_are_stripped_from_the_output_before_parsing(): void
    {
        $noisy = "PHP Deprecated: old thing\nreal finding here\nDeprecated: another\nsecond line";
        $executor = (new FakeProcessExecutor())->queueSuccess($noisy);
        $runner = new SequentialRunner($executor);

        $plan = StepPlan::command('phpmd', ['phpmd'])->filteringDeprecations();
        $result = $runner->run([new FakeStep('phpmd', $plan)], $this->context(), Mode::Check);

        $output = $result->steps[0]->output;
        $this->assertStringNotContainsString('PHP Deprecated:', $output);
        $this->assertStringNotContainsString('Deprecated:', $output);
        $this->assertStringContainsString('real finding here', $output);
        $this->assertStringContainsString('second line', $output);
    }

    public function test_a_missing_tool_without_a_require_hint_reports_a_plain_message(): void
    {
        $executor = new FakeProcessExecutor();
        $runner = new SequentialRunner($executor);

        // A vendor-bin tool with no require hint that doesn't exist on disk at /project.
        $step = new FakeStep('mago', StepPlan::command('mago', ['mago']), tool: Tool::vendorBin('mago'));
        $result = $runner->run([$step], $this->context(), Mode::Check);

        $this->assertSame(StepStatus::MissingTool, $result->steps[0]->status);
        $reason = (string) $result->steps[0]->skipReason;
        $this->assertStringContainsString('"mago" is not installed.', $reason);
        $this->assertStringNotContainsString('composer require', $reason, 'no hint means no require advice');
    }

    public function test_the_report_file_contents_are_handed_to_the_parser_then_deleted(): void
    {
        $captured = null;
        $executor = (new FakeProcessExecutor())
            ->queueFailure(1)
            ->onExecute(function ($spec) use (&$captured): void {
                // Emulate the tool writing its JUnit report to the substituted temp path.
                $arg = $spec->command[1];
                $captured = substr($arg, strlen('--log-junit='));
                file_put_contents($captured, '<testsuites><testsuite><testcase name="t" file="/project/tests/T.php" line="3"><failure>t: boom</failure></testcase></testsuite></testsuites>');
            });
        $runner = new SequentialRunner($executor);

        $plan = StepPlan::command('test', ['phpunit', '--log-junit=' . StepPlan::REPORT_FILE])
            ->parseWith(new JUnitParser('/project'))
            ->readingReportFile();
        $result = $runner->run([new FakeStep('test', $plan)], $this->context(), Mode::Check);

        // The parser saw the report contents.
        $this->assertCount(1, $result->steps[0]->findings);
        $this->assertStringContainsString('boom', $result->steps[0]->findings[0]->message);

        // The temp file was cleaned up.
        $this->assertNotNull($captured);
        $this->assertFileDoesNotExist($captured);
    }
}
