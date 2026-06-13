<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Console\InstallCommand;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InstallCommand::class)]
final class InstallCommandTest extends TestCase
{
    private function tester(TempProject $project, FakeProcessExecutor $executor): CommandTester
    {
        $command = new InstallCommand($project->root, $executor);
        // The interactive prompts ask for the "question" helper, which a bare command lacks.
        $command->setHelperSet(new HelperSet(['question' => new QuestionHelper()]));

        return new CommandTester($command);
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

    public function test_it_scaffolds_a_psalm_config_with_dead_code_analysis_off(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $this->tester($project, $executor)->execute(['--yes' => true], ['interactive' => false]);

        $this->assertFileExists($project->root . '/psalm.xml');
        $psalm = (string) file_get_contents($project->root . '/psalm.xml');
        $this->assertStringContainsString('findUnusedCode="false"', $psalm);

        // It is scaffolded, not delegated to the tool's own init.
        foreach ($executor->commands() as $command) {
            $this->assertFalse(
                str_ends_with($command[0], '/vendor/bin/psalm') && in_array('--init', $command, true),
                'psalm --init should no longer be run',
            );
        }
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

    public function test_without_yes_a_non_interactive_run_explains_how_to_apply_and_changes_nothing(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $tester = $this->tester($project, $executor);
        $tester->execute([], ['interactive' => false]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $executor->executed, 'nothing is installed without confirmation');
        $this->assertStringContainsString('Pass --yes to apply', $tester->getDisplay());
        $this->assertFileDoesNotExist($project->root . '/phpstan.neon');
    }

    public function test_an_interactive_yes_confirmation_proceeds_with_the_install(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $tester = $this->tester($project, $executor);
        // Runner choice (default phpunit), then the "Proceed?" confirmation.
        $tester->setInputs(['phpunit', 'yes']);
        $tester->execute(['--runner' => 'phpunit'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertContains('phpstan/phpstan:^2', $this->requireCommand($executor));
    }

    public function test_an_interactive_declined_confirmation_aborts(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $tester = $this->tester($project, $executor);
        $tester->setInputs(['no']);
        $tester->execute(['--runner' => 'phpunit', '--with-phpmd' => true], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $executor->executed, 'declining must install nothing');
        $this->assertStringContainsString('Aborted.', $tester->getDisplay());
    }

    public function test_the_interactive_runner_prompt_selects_pest(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $tester = $this->tester($project, $executor);
        // Runner prompt (pest), PHPMD opt-in prompt (no), then confirm the install.
        $tester->setInputs(['pest', 'no', 'yes']);
        $tester->execute([], ['interactive' => true]);

        $require = $this->requireCommand($executor);
        $this->assertContains('pestphp/pest:^3', $require);
    }

    public function test_the_interactive_phpmd_prompt_opts_in(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $executor = new FakeProcessExecutor();

        $tester = $this->tester($project, $executor);
        // Runner choice, PHPMD opt-in, then proceed.
        $tester->setInputs(['phpunit', 'yes', 'yes']);
        $tester->execute([], ['interactive' => true]);

        $this->assertContains('phpmd/phpmd:^3@dev', $this->requireCommand($executor));
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
