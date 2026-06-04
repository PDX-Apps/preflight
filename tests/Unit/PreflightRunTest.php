<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Config\Configuration;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepStatus;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PdxApps\Preflight\Tests\Support\TempProject;
use PdxApps\Preflight\Tests\Unit\Fixtures\ConfigurableStep;
use PdxApps\Preflight\Tests\Unit\Fixtures\SecondConfigurableStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preflight::class)]
final class PreflightRunTest extends TestCase
{
    private function project(): TempProject
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('vendor/bin/configurable', '#!/usr/bin/env php');

        return $project;
    }

    public function test_with_no_explicit_steps_it_auto_runs_installed_built_ins(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('vendor/bin/pint', '#!/usr/bin/env php'); // Pint "installed"
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        // Default configuration: no explicit steps -> auto-detect.
        $result = Preflight::make(new Configuration(), projectRoot: $project->root, executor: $executor)->run(Mode::Check);

        $names = array_map(static fn ($s) => $s->name, $result->steps);
        $this->assertContains('pint', $names);
    }

    public function test_with_no_explicit_steps_and_no_vendor_tools_only_composer_audit_runs(): void
    {
        // No vendor/bin tools are installed, so every vendor-bin step is skipped. composer
        // audit needs no install (composer ships it), so it remains the one auto-detected step.
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $result = Preflight::make(new Configuration(), projectRoot: $project->root, executor: $executor)->run(Mode::Check);

        $names = array_map(static fn ($s) => $s->name, $result->steps);
        $this->assertSame(['composer-audit'], $names);
        $this->assertNotContains('pint', $names);
    }

    public function test_it_runs_the_configured_steps_and_returns_a_run_result(): void
    {
        $project = $this->project();
        $executor = (new FakeProcessExecutor())->queueSuccess();

        $config = new Configuration(steps: [ConfigurableStep::make()]);
        $result = Preflight::make($config, projectRoot: $project->root, executor: $executor)->run(Mode::Check);

        $this->assertInstanceOf(RunResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertCount(1, $result->steps);
        $this->assertSame('configurable-step', $result->steps[0]->name);
        $this->assertSame(StepStatus::Passed, $result->steps[0]->status);
    }

    public function test_steps_removed_with_without_are_excluded_from_the_run(): void
    {
        $project = $this->project();
        $executor = (new FakeProcessExecutor())->queueSuccess();

        $config = new Configuration(
            steps: [ConfigurableStep::make(), SecondConfigurableStep::make()],
            without: [SecondConfigurableStep::class],
        );
        $result = Preflight::make($config, projectRoot: $project->root, executor: $executor)->run(Mode::Check);

        $this->assertCount(1, $result->steps);
        $this->assertSame('configurable-step', $result->steps[0]->name);
    }

    public function test_it_honors_fail_fast_from_the_configuration(): void
    {
        $project = $this->project();
        $executor = (new FakeProcessExecutor())->queueFailure(1);

        $config = new Configuration(
            steps: [ConfigurableStep::make(), SecondConfigurableStep::make()],
            failFast: true,
        );
        $result = Preflight::make($config, projectRoot: $project->root, executor: $executor)->run(Mode::Check);

        $this->assertSame(StepStatus::Failed, $result->steps[0]->status);
        $this->assertSame(StepStatus::Skipped, $result->steps[1]->status);
        $this->assertCount(1, $executor->executed);
    }

    public function test_a_steps_own_settings_drive_the_executed_command(): void
    {
        $project = $this->project();
        $project->file('phpstan.neon', '');
        $executor = (new FakeProcessExecutor())->queueSuccess();

        $config = new Configuration(steps: [ConfigurableStep::make()->config('phpstan.neon')]);
        Preflight::make($config, projectRoot: $project->root, executor: $executor)->run(Mode::Check);

        $this->assertSame(
            [['configurable', '--config=' . $project->root . '/phpstan.neon']],
            $executor->commands(),
        );
    }
}
