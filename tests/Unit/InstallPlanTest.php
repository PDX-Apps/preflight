<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Install\InstallCaveat;
use PdxApps\Preflight\Install\InstallPlan;
use PdxApps\Preflight\Install\InstallRecipe;
use PdxApps\Preflight\Install\PlannedInstall;
use PdxApps\Preflight\Install\SkippedInstall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallPlan::class)]
final class InstallPlanTest extends TestCase
{
    private function planned(string $step, string $package, string $constraint, ?InstallCaveat $caveat = null): PlannedInstall
    {
        return new PlannedInstall($step, new InstallRecipe($package, $constraint, $caveat));
    }

    public function test_an_empty_plan_reports_empty(): void
    {
        $this->assertTrue((new InstallPlan())->isEmpty());
        $this->assertFalse(new InstallPlan([$this->planned('phpstan', 'phpstan/phpstan', '^2')])->isEmpty());
    }

    public function test_requirements_join_package_and_constraint(): void
    {
        $plan = new InstallPlan([
            $this->planned('phpstan', 'phpstan/phpstan', '^2'),
            $this->planned('pint', 'laravel/pint', '^1.29'),
        ]);

        $this->assertSame(['phpstan/phpstan:^2', 'laravel/pint:^1.29'], $plan->requirements());
    }

    public function test_sets_minimum_stability_dev_only_when_a_planned_caveat_requires_it(): void
    {
        $devCaveat = new InstallCaveat('dev branch', 'https://x', setsMinimumStabilityDev: true);
        $plainCaveat = new InstallCaveat('note', 'https://x');

        $this->assertTrue(new InstallPlan([$this->planned('phpmd', 'phpmd/phpmd', '3.x-dev', $devCaveat)])->setsMinimumStabilityDev());
        $this->assertFalse(new InstallPlan([$this->planned('a', 'a/a', '^1', $plainCaveat)])->setsMinimumStabilityDev());
        $this->assertFalse(new InstallPlan([$this->planned('a', 'a/a', '^1')])->setsMinimumStabilityDev());
    }

    public function test_skipped_installs_are_carried_through(): void
    {
        $plan = new InstallPlan([], [new SkippedInstall('phpmd', 'phpmd/phpmd', 'already installed')]);

        $this->assertTrue($plan->isEmpty());
        $this->assertCount(1, $plan->skipped);
        $this->assertSame('already installed', $plan->skipped[0]->reason);
    }
}
