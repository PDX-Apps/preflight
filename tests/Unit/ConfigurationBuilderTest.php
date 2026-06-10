<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Config\ConfigurationBuilder;
use PdxApps\Preflight\OutputFormat;
use PdxApps\Preflight\Tests\Unit\Fixtures\ConfigurableStep;
use PdxApps\Preflight\Tests\Unit\Fixtures\SecondConfigurableStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationBuilder::class)]
final class ConfigurationBuilderTest extends TestCase
{
    public function test_it_builds_a_configuration_with_defaults(): void
    {
        $config = (new ConfigurationBuilder())->build();

        $this->assertNull($config->paths);
        $this->assertNull($config->steps);
        $this->assertFalse($config->failFast);
    }

    public function test_with_paths_sets_paths(): void
    {
        $config = (new ConfigurationBuilder())->withPaths(['app', 'src'])->build();

        $this->assertSame(['app', 'src'], $config->paths);
    }

    public function test_exclude_accumulates_paths_across_calls(): void
    {
        $config = (new ConfigurationBuilder())
            ->exclude(['app/Providers'])
            ->exclude(['app/Actions/Fortify', 'database'])
            ->build();

        $this->assertSame(['app/Providers', 'app/Actions/Fortify', 'database'], $config->exclude);
    }

    public function test_only_and_skip_record_step_name_selections(): void
    {
        $config = (new ConfigurationBuilder())
            ->only(['phpstan', 'test'])
            ->withSkip(['phpmd'])
            ->build();

        $this->assertSame(['phpstan', 'test'], $config->only);
        $this->assertSame(['phpmd'], $config->skip);
    }

    public function test_with_steps_accepts_class_strings_and_normalizes_them_to_instances(): void
    {
        $config = (new ConfigurationBuilder())
            ->withSteps([ConfigurableStep::class, SecondConfigurableStep::class])
            ->build();

        $this->assertCount(2, $config->steps);
        $this->assertInstanceOf(ConfigurableStep::class, $config->steps[0]);
        $this->assertInstanceOf(SecondConfigurableStep::class, $config->steps[1]);
    }

    public function test_with_steps_accepts_already_configured_instances(): void
    {
        $configured = ConfigurableStep::make()->config('phpstan.neon');
        $config = (new ConfigurationBuilder())
            ->withSteps([$configured, SecondConfigurableStep::class])
            ->build();

        $this->assertSame($configured, $config->steps[0]);
        $this->assertSame('phpstan.neon', $config->steps[0]->configReference());
    }

    public function test_add_steps_normalizes_class_strings_and_records_them_as_added(): void
    {
        $config = (new ConfigurationBuilder())
            ->addSteps([ConfigurableStep::class, SecondConfigurableStep::class])
            ->build();

        $this->assertNull($config->steps); // auto-detection stays on
        $this->assertCount(2, $config->added);
        $this->assertInstanceOf(ConfigurableStep::class, $config->added[0]);
        $this->assertInstanceOf(SecondConfigurableStep::class, $config->added[1]);
    }

    public function test_add_steps_accepts_instances_and_accumulates_across_calls(): void
    {
        $configured = ConfigurableStep::make()->config('phpstan.neon');
        $config = (new ConfigurationBuilder())
            ->addSteps([$configured])
            ->addSteps([SecondConfigurableStep::class])
            ->build();

        $this->assertSame($configured, $config->added[0]);
        $this->assertInstanceOf(SecondConfigurableStep::class, $config->added[1]);
    }

    public function test_tune_records_an_overlay_keyed_by_class(): void
    {
        $tuned = ConfigurableStep::make()->config('tuned.neon');
        $config = (new ConfigurationBuilder())->tune($tuned)->build();

        $this->assertSame([ConfigurableStep::class => $tuned], $config->tunes);
    }

    public function test_without_records_a_removal_by_class(): void
    {
        $config = (new ConfigurationBuilder())->without(ConfigurableStep::class)->build();

        $this->assertSame([ConfigurableStep::class], $config->without);
    }

    public function test_skip_and_fail_fast_and_format(): void
    {
        $config = (new ConfigurationBuilder())
            ->withSkip(['vendor'])
            ->failFast()
            ->defaultFormat('json')
            ->build();

        $this->assertSame(['vendor'], $config->skip);
        $this->assertTrue($config->failFast);
        $this->assertSame(OutputFormat::Json, $config->defaultFormat);
    }

    public function test_fix_and_dirty_are_off_by_default(): void
    {
        $config = (new ConfigurationBuilder())->build();

        $this->assertFalse($config->fixByDefault);
        $this->assertFalse($config->dirtyByDefault);
    }

    public function test_fix_by_default_can_be_opted_into(): void
    {
        $this->assertTrue((new ConfigurationBuilder())->fixByDefault()->build()->fixByDefault);
        $this->assertFalse((new ConfigurationBuilder())->fixByDefault(false)->build()->fixByDefault);
    }

    public function test_dirty_by_default_can_be_opted_into(): void
    {
        $this->assertTrue((new ConfigurationBuilder())->dirtyByDefault()->build()->dirtyByDefault);
        $this->assertFalse((new ConfigurationBuilder())->dirtyByDefault(false)->build()->dirtyByDefault);
    }

    public function test_for_agents_flips_the_agent_friendly_defaults_at_once(): void
    {
        $config = (new ConfigurationBuilder())->forAgents()->build();

        // The autonomous-agent preset: scope to changes, auto-fix what's fixable, and
        // emit the agent format — so a bare `preflight` does the right thing for an agent.
        $this->assertTrue($config->dirtyByDefault);
        $this->assertTrue($config->fixByDefault);
        $this->assertSame(OutputFormat::Agent, $config->defaultFormat);
    }

    public function test_for_agents_can_keep_dirty_scoping_off(): void
    {
        $config = (new ConfigurationBuilder())->forAgents(dirty: false)->build();

        // Still auto-fixes and emits the agent format, but checks the whole project
        // rather than only working-tree changes.
        $this->assertFalse($config->dirtyByDefault);
        $this->assertTrue($config->fixByDefault);
        $this->assertSame(OutputFormat::Agent, $config->defaultFormat);
    }
}
