<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Parsing\ExitCodeParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StepPlan::class)]
final class StepPlanTest extends TestCase
{
    public function test_notes_accumulate_and_default_to_empty(): void
    {
        $plan = StepPlan::command('test', ['phpunit']);
        $this->assertSame([], $plan->notes);

        $first = new Finding('test', Severity::Warning, 'one');
        $second = new Finding('test', Severity::Info, 'two');
        $withNotes = $plan->note($first)->note($second);

        $this->assertSame([$first, $second], $withNotes->notes);
        $this->assertSame([], $plan->notes, 'note() returns a copy, leaving the original untouched');
    }

    public function test_command_plan_defaults_to_the_exit_code_parser(): void
    {
        $plan = StepPlan::command('phpstan', ['phpstan', 'analyse']);

        $this->assertSame(['phpstan', 'analyse'], $plan->command);
        $this->assertSame([], $plan->before);
        $this->assertSame([], $plan->env);
        $this->assertFalse($plan->filtersDeprecations);

        // Default parser surfaces a finding on failure (i.e. it is an ExitCodeParser).
        $this->assertCount(1, $plan->parser->parse(new ProcessResult(1, '', ''))->findings);
    }

    public function test_parse_with_overrides_the_default_parser(): void
    {
        $parser = new ExitCodeParser('custom', 'nope');
        $plan = StepPlan::command('custom', ['x'])->parseWith($parser);

        $this->assertSame($parser, $plan->parser);
    }

    public function test_before_commands_are_appended_in_order(): void
    {
        $plan = StepPlan::command('test', ['paratest'])
            ->before(['php', 'artisan', 'config:clear']);

        $this->assertSame([['php', 'artisan', 'config:clear']], $plan->before);
    }

    public function test_multiple_before_calls_accumulate(): void
    {
        $plan = StepPlan::command('test', ['paratest'])
            ->before(['a'])
            ->before(['b']);

        $this->assertSame([['a'], ['b']], $plan->before);
    }

    public function test_env_and_deprecation_filter_are_configurable_immutably(): void
    {
        $plan = StepPlan::command('phpmd', ['phpmd']);
        $configured = $plan->withEnv(['X' => '1'])->filteringDeprecations();

        // Original is untouched (immutable builder).
        $this->assertSame([], $plan->env);
        $this->assertFalse($plan->filtersDeprecations);

        $this->assertSame(['X' => '1'], $configured->env);
        $this->assertTrue($configured->filtersDeprecations);
    }

    public function test_exit_code_plan_is_a_shortcut_for_a_pass_fail_command(): void
    {
        $plan = StepPlan::exitCode('composer-audit', ['composer', 'audit']);

        $this->assertSame(['composer', 'audit'], $plan->command);
        $this->assertCount(1, $plan->parser->parse(new ProcessResult(3, '', ''))->findings);
        $this->assertSame([], $plan->parser->parse(new ProcessResult(0, '', ''))->findings);
    }

    public function test_a_plan_does_not_read_a_report_file_by_default(): void
    {
        $plan = StepPlan::command('test', ['phpunit']);

        $this->assertFalse($plan->readsReportFile);
        $this->assertSame(StepPlan::REPORT_FILE, '{REPORT_FILE}');
    }

    public function test_reading_report_file_is_an_opt_in_capability(): void
    {
        $plan = StepPlan::command('test', ['phpunit', '--log-junit=' . StepPlan::REPORT_FILE])
            ->readingReportFile();

        $this->assertTrue($plan->readsReportFile);
        $this->assertContains('phpunit', $plan->command);
    }
}
