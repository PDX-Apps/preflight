<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PintParser;
use PdxApps\Preflight\Steps\Pint;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pint::class)]
final class PintStepTest extends TestCase
{
    private function context(TempProject $project, ?TargetSet $targets = null): Context
    {
        return new Context($project->root, $targets ?? TargetSet::wholeProject());
    }

    public function test_its_identity(): void
    {
        $pint = Pint::make();

        $this->assertSame('pint', $pint->name());
        $this->assertSame('Pint', $pint->label());
        $this->assertSame('pint', $pint->tool()?->binary);
        $this->assertSame('laravel/pint', $pint->tool()?->requireHint);
        $this->assertSame([Mode::Check, Mode::Fix], $pint->modes());
        $this->assertSame(Targeting::Files, $pint->targeting());
    }

    public function test_check_mode_runs_pint_in_test_mode_with_json_output_and_the_root_config(): void
    {
        $project = new TempProject();
        $project->file('pint.json', '{}');

        $plan = Pint::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/pint', $plan->command[0]);
        $this->assertContains('--test', $plan->command);
        $this->assertContains('--format=json', $plan->command);
        $this->assertContains('--config=' . $project->root . '/pint.json', $plan->command);
        $this->assertInstanceOf(PintParser::class, $plan->parser);
    }

    public function test_fix_mode_omits_the_test_flag(): void
    {
        $project = new TempProject();
        $project->file('pint.json', '{}');

        $plan = Pint::make()->plan($this->context($project), Mode::Fix);

        $this->assertNotContains('--test', $plan->command);
        $this->assertContains('--config=' . $project->root . '/pint.json', $plan->command);
    }

    public function test_it_omits_the_config_flag_when_no_pint_json_exists(): void
    {
        $project = new TempProject();

        $plan = Pint::make()->plan($this->context($project), Mode::Check);

        foreach ($plan->command as $arg) {
            $this->assertStringNotContainsString('--config=', $arg);
        }
    }

    public function test_a_custom_config_reference_overrides_the_default(): void
    {
        $project = new TempProject();
        $project->file('quality/pint.json', '{}');

        $plan = Pint::make()->config('quality/pint.json')->plan($this->context($project), Mode::Check);

        $this->assertContains('--config=' . $project->root . '/quality/pint.json', $plan->command);
    }

    public function test_a_narrowed_run_passes_the_target_files_as_path_arguments(): void
    {
        $project = new TempProject();
        $targets = TargetSet::narrowed([Target::file('app/A.php'), Target::file('app/B.php')]);

        $plan = Pint::make()->plan($this->context($project, $targets), Mode::Check);

        $this->assertContains('app/A.php', $plan->command);
        $this->assertContains('app/B.php', $plan->command);
    }

    public function test_before_commands_and_extra_args_flow_into_the_plan(): void
    {
        $project = new TempProject();

        $plan = Pint::make()
            ->before(['php', 'artisan', 'config:clear'])
            ->args(['--dirty'])
            ->plan($this->context($project), Mode::Check);

        $this->assertSame([['php', 'artisan', 'config:clear']], $plan->before);
        $this->assertContains('--dirty', $plan->command);
    }
}
