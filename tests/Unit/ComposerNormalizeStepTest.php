<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\ComposerNormalizeParser;
use PdxApps\Preflight\Steps\ComposerNormalize;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerNormalize::class)]
final class ComposerNormalizeStepTest extends TestCase
{
    private function context(TempProject $project): Context
    {
        return new Context($project->root, TargetSet::wholeProject());
    }

    public function test_its_identity(): void
    {
        $step = ComposerNormalize::make();

        $this->assertSame('composer-normalize', $step->name());
        $this->assertSame('Composer Normalize', $step->label());
        $this->assertSame('composer', $step->tool()?->binary);
        $this->assertSame('ergebnis/composer-normalize', $step->tool()?->pluginPackage);
        $this->assertSame([Mode::Check, Mode::Fix], $step->modes());
        $this->assertSame(Targeting::Whole, $step->targeting());
        $this->assertNull($step->defaultConfig());
    }

    public function test_check_mode_runs_a_dry_run(): void
    {
        $plan = ComposerNormalize::make()->plan($this->context(new TempProject()), Mode::Check);

        $this->assertSame('composer', $plan->command[0]);
        $this->assertSame('normalize', $plan->command[1]);
        $this->assertContains('--dry-run', $plan->command);
        $this->assertInstanceOf(ComposerNormalizeParser::class, $plan->parser);
    }

    public function test_fix_mode_does_not_dry_run(): void
    {
        $plan = ComposerNormalize::make()->plan($this->context(new TempProject()), Mode::Fix);

        $this->assertNotContains('--dry-run', $plan->command);
    }

    public function test_it_does_not_churn_the_lock_file(): void
    {
        $plan = ComposerNormalize::make()->plan($this->context(new TempProject()), Mode::Fix);

        $this->assertContains('--no-update-lock', $plan->command);
    }

    public function test_before_commands_are_threaded_into_the_plan(): void
    {
        $plan = ComposerNormalize::make()
            ->before(['composer', 'install'])
            ->plan($this->context(new TempProject()), Mode::Check);

        $this->assertSame([['composer', 'install']], $plan->before);
    }
}
