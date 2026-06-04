<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PhpmdParser;
use PdxApps\Preflight\Steps\Phpmd;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Phpmd::class)]
final class PhpmdStepTest extends TestCase
{
    private function context(TempProject $project, ?TargetSet $targets = null): Context
    {
        return new Context($project->root, $targets ?? TargetSet::wholeProject());
    }

    public function test_its_identity(): void
    {
        $step = Phpmd::make();

        $this->assertSame('phpmd', $step->name());
        $this->assertSame('PHPMD', $step->label());
        $this->assertSame('phpmd', $step->tool()?->binary);
        $this->assertSame('phpmd/phpmd', $step->tool()?->requireHint);
        $this->assertSame([Mode::Check], $step->modes());
        $this->assertSame(Targeting::Paths, $step->targeting());
        $this->assertSame('phpmd.xml', $step->defaultConfig());
    }

    public function test_it_runs_the_analyze_subcommand_with_json(): void
    {
        $project = new TempProject();

        $plan = Phpmd::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/phpmd', $plan->command[0]);
        $this->assertSame('analyze', $plan->command[1]);
        $this->assertContains('--format=json', $plan->command);
        $this->assertInstanceOf(PhpmdParser::class, $plan->parser);
    }

    public function test_it_uses_the_default_built_in_rulesets_when_no_phpmd_xml(): void
    {
        $project = new TempProject();

        $plan = Phpmd::make()->plan($this->context($project), Mode::Check);

        $ruleset = $this->optionValue($plan->command, '--ruleset=');
        $this->assertStringContainsString('cleancode', $ruleset);
        $this->assertStringContainsString('codesize', $ruleset);
    }

    public function test_it_uses_the_root_phpmd_xml_ruleset_when_present(): void
    {
        $project = new TempProject();
        $project->file('phpmd.xml', '<?xml version="1.0"?><ruleset/>');

        $plan = Phpmd::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/phpmd.xml', $this->optionValue($plan->command, '--ruleset='));
    }

    public function test_rulesets_are_configurable(): void
    {
        $project = new TempProject();

        $plan = Phpmd::make()->rulesets(['naming', 'unusedcode'])->plan($this->context($project), Mode::Check);

        $this->assertSame('naming,unusedcode', $this->optionValue($plan->command, '--ruleset='));
    }

    public function test_explicit_rulesets_override_a_present_phpmd_xml(): void
    {
        $project = new TempProject();
        $project->file('phpmd.xml', '<?xml version="1.0"?><ruleset/>');

        $plan = Phpmd::make()->rulesets(['naming'])->plan($this->context($project), Mode::Check);

        $this->assertSame('naming', $this->optionValue($plan->command, '--ruleset='));
    }

    public function test_whole_project_passes_the_configured_scan_paths(): void
    {
        $project = new TempProject();

        $plan = Phpmd::make()->paths(['app', 'src'])->plan($this->context($project), Mode::Check);

        $this->assertContains('app', $plan->command);
        $this->assertContains('src', $plan->command);
    }

    public function test_a_narrowed_run_passes_the_target_paths(): void
    {
        $project = new TempProject();
        $targets = TargetSet::narrowed([Target::directory('src/Steps'), Target::file('src/Mode.php')]);

        $plan = Phpmd::make()->plan($this->context($project, $targets), Mode::Check);

        // Paths targeting widens files to dirs; here: src/Steps + src (Mode.php's dir).
        $this->assertContains('src/Steps', $plan->command);
        $this->assertContains('src', $plan->command);
    }

    /**
     * Read the value of a `--name=value` option from a command argv.
     *
     * @param  list<string>  $command
     */
    private function optionValue(array $command, string $prefix): string
    {
        foreach ($command as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }

        return '';
    }
}
