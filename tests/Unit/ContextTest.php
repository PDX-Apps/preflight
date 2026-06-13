<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Support\CoverageDriver;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Context::class)]
final class ContextTest extends TestCase
{
    private function context(string $root, ?TargetSet $targets = null): Context
    {
        return new Context($root, $targets ?? TargetSet::wholeProject());
    }

    public function test_it_exposes_the_project_root(): void
    {
        $project = new TempProject();

        $this->assertSame($project->root, $this->context($project->root)->projectRoot());
    }

    public function test_it_exposes_the_injected_coverage_driver_defaulting_to_none(): void
    {
        $project = new TempProject();

        $this->assertNull($this->context($project->root)->coverageDriver());
        $this->assertSame(
            CoverageDriver::Pcov,
            (new Context($project->root, TargetSet::wholeProject(), CoverageDriver::Pcov))->coverageDriver(),
        );
    }

    public function test_it_resolves_a_config_reference_against_the_root(): void
    {
        $project = new TempProject();
        $context = $this->context($project->root);

        $this->assertSame($project->root . '/phpstan.neon', $context->configPath('phpstan.neon'));
        $this->assertNull($context->configPath(null));
    }

    public function test_path_exists_checks_files_and_directories_under_the_root(): void
    {
        $project = new TempProject();
        $project->dir('app');
        $project->file('composer.json', '{}');
        $context = $this->context($project->root);

        $this->assertTrue($context->pathExists('app'));
        $this->assertTrue($context->pathExists('composer.json'));
        $this->assertFalse($context->pathExists('src'));
    }

    public function test_changed_lines_default_to_none(): void
    {
        $this->assertSame([], $this->context((new TempProject())->root)->changedLines());
    }

    public function test_changed_lines_can_be_given_as_an_array(): void
    {
        $changed = ['src/Foo.php' => [[10, 12]]];
        $context = new Context((new TempProject())->root, TargetSet::wholeProject(), null, $changed);

        $this->assertSame($changed, $context->changedLines());
    }

    public function test_changed_lines_from_a_closure_are_computed_on_access(): void
    {
        $calls = 0;
        $closure = function () use (&$calls): array {
            $calls++;

            return ['src/Foo.php' => [[1, 1]]];
        };
        $context = new Context((new TempProject())->root, TargetSet::wholeProject(), null, $closure);

        $this->assertSame(0, $calls, 'the closure is not run until changedLines() is called');
        $this->assertSame(['src/Foo.php' => [[1, 1]]], $context->changedLines());
        $this->assertSame(1, $calls);
    }

    public function test_it_resolves_a_vendor_bin_tool_path(): void
    {
        $project = new TempProject();
        $context = $this->context($project->root);

        $this->assertSame(
            $project->root . '/vendor/bin/pint',
            $context->toolPath(Tool::vendorBin('pint')),
        );
    }

    public function test_a_vendor_bin_tool_is_available_only_when_its_binary_exists(): void
    {
        $project = new TempProject();
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');
        $context = $this->context($project->root);

        $this->assertTrue($context->toolAvailable(Tool::vendorBin('pint')));
        $this->assertFalse($context->toolAvailable(Tool::vendorBin('phpstan')));
    }

    public function test_a_system_tool_is_always_available(): void
    {
        $context = $this->context((new TempProject())->root);

        $this->assertTrue($context->toolAvailable(Tool::system('composer')));
    }

    public function test_it_exposes_the_target_set_and_path_args_for_a_targeting(): void
    {
        $targets = TargetSet::narrowed([
            Target::file('app/Foo.php'),
            Target::directory('app/Http'),
        ]);
        $context = $this->context((new TempProject())->root, $targets);

        $this->assertSame($targets, $context->targets());
        $this->assertSame(['app/Foo.php', 'app/Http'], $context->pathsFor(Targeting::Files));
        $this->assertTrue($context->isNarrowed());
    }
}
