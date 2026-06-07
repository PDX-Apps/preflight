<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Install\InstallCaveat;
use PdxApps\Preflight\Install\InstallOptions;
use PdxApps\Preflight\Install\InstallOutcome;
use PdxApps\Preflight\Install\InstallRecipe;
use PdxApps\Preflight\Install\PlannedInstall;
use PdxApps\Preflight\Install\SkippedInstall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallRecipe::class)]
#[CoversClass(InstallOptions::class)]
#[CoversClass(InstallCaveat::class)]
#[CoversClass(InstallOutcome::class)]
#[CoversClass(PlannedInstall::class)]
#[CoversClass(SkippedInstall::class)]
final class InstallValueObjectsTest extends TestCase
{
    public function test_recipe_builds_a_requirement_string_and_reports_a_caveat(): void
    {
        $plain = new InstallRecipe('phpstan/phpstan', '^2');
        $this->assertSame('phpstan/phpstan:^2', $plain->requirement());
        $this->assertFalse($plain->hasCaveat());

        $caveated = new InstallRecipe('phpmd/phpmd', '3.x-dev', new InstallCaveat('dev', 'https://x', true));
        $this->assertTrue($caveated->hasCaveat());
        $this->assertSame('phpmd/phpmd:3.x-dev', $caveated->requirement());
    }

    public function test_recipe_carries_its_config_strategy(): void
    {
        $recipe = new InstallRecipe('a/a', '^1', configFile: 'phpstan.neon', initArgs: ['--init']);

        $this->assertSame('phpstan.neon', $recipe->configFile);
        $this->assertSame(['--init'], $recipe->initArgs);
    }

    public function test_options_default_to_phpunit_with_configs_and_no_caveats(): void
    {
        $options = new InstallOptions();

        $this->assertSame('phpunit', $options->runner);
        $this->assertTrue($options->writeConfigs);
        $this->assertFalse($options->caveatApproved('phpmd'));
    }

    public function test_options_report_approved_caveats(): void
    {
        $options = new InstallOptions(runner: null, approvedCaveats: ['phpmd'], writeConfigs: false);

        $this->assertNull($options->runner);
        $this->assertFalse($options->writeConfigs);
        $this->assertTrue($options->caveatApproved('phpmd'));
        $this->assertFalse($options->caveatApproved('psalm'));
    }

    public function test_caveat_defaults_to_not_setting_minimum_stability(): void
    {
        $this->assertFalse(new InstallCaveat('note', 'https://x')->setsMinimumStabilityDev);
        $this->assertTrue(new InstallCaveat('note', 'https://x', true)->setsMinimumStabilityDev);
    }

    public function test_outcome_and_planned_and_skipped_expose_their_data(): void
    {
        $outcome = new InstallOutcome(true, ['done']);
        $this->assertTrue($outcome->success);
        $this->assertSame(['done'], $outcome->messages);

        $planned = new PlannedInstall('phpstan', new InstallRecipe('phpstan/phpstan', '^2'));
        $this->assertSame('phpstan', $planned->stepName);
        $this->assertSame('phpstan/phpstan', $planned->recipe->package);

        $skipped = new SkippedInstall('phpmd', 'phpmd/phpmd', 'caveat not approved');
        $this->assertSame('phpmd', $skipped->stepName);
        $this->assertSame('phpmd/phpmd', $skipped->package);
        $this->assertSame('caveat not approved', $skipped->reason);
    }
}
