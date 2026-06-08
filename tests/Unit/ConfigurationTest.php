<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Config\Configuration;
use PdxApps\Preflight\Config\ModuleConfig;
use PdxApps\Preflight\OutputFormat;
use PdxApps\Preflight\Tests\Unit\Fixtures\ConfigurableStep;
use PdxApps\Preflight\Tests\Unit\Fixtures\SecondConfigurableStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function test_it_has_sensible_defaults(): void
    {
        $config = new Configuration();

        $this->assertNull($config->paths);
        $this->assertInstanceOf(ModuleConfig::class, $config->modules);
        $this->assertSame([], $config->skip);
        $this->assertNull($config->steps);
        $this->assertSame([], $config->added);
        $this->assertSame([], $config->tunes);
        $this->assertSame([], $config->without);
        $this->assertFalse($config->failFast);
        $this->assertSame(OutputFormat::Auto, $config->defaultFormat);
    }

    public function test_null_steps_means_auto_detect_a_non_null_list_means_explicit(): void
    {
        $this->assertFalse((new Configuration())->hasExplicitSteps());
        $this->assertTrue((new Configuration(steps: []))->hasExplicitSteps());
        $this->assertTrue((new Configuration(steps: [ConfigurableStep::make()]))->hasExplicitSteps());
    }

    public function test_modules_can_be_configured_and_disabled(): void
    {
        $configured = new Configuration(modules: new ModuleConfig('Packages', 'src', 'test'));
        $this->assertSame('Packages', $configured->modules->dir);
        $this->assertTrue($configured->usesModules());

        $none = new Configuration(modules: null);
        $this->assertFalse($none->usesModules());
        $this->assertNull($none->modules);
    }

    public function test_with_fail_fast_returns_a_copy_with_fail_fast_flipped_and_the_rest_intact(): void
    {
        $base = new Configuration(
            steps: [ConfigurableStep::make()],
            skip: ['x'],
            failFast: false,
            defaultFormat: OutputFormat::Github,
            paths: ['src'],
            fixByDefault: true,
            dirtyByDefault: true,
            exclude: ['app/Providers'],
        );

        $flipped = $base->withFailFast(true);

        $this->assertFalse($base->failFast, 'the original is untouched');
        $this->assertTrue($flipped->failFast);
        // Everything else is carried over unchanged.
        $this->assertSame($base->steps, $flipped->steps);
        $this->assertSame(['x'], $flipped->skip);
        $this->assertSame(OutputFormat::Github, $flipped->defaultFormat);
        $this->assertSame(['src'], $flipped->paths);
        $this->assertTrue($flipped->fixByDefault);
        $this->assertTrue($flipped->dirtyByDefault);
        $this->assertSame(['app/Providers'], $flipped->exclude);
    }

    public function test_resolve_steps_uses_the_explicit_list_when_present(): void
    {
        $a = ConfigurableStep::make();
        $b = SecondConfigurableStep::make();
        $config = new Configuration(steps: [$a, $b]);

        $this->assertSame([$a, $b], $config->resolveSteps(autoSteps: []));
    }

    public function test_resolve_steps_falls_back_to_auto_steps_when_no_explicit_list(): void
    {
        $auto = ConfigurableStep::make();
        $config = new Configuration();

        $this->assertSame([$auto], $config->resolveSteps(autoSteps: [$auto]));
    }

    public function test_added_steps_are_appended_after_the_auto_detected_base(): void
    {
        $auto = ConfigurableStep::make();
        $extra = SecondConfigurableStep::make();
        $config = new Configuration(added: [$extra]);

        $this->assertSame([$auto, $extra], $config->resolveSteps(autoSteps: [$auto]));
    }

    public function test_added_steps_are_appended_after_an_explicit_set(): void
    {
        $a = ConfigurableStep::make();
        $extra = SecondConfigurableStep::make();
        $config = new Configuration(steps: [$a], added: [$extra]);

        $this->assertSame([$a, $extra], $config->resolveSteps(autoSteps: []));
    }

    public function test_an_added_step_already_in_the_base_keeps_its_position_and_instance(): void
    {
        $base = [ConfigurableStep::make(), SecondConfigurableStep::make()];
        $config = new Configuration(added: [ConfigurableStep::make()]);

        $resolved = $config->resolveSteps(autoSteps: $base);

        $this->assertSame($base, $resolved);
    }

    public function test_without_removes_an_added_step(): void
    {
        $config = new Configuration(
            added: [SecondConfigurableStep::make()],
            without: [SecondConfigurableStep::class],
        );

        $resolved = $config->resolveSteps(autoSteps: [ConfigurableStep::make()]);

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(ConfigurableStep::class, $resolved[0]);
    }

    public function test_a_tune_reconfigures_an_added_step(): void
    {
        $tuned = SecondConfigurableStep::make()->config('tuned.neon');
        $config = new Configuration(
            added: [SecondConfigurableStep::make()],
            tunes: [SecondConfigurableStep::class => $tuned],
        );

        $resolved = $config->resolveSteps(autoSteps: [ConfigurableStep::make()]);

        $this->assertSame($tuned, $resolved[1]);
    }

    public function test_without_removes_a_step_by_class_from_the_resolved_set(): void
    {
        $auto = [ConfigurableStep::make(), SecondConfigurableStep::make()];
        $config = new Configuration(without: [SecondConfigurableStep::class]);

        $resolved = $config->resolveSteps(autoSteps: $auto);

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(ConfigurableStep::class, $resolved[0]);
    }

    public function test_tune_replaces_a_matching_step_in_place_preserving_order(): void
    {
        $auto = [ConfigurableStep::make(), SecondConfigurableStep::make()];
        $tuned = ConfigurableStep::make()->config('tuned.neon');
        $config = new Configuration(tunes: [ConfigurableStep::class => $tuned]);

        $resolved = $config->resolveSteps(autoSteps: $auto);

        $this->assertSame($tuned, $resolved[0]);
        $this->assertInstanceOf(SecondConfigurableStep::class, $resolved[1]);
    }

    public function test_tune_for_a_class_absent_from_the_base_appends_it(): void
    {
        $auto = [SecondConfigurableStep::make()];
        $tuned = ConfigurableStep::make()->config('added.neon');
        $config = new Configuration(tunes: [ConfigurableStep::class => $tuned]);

        $resolved = $config->resolveSteps(autoSteps: $auto);

        $this->assertCount(2, $resolved);
        $this->assertInstanceOf(SecondConfigurableStep::class, $resolved[0]);
        $this->assertSame($tuned, $resolved[1]);
    }

    public function test_without_wins_over_a_tune_for_the_same_class(): void
    {
        $tuned = ConfigurableStep::make()->config('x');
        $config = new Configuration(
            tunes: [ConfigurableStep::class => $tuned],
            without: [ConfigurableStep::class],
        );

        $this->assertSame([], $config->resolveSteps(autoSteps: [ConfigurableStep::make()]));
    }
}
