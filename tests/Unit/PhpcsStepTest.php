<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PhpcsParser;
use PdxApps\Preflight\Steps\Phpcs;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Phpcs::class)]
final class PhpcsStepTest extends TestCase
{
    private function context(TempProject $project, ?TargetSet $targets = null): Context
    {
        return new Context($project->root, $targets ?? TargetSet::wholeProject());
    }

    public function test_its_identity(): void
    {
        $step = Phpcs::make();

        $this->assertSame('phpcs', $step->name());
        $this->assertSame('PHPCS', $step->label());
        $this->assertSame('phpcs', $step->tool()?->binary);
        $this->assertSame('squizlabs/php_codesniffer', $step->tool()?->requireHint);
        $this->assertSame([Mode::Check, Mode::Fix], $step->modes());
        $this->assertSame(Targeting::Files, $step->targeting());
        $this->assertSame('phpcs.xml', $step->defaultConfig());
    }

    public function test_check_mode_runs_phpcs_with_json_report(): void
    {
        $project = new TempProject();

        $plan = Phpcs::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/phpcs', $plan->command[0]);
        $this->assertContains('--report=json', $plan->command);
        $this->assertInstanceOf(PhpcsParser::class, $plan->parser);
    }

    public function test_fix_mode_runs_the_phpcbf_binary_without_json_report(): void
    {
        $project = new TempProject();

        $plan = Phpcs::make()->plan($this->context($project), Mode::Fix);

        $this->assertSame($project->root . '/vendor/bin/phpcbf', $plan->command[0]);
        $this->assertNotContains('--report=json', $plan->command);
    }

    public function test_it_uses_the_root_standard_when_present(): void
    {
        $project = new TempProject();
        $project->file('phpcs.xml', '<?xml version="1.0"?><ruleset/>');

        $plan = Phpcs::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--standard=' . $project->root . '/phpcs.xml', $plan->command);
    }

    public function test_it_falls_back_to_phpcs_xml_dist(): void
    {
        $project = new TempProject();
        $project->file('phpcs.xml.dist', '<?xml version="1.0"?><ruleset/>');

        $plan = Phpcs::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--standard=' . $project->root . '/phpcs.xml.dist', $plan->command);
    }

    public function test_it_defaults_to_the_psr12_standard_when_no_config_exists(): void
    {
        $project = new TempProject();

        $plan = Phpcs::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--standard=PSR12', $plan->command);
    }

    public function test_the_standard_is_configurable(): void
    {
        $project = new TempProject();

        $plan = Phpcs::make()->standard('PSR2')->plan($this->context($project), Mode::Check);

        $this->assertContains('--standard=PSR2', $plan->command);
    }

    public function test_an_explicit_standard_overrides_a_present_config(): void
    {
        $project = new TempProject();
        $project->file('phpcs.xml', '<?xml version="1.0"?><ruleset/>');

        $plan = Phpcs::make()->standard('PSR12')->plan($this->context($project), Mode::Check);

        $this->assertContains('--standard=PSR12', $plan->command);
        $this->assertNotContains('--standard=' . $project->root . '/phpcs.xml', $plan->command);
    }

    public function test_parallelism_is_added_only_when_configured(): void
    {
        $project = new TempProject();

        $default = Phpcs::make()->plan($this->context($project), Mode::Check);
        foreach ($default->command as $arg) {
            $this->assertStringNotContainsString('--parallel=', $arg);
        }

        $parallel = Phpcs::make()->parallel(4)->plan($this->context($project), Mode::Check);
        $this->assertContains('--parallel=4', $parallel->command);
    }

    public function test_a_narrowed_run_passes_target_files(): void
    {
        $project = new TempProject();
        $targets = TargetSet::narrowed([Target::file('src/A.php'), Target::file('src/B.php')]);

        $plan = Phpcs::make()->plan($this->context($project, $targets), Mode::Check);

        $this->assertContains('src/A.php', $plan->command);
        $this->assertContains('src/B.php', $plan->command);
    }
}
