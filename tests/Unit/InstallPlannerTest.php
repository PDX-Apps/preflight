<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Install\InstallOptions;
use PdxApps\Preflight\Install\InstallPlanner;
use PdxApps\Preflight\Steps\StepRegistry;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallPlanner::class)]
final class InstallPlannerTest extends TestCase
{
    /**
     * @return list<Step>
     */
    private function defaultSteps(): array
    {
        return array_map(static fn (string $class): Step => $class::make(), StepRegistry::defaults());
    }

    private function planner(TempProject $project): InstallPlanner
    {
        return new InstallPlanner(new Context($project->root, TargetSet::wholeProject()));
    }

    public function test_a_bare_project_installs_the_clean_tools_and_a_default_runner(): void
    {
        $plan = $this->planner(new TempProject())->plan($this->defaultSteps(), new InstallOptions());

        $reqs = $plan->requirements();
        $this->assertContains('laravel/pint:^1', $reqs);
        $this->assertContains('squizlabs/php_codesniffer:^4', $reqs);
        $this->assertContains('phpstan/phpstan:^2', $reqs);
        $this->assertContains('rector/rector:^2', $reqs);
        $this->assertContains('vimeo/psalm:^6', $reqs);
        $this->assertContains('phpunit/phpunit:^11', $reqs, 'the default runner');
    }

    public function test_composer_audit_is_never_part_of_the_plan(): void
    {
        $plan = $this->planner(new TempProject())->plan($this->defaultSteps(), new InstallOptions());

        foreach ([...$plan->installs, ...$plan->skipped] as $item) {
            $this->assertNotSame('composer-audit', $item->stepName);
        }
    }

    public function test_phpmd_is_skipped_unless_its_caveat_is_approved(): void
    {
        $plan = $this->planner(new TempProject())->plan($this->defaultSteps(), new InstallOptions());

        $this->assertNotContains('phpmd/phpmd:^3@dev', $plan->requirements());
        $skipped = array_filter($plan->skipped, static fn ($s) => $s->stepName === 'phpmd');
        $this->assertCount(1, $skipped);
        $this->assertFalse($plan->setsMinimumStabilityDev());
    }

    public function test_approving_the_phpmd_caveat_includes_it_and_flags_dev_stability(): void
    {
        $options = new InstallOptions(approvedCaveats: ['phpmd']);

        $plan = $this->planner(new TempProject())->plan($this->defaultSteps(), $options);

        $this->assertContains('phpmd/phpmd:^3@dev', $plan->requirements());
        $this->assertTrue($plan->setsMinimumStabilityDev());
    }

    public function test_an_already_installed_tool_is_skipped_not_reinstalled(): void
    {
        $project = new TempProject();
        $project->file('vendor/bin/phpstan', '#!/usr/bin/env php');

        $plan = $this->planner($project)->plan($this->defaultSteps(), new InstallOptions());

        $this->assertNotContains('phpstan/phpstan:^2', $plan->requirements());
        $skipped = array_filter($plan->skipped, static fn ($s) => $s->stepName === 'phpstan');
        $this->assertCount(1, $skipped);
    }

    public function test_the_runner_choice_is_honored(): void
    {
        $plan = $this->planner(new TempProject())->plan($this->defaultSteps(), new InstallOptions(runner: 'pest'));

        $this->assertContains('pestphp/pest:^3', $plan->requirements());
        $this->assertNotContains('phpunit/phpunit:^11', $plan->requirements());
    }

    public function test_no_runner_is_installed_when_runner_is_none(): void
    {
        $plan = $this->planner(new TempProject())->plan($this->defaultSteps(), new InstallOptions(runner: null));

        $this->assertNotContains('phpunit/phpunit:^11', $plan->requirements());
        $this->assertNotContains('pestphp/pest:^3', $plan->requirements());
    }

    public function test_an_existing_runner_means_the_test_step_is_skipped(): void
    {
        $project = new TempProject();
        $project->file('vendor/bin/phpunit', '#!/usr/bin/env php');

        $plan = $this->planner($project)->plan($this->defaultSteps(), new InstallOptions(runner: 'pest'));

        // phpunit underlies all runners; its presence means tests can already run.
        $this->assertNotContains('pestphp/pest:^3', $plan->requirements());
        $this->assertNotContains('phpunit/phpunit:^11', $plan->requirements());
    }
}
