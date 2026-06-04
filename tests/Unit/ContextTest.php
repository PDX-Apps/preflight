<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
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

    public function test_it_resolves_a_config_reference_against_the_root(): void
    {
        $project = new TempProject();
        $context = $this->context($project->root);

        $this->assertSame($project->root . '/phpstan.neon', $context->configPath('phpstan.neon'));
        $this->assertNull($context->configPath(null));
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

    public function test_a_composer_plugin_is_available_only_when_its_package_is_installed(): void
    {
        $project = new TempProject();
        $context = $this->context($project->root);
        $plugin = Tool::composerPlugin('ergebnis/composer-normalize');

        $this->assertFalse($context->toolAvailable($plugin));

        $project->file('vendor/ergebnis/composer-normalize/composer.json', '{}');
        $this->assertTrue($context->toolAvailable($plugin));
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
