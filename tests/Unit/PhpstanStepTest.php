<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PhpstanParser;
use PdxApps\Preflight\Steps\Phpstan;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Phpstan::class)]
final class PhpstanStepTest extends TestCase
{
    private function context(TempProject $project, ?TargetSet $targets = null): Context
    {
        return new Context($project->root, $targets ?? TargetSet::wholeProject());
    }

    public function test_its_identity(): void
    {
        $step = Phpstan::make();

        $this->assertSame('phpstan', $step->name());
        $this->assertSame('PHPStan', $step->label());
        $this->assertSame('phpstan', $step->tool()?->binary);
        $this->assertSame('phpstan/phpstan', $step->tool()?->requireHint);
        $this->assertSame([Mode::Check], $step->modes(), 'phpstan is check-only');
        $this->assertSame(Targeting::Files, $step->targeting());
        $this->assertSame('phpstan.neon', $step->defaultConfig());
    }

    public function test_it_analyses_with_json_output_and_no_progress(): void
    {
        $project = new TempProject();

        $plan = Phpstan::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/phpstan', $plan->command[0]);
        $this->assertContains('analyse', $plan->command);
        $this->assertContains('--error-format=json', $plan->command);
        $this->assertContains('--no-progress', $plan->command);
        $this->assertInstanceOf(PhpstanParser::class, $plan->parser);
    }

    public function test_it_uses_the_root_config_when_present(): void
    {
        $project = new TempProject();
        $project->file('phpstan.neon', "parameters:\n    level: 5\n");

        $plan = Phpstan::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--configuration=' . $project->root . '/phpstan.neon', $plan->command);
    }

    public function test_it_falls_back_to_neon_dist_when_no_neon_exists(): void
    {
        $project = new TempProject();
        $project->file('phpstan.neon.dist', "parameters:\n    level: 5\n");

        $plan = Phpstan::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--configuration=' . $project->root . '/phpstan.neon.dist', $plan->command);
    }

    public function test_without_a_config_it_applies_a_default_level(): void
    {
        $project = new TempProject();

        $plan = Phpstan::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--level=5', $plan->command);
        foreach ($plan->command as $arg) {
            $this->assertStringNotContainsString('--configuration=', $arg);
        }
    }

    public function test_a_config_suppresses_the_default_level(): void
    {
        $project = new TempProject();
        $project->file('phpstan.neon', "parameters:\n    level: 8\n");

        $plan = Phpstan::make()->plan($this->context($project), Mode::Check);

        foreach ($plan->command as $arg) {
            $this->assertStringNotContainsString('--level=', $arg, 'the config owns the level');
        }
    }

    public function test_the_level_is_configurable(): void
    {
        $project = new TempProject();

        $plan = Phpstan::make()->level(9)->plan($this->context($project), Mode::Check);

        $this->assertContains('--level=9', $plan->command);
    }

    public function test_an_explicit_level_overrides_a_present_config(): void
    {
        $project = new TempProject();
        $project->file('phpstan.neon', "parameters:\n    level: 5\n");

        $plan = Phpstan::make()->level(9)->plan($this->context($project), Mode::Check);

        // The config file is still used, but --level is passed too and wins (CLI > neon).
        $this->assertContains('--configuration=' . $project->root . '/phpstan.neon', $plan->command);
        $this->assertContains('--level=9', $plan->command);
    }

    public function test_the_memory_limit_is_configurable(): void
    {
        $project = new TempProject();

        $plan = Phpstan::make()->memoryLimit('1G')->plan($this->context($project), Mode::Check);

        $this->assertContains('--memory-limit=1G', $plan->command);
    }

    public function test_a_narrowed_run_passes_target_files_as_path_arguments(): void
    {
        $project = new TempProject();
        $targets = TargetSet::narrowed([Target::file('src/A.php'), Target::file('src/B.php')]);

        $plan = Phpstan::make()->plan($this->context($project, $targets), Mode::Check);

        $this->assertContains('src/A.php', $plan->command);
        $this->assertContains('src/B.php', $plan->command);
    }
}
