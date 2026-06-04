<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Console\InitCommand;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InitCommand::class)]
final class InitCommandTest extends TestCase
{
    private function tester(TempProject $project): CommandTester
    {
        return new CommandTester(new InitCommand($project->root));
    }

    public function test_it_scaffolds_a_preflight_config_file(): void
    {
        $project = new TempProject();

        $exit = $this->tester($project)->execute([], ['decorated' => false]);

        $this->assertSame(0, $exit);
        $this->assertFileExists($project->root . '/preflight.php');
    }

    public function test_the_scaffolded_file_returns_a_valid_configuration(): void
    {
        $project = new TempProject();
        $this->tester($project)->execute([], ['decorated' => false]);

        $returned = require $project->root . '/preflight.php';

        // It returns a builder; building it must not error.
        $this->assertNotNull($returned->build());
    }

    public function test_it_does_not_overwrite_an_existing_file_without_force(): void
    {
        $project = new TempProject();
        $project->file('preflight.php', '<?php // mine');

        $exit = $this->tester($project)->execute([], ['decorated' => false]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('<?php // mine', (string) file_get_contents($project->root . '/preflight.php'));
    }

    public function test_force_overwrites_an_existing_file(): void
    {
        $project = new TempProject();
        $project->file('preflight.php', '<?php // mine');

        $exit = $this->tester($project)->execute(['--force' => true], ['decorated' => false]);

        $this->assertSame(0, $exit);
        $this->assertStringNotContainsString('// mine', (string) file_get_contents($project->root . '/preflight.php'));
    }

    public function test_it_gitignores_the_freshness_cache(): void
    {
        $project = new TempProject();
        $project->file('.gitignore', "/vendor/\n");

        $this->tester($project)->execute([], ['decorated' => false]);

        $this->assertStringContainsString('.preflight.cache.json', (string) file_get_contents($project->root . '/.gitignore'));
    }

    public function test_it_creates_a_gitignore_when_absent(): void
    {
        $project = new TempProject();

        $this->tester($project)->execute([], ['decorated' => false]);

        $this->assertStringContainsString('.preflight.cache.json', (string) file_get_contents($project->root . '/.gitignore'));
    }

    public function test_it_does_not_duplicate_the_gitignore_entry(): void
    {
        $project = new TempProject();
        $project->file('.gitignore', ".preflight.cache.json\n");

        $this->tester($project)->execute([], ['decorated' => false]);

        $this->assertSame(1, substr_count((string) file_get_contents($project->root . '/.gitignore'), '.preflight.cache.json'));
    }
}
