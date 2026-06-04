<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\JUnitParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Steps\Tests;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tests::class)]
final class TestsStepTest extends TestCase
{
    private function context(TempProject $project, ?TargetSet $targets = null): Context
    {
        return new Context($project->root, $targets ?? TargetSet::wholeProject());
    }

    private function projectWith(string ...$binaries): TempProject
    {
        $project = new TempProject();
        foreach ($binaries as $binary) {
            $project->file('vendor/bin/' . $binary, '#!/usr/bin/env php');
        }

        return $project;
    }

    public function test_its_identity(): void
    {
        $step = Tests::make();

        $this->assertSame('test', $step->name(), 'name is test, not the class basename');
        $this->assertSame('Tests', $step->label());
        $this->assertSame('phpunit', $step->tool()?->binary, 'phpunit underlies every runner');
        $this->assertSame([Mode::Check], $step->modes());
        $this->assertSame(Targeting::Files, $step->targeting());
        $this->assertSame('phpunit.xml', $step->defaultConfig());
    }

    public function test_it_writes_junit_to_a_report_file_and_parses_it(): void
    {
        $project = $this->projectWith('phpunit');

        $plan = Tests::make()->runner('phpunit')->plan($this->context($project), Mode::Check);

        $this->assertTrue($plan->readsReportFile);
        $this->assertContains('--log-junit=' . StepPlan::REPORT_FILE, $plan->command);
        $this->assertContains('--no-coverage', $plan->command);
        $this->assertInstanceOf(JUnitParser::class, $plan->parser);
    }

    public function test_explicit_phpunit_runner_uses_the_phpunit_binary_without_parallelism(): void
    {
        $project = $this->projectWith('phpunit');

        $plan = Tests::make()->runner('phpunit')->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/phpunit', $plan->command[0]);
        foreach ($plan->command as $arg) {
            $this->assertStringNotContainsString('--parallel', $arg);
            $this->assertStringNotContainsString('-p', $arg === '-p' ? $arg : 'x');
        }
    }

    public function test_paratest_runner_uses_the_paratest_binary_with_processes_auto(): void
    {
        $project = $this->projectWith('paratest');

        $plan = Tests::make()->runner('paratest')->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/paratest', $plan->command[0]);
        $this->assertContains('--processes=auto', $plan->command);
    }

    public function test_pest_runner_uses_the_pest_binary_with_parallel(): void
    {
        $project = $this->projectWith('pest');

        $plan = Tests::make()->runner('pest')->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/pest', $plan->command[0]);
        $this->assertContains('--parallel', $plan->command);
    }

    public function test_auto_prefers_paratest_then_pest_then_phpunit(): void
    {
        $all = $this->projectWith('phpunit', 'paratest', 'pest');
        $this->assertSame($all->root . '/vendor/bin/paratest', Tests::make()->plan($this->context($all), Mode::Check)->command[0]);

        $pestPhpunit = $this->projectWith('phpunit', 'pest');
        $this->assertSame($pestPhpunit->root . '/vendor/bin/pest', Tests::make()->plan($this->context($pestPhpunit), Mode::Check)->command[0]);

        $only = $this->projectWith('phpunit');
        $this->assertSame($only->root . '/vendor/bin/phpunit', Tests::make()->plan($this->context($only), Mode::Check)->command[0]);
    }

    public function test_availability_is_gated_on_phpunit_which_underlies_every_runner(): void
    {
        // paratest and pest both depend on phpunit, so phpunit's presence is the right
        // signal for whether tests can run; the concrete runner is still resolved per run.
        $this->assertSame('phpunit', Tests::make()->tool()?->binary);
    }

    public function test_it_uses_the_root_config_when_present(): void
    {
        $project = $this->projectWith('phpunit');
        $project->file('phpunit.xml', '<?xml version="1.0"?><phpunit/>');

        $plan = Tests::make()->runner('phpunit')->plan($this->context($project), Mode::Check);

        $this->assertContains('--configuration=' . $project->root . '/phpunit.xml', $plan->command);
    }

    public function test_a_filter_is_passed_through(): void
    {
        $project = $this->projectWith('phpunit');

        $plan = Tests::make()->runner('phpunit')->filter('MyTest')->plan($this->context($project), Mode::Check);

        $this->assertContains('--filter=MyTest', $plan->command);
    }

    public function test_a_narrowed_run_passes_target_files_as_paths(): void
    {
        $project = $this->projectWith('phpunit');
        $targets = TargetSet::narrowed([Target::file('tests/FooTest.php')]);

        $plan = Tests::make()->runner('phpunit')->plan($this->context($project, $targets), Mode::Check);

        $this->assertContains('tests/FooTest.php', $plan->command);
    }

    public function test_a_before_command_flows_into_the_plan(): void
    {
        $project = $this->projectWith('phpunit');

        $plan = Tests::make()->runner('phpunit')->before(['php', 'artisan', 'config:clear'])
            ->plan($this->context($project), Mode::Check);

        $this->assertSame([['php', 'artisan', 'config:clear']], $plan->before);
    }
}
