<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Install\InstallCaveat;
use PdxApps\Preflight\Install\Installer;
use PdxApps\Preflight\Install\InstallPlan;
use PdxApps\Preflight\Install\InstallRecipe;
use PdxApps\Preflight\Install\PlannedInstall;
use PdxApps\Preflight\Steps\Psalm;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Installer::class)]
final class InstallerTest extends TestCase
{
    private function plan(InstallRecipe $recipe, string $stepName = 'phpstan'): InstallPlan
    {
        return new InstallPlan([new PlannedInstall($stepName, $recipe)]);
    }

    public function test_a_dev_branch_caveat_sets_minimum_stability_before_requiring(): void
    {
        $project = new TempProject();
        $executor = new FakeProcessExecutor();
        $recipe = new InstallRecipe(
            'phpmd/phpmd',
            '3.x-dev',
            new InstallCaveat('dev branch', 'https://x', setsMinimumStabilityDev: true),
        );

        $outcome = (new Installer($project->root, $executor))->apply($this->plan($recipe, 'phpmd'), [], writeConfigs: false, force: false);

        $this->assertTrue($outcome->success);
        $commands = $executor->commands();
        $this->assertContains(['composer', 'config', 'minimum-stability', 'dev'], $commands);
        $this->assertContains(['composer', 'config', 'prefer-stable', 'true'], $commands);
        $this->assertContains(['composer', 'require', '--dev', 'phpmd/phpmd:3.x-dev'], $commands);
    }

    public function test_it_requires_without_touching_stability_when_there_is_no_dev_caveat(): void
    {
        $project = new TempProject();
        $executor = new FakeProcessExecutor();

        $outcome = (new Installer($project->root, $executor))->apply(
            $this->plan(new InstallRecipe('phpstan/phpstan', '^2')),
            [],
            writeConfigs: false,
            force: false,
        );

        $this->assertTrue($outcome->success);
        $this->assertSame([['composer', 'require', '--dev', 'phpstan/phpstan:^2']], $executor->commands());
    }

    public function test_a_failed_require_returns_a_failure_outcome_and_skips_scaffolding(): void
    {
        $project = new TempProject();
        $executor = (new FakeProcessExecutor())->queueFailure(1, 'version conflict');

        $outcome = (new Installer($project->root, $executor))->apply(
            $this->plan(new InstallRecipe('phpstan/phpstan', '^2', configFile: 'phpstan.neon')),
            [],
            writeConfigs: true,
            force: false,
        );

        $this->assertFalse($outcome->success);
        $this->assertStringContainsString('composer require failed', implode("\n", $outcome->messages));
        $this->assertStringContainsString('version conflict', implode("\n", $outcome->messages));
        $this->assertFileDoesNotExist($project->root . '/phpstan.neon');
    }

    public function test_it_scaffolds_a_config_file_on_success(): void
    {
        $project = new TempProject();
        $executor = new FakeProcessExecutor();

        $outcome = (new Installer($project->root, $executor))->apply(
            $this->plan(new InstallRecipe('phpstan/phpstan', '^2', configFile: 'phpstan.neon')),
            [],
            writeConfigs: true,
            force: false,
        );

        $this->assertTrue($outcome->success);
        $this->assertFileExists($project->root . '/phpstan.neon');
        $this->assertStringContainsString('config phpstan.neon', implode("\n", $outcome->messages));
    }

    public function test_an_existing_config_is_skipped_unless_forced(): void
    {
        $project = new TempProject();
        $project->file('phpstan.neon', 'original');
        $recipe = new InstallRecipe('phpstan/phpstan', '^2', configFile: 'phpstan.neon');

        new Installer($project->root, new FakeProcessExecutor())->apply($this->plan($recipe), [], writeConfigs: true, force: false);
        $this->assertSame('original', file_get_contents($project->root . '/phpstan.neon'), 'kept without --force');

        new Installer($project->root, new FakeProcessExecutor())->apply($this->plan($recipe), [], writeConfigs: true, force: true);
        $this->assertNotSame('original', file_get_contents($project->root . '/phpstan.neon'), 'overwritten with --force');
    }

    public function test_writing_configs_off_skips_both_scaffolding_and_inits(): void
    {
        $project = new TempProject();
        $executor = new FakeProcessExecutor();

        (new Installer($project->root, $executor))->apply(
            $this->plan(new InstallRecipe('phpstan/phpstan', '^2', configFile: 'phpstan.neon')),
            [],
            writeConfigs: false,
            force: false,
        );

        $this->assertFileDoesNotExist($project->root . '/phpstan.neon');
        $this->assertSame([['composer', 'require', '--dev', 'phpstan/phpstan:^2']], $executor->commands());
    }

    public function test_it_delegates_config_creation_to_a_tools_own_init(): void
    {
        $project = new TempProject();
        $executor = new FakeProcessExecutor();
        $recipe = new InstallRecipe('vimeo/psalm', '^6', initArgs: ['--init']);

        (new Installer($project->root, $executor))->apply(
            $this->plan($recipe, 'psalm'),
            ['psalm' => Psalm::make()],
            writeConfigs: true,
            force: false,
        );

        $this->assertContains([$project->root . '/vendor/bin/psalm', '--init'], $executor->commands());
    }
}
