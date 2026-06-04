<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\DeptracParser;
use PdxApps\Preflight\Steps\Deptrac;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Deptrac::class)]
final class DeptracStepTest extends TestCase
{
    private function context(TempProject $project): Context
    {
        return new Context($project->root, TargetSet::wholeProject());
    }

    private function optionValue(array $command, string $prefix): string
    {
        foreach ($command as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }

        return '';
    }

    public function test_its_identity(): void
    {
        $step = Deptrac::make();

        $this->assertSame('deptrac', $step->name());
        $this->assertSame('Deptrac', $step->label());
        $this->assertSame('deptrac', $step->tool()?->binary);
        $this->assertSame('deptrac/deptrac', $step->tool()?->requireHint);
        $this->assertSame([Mode::Check], $step->modes());
        $this->assertSame(Targeting::Whole, $step->targeting());
        $this->assertSame('deptrac.yaml', $step->defaultConfig());
    }

    public function test_it_analyses_as_json(): void
    {
        $project = new TempProject();
        $plan = Deptrac::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/deptrac', $plan->command[0]);
        $this->assertSame('analyse', $plan->command[1]);
        $this->assertContains('--formatter=json', $plan->command);
        $this->assertInstanceOf(DeptracParser::class, $plan->parser);
    }

    public function test_it_uses_a_present_depfile(): void
    {
        $project = new TempProject();
        $project->file('deptrac.yaml', "deptrac:\n  paths: [src]\n");

        $plan = Deptrac::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/deptrac.yaml', $this->optionValue($plan->command, '--config-file='));
    }

    public function test_without_a_depfile_no_config_file_is_passed(): void
    {
        $plan = Deptrac::make()->plan($this->context(new TempProject()), Mode::Check);

        foreach ($plan->command as $arg) {
            $this->assertStringNotContainsString('--config-file=', $arg);
        }
    }

    public function test_before_commands_are_threaded_into_the_plan(): void
    {
        $plan = Deptrac::make()
            ->before(['composer', 'install'])
            ->plan($this->context(new TempProject()), Mode::Check);

        $this->assertSame([['composer', 'install']], $plan->before);
    }
}
