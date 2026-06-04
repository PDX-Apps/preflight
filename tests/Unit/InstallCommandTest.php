<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Console\InstallCommand;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InstallCommand::class)]
final class InstallCommandTest extends TestCase
{
    private function tester(TempProject $project, FakeProcessExecutor $executor): CommandTester
    {
        return new CommandTester(new InstallCommand($project->root, $executor));
    }

    /**
     * The argv of the executed `composer require` call, or [] if none ran.
     *
     * @return list<string>
     */
    private function requireCommand(FakeProcessExecutor $executor): array
    {
        foreach ($executor->commands() as $command) {
            if (($command[1] ?? null) === 'require') {
                return $command;
            }
        }

        return [];
    }

    private function ranComposerConfig(FakeProcessExecutor $executor, string $key): bool
    {
        foreach ($executor->commands() as $command) {
            if (($command[1] ?? null) === 'config' && in_array($key, $command, true)) {
                return true;
            }
        }

        return false;
    }

    public function test_a_bare_project_requires_the_clean_tools_and_the_default_runner(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $this->tester($project, $executor)->execute(['--yes' => true], ['interactive' => false]);

        $require = $this->requireCommand($executor);
        $this->assertContains('phpstan/phpstan:^2', $require);
        $this->assertContains('laravel/pint:^1', $require);
        $this->assertContains('phpunit/phpunit:^11', $require);
        $this->assertNotContains('phpmd/phpmd:^3@dev', $require, 'phpmd is opt-in');
        $this->assertFalse($this->ranComposerConfig($executor, 'minimum-stability'));
    }

    public function test_it_scaffolds_config_files_for_installed_tools(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('src/Foo.php', '<?php');

        $this->tester($project, new FakeProcessExecutor())->execute(['--yes' => true], ['interactive' => false]);

        $this->assertFileExists($project->root . '/pint.json');
        $this->assertFileExists($project->root . '/phpcs.xml');
        $this->assertFileExists($project->root . '/phpstan.neon');
        $this->assertFileExists($project->root . '/rector.php');
        $this->assertStringContainsString('- src', (string) file_get_contents($project->root . '/phpstan.neon'));
    }

    public function test_it_delegates_to_psalm_init(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $this->tester($project, $executor)->execute(['--yes' => true], ['interactive' => false]);

        $ranInit = false;
        foreach ($executor->commands() as $command) {
            if (str_ends_with($command[0], '/vendor/bin/psalm') && in_array('--init', $command, true)) {
                $ranInit = true;
            }
        }
        $this->assertTrue($ranInit, 'psalm --init should be delegated to');
    }

    public function test_with_phpmd_sets_dev_stability_and_requires_the_dev_branch(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $this->tester($project, $executor)->execute(['--yes' => true, '--with-phpmd' => true], ['interactive' => false]);

        $this->assertTrue($this->ranComposerConfig($executor, 'minimum-stability'));
        $this->assertContains('phpmd/phpmd:^3@dev', $this->requireCommand($executor));
        $this->assertFileExists($project->root . '/phpmd.xml');
    }

    public function test_dry_run_changes_nothing(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $this->tester($project, $executor)->execute(['--dry-run' => true], ['interactive' => false]);

        $this->assertSame([], $executor->executed);
        $this->assertFileDoesNotExist($project->root . '/phpstan.neon');
    }

    public function test_no_configs_installs_but_writes_no_files(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $this->tester($project, $executor)->execute(['--yes' => true, '--no-configs' => true], ['interactive' => false]);

        $this->assertNotSame([], $this->requireCommand($executor));
        $this->assertFileDoesNotExist($project->root . '/phpstan.neon');
    }

    public function test_the_runner_flag_selects_pest(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $this->tester($project, $executor)->execute(['--yes' => true, '--runner' => 'pest'], ['interactive' => false]);

        $require = $this->requireCommand($executor);
        $this->assertContains('pestphp/pest:^3', $require);
        $this->assertNotContains('phpunit/phpunit:^11', $require);
    }

    public function test_runner_none_installs_no_runner(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $this->tester($project, $executor)->execute(['--yes' => true, '--runner' => 'none'], ['interactive' => false]);

        $require = $this->requireCommand($executor);
        $this->assertNotContains('phpunit/phpunit:^11', $require);
        $this->assertNotContains('pestphp/pest:^3', $require);
    }

    public function test_when_everything_is_installed_nothing_runs(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        foreach (['pint', 'phpcs', 'phpstan', 'rector', 'psalm', 'phpunit'] as $binary) {
            $project->file('vendor/bin/' . $binary, '#!/usr/bin/env php');
        }
        $executor = new FakeProcessExecutor();

        $tester = $this->tester($project, $executor);
        $tester->execute(['--yes' => true], ['interactive' => false]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $executor->executed);
    }

    public function test_an_existing_config_is_not_overwritten_without_force(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('phpstan.neon', '# my config');

        $this->tester($project, new FakeProcessExecutor())->execute(['--yes' => true], ['interactive' => false]);
        $this->assertSame('# my config', file_get_contents($project->root . '/phpstan.neon'));

        $this->tester($project, new FakeProcessExecutor())->execute(['--yes' => true, '--force' => true], ['interactive' => false]);
        $this->assertNotSame('# my config', file_get_contents($project->root . '/phpstan.neon'));
    }

    public function test_a_failed_composer_require_aborts_before_scaffolding(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = (new FakeProcessExecutor())->queue(new ProcessResult(1, '', 'conflict'));

        $tester = $this->tester($project, $executor);
        $tester->execute(['--yes' => true], ['interactive' => false]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertFileDoesNotExist($project->root . '/phpstan.neon');
    }
}
