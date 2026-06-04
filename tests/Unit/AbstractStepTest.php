<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Steps\AbstractStep;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Context;
use PdxApps\Preflight\Tests\Unit\Fixtures\ConfigurableStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractStep::class)]
final class AbstractStepTest extends TestCase
{
    public function test_make_returns_an_instance_of_the_concrete_step(): void
    {
        $step = ConfigurableStep::make();

        $this->assertInstanceOf(ConfigurableStep::class, $step);
    }

    public function test_name_derives_from_the_class_basename(): void
    {
        $this->assertSame('configurable-step', ConfigurableStep::make()->name());
    }

    public function test_a_fresh_step_has_default_settings(): void
    {
        $step = ConfigurableStep::make();

        $this->assertNull($step->configReference());
        $this->assertSame([], $step->beforeCommands());
        $this->assertSame([], $step->extraArgs());
    }

    public function test_with_methods_return_a_new_instance_and_leave_the_original_untouched(): void
    {
        $original = ConfigurableStep::make();
        $configured = $original->config('phpstan.neon');

        $this->assertNotSame($original, $configured);
        $this->assertNull($original->configReference());
        $this->assertSame('phpstan.neon', $configured->configReference());
    }

    public function test_settings_are_chainable_and_accumulate(): void
    {
        $step = ConfigurableStep::make()
            ->config('a.neon')
            ->before(['php', 'artisan', 'config:clear'])
            ->before(['echo', 'hi'])
            ->args(['--foo'])
            ->args(['--bar']);

        $this->assertSame('a.neon', $step->configReference());
        $this->assertSame([['php', 'artisan', 'config:clear'], ['echo', 'hi']], $step->beforeCommands());
        $this->assertSame(['--foo', '--bar'], $step->extraArgs());
    }

    public function test_subclass_specific_setters_clone_correctly(): void
    {
        $original = ConfigurableStep::make();
        $leveled = $original->level(9);

        $this->assertSame(1, $original->getLevel());
        $this->assertSame(9, $leveled->getLevel());
    }

    public function test_plan_reads_the_steps_own_resolved_settings(): void
    {
        $step = ConfigurableStep::make()->config('custom.neon')->level(5)->args(['--xtra']);
        $context = new Context('/project', TargetSet::wholeProject());

        $plan = $step->plan($context, Mode::Check);

        $this->assertSame(
            ['configurable', '--config=/project/custom.neon', '--level=5', '--xtra'],
            $plan->command,
        );
    }
}
