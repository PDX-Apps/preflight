<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Steps\ComposerAudit;
use PdxApps\Preflight\Steps\Pint;
use PdxApps\Preflight\Steps\StepRegistry;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StepRegistry::class)]
final class StepRegistryTest extends TestCase
{
    private function context(TempProject $project): Context
    {
        return new Context($project->root, TargetSet::wholeProject());
    }

    public function test_the_default_set_lists_pint(): void
    {
        $classes = StepRegistry::defaults();

        $this->assertContains(Pint::class, $classes);
    }

    public function test_the_default_set_lists_composer_audit(): void
    {
        $this->assertContains(ComposerAudit::class, StepRegistry::defaults());
    }

    public function test_composer_audit_is_installed_even_without_any_vendor_bin(): void
    {
        // composer is a system tool, so the audit step is available in every project.
        $installed = (new StepRegistry())->installed($this->context(new TempProject()));

        $names = array_map(static fn ($s) => $s->name(), $installed);
        $this->assertContains('composer-audit', $names);
    }

    public function test_every_default_class_is_instantiable_as_a_step(): void
    {
        foreach (StepRegistry::defaults() as $class) {
            $step = $class::make();
            $this->assertNotSame('', $step->name());
        }
    }

    public function test_installed_returns_only_steps_whose_tool_is_available(): void
    {
        $project = new TempProject();
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');

        $installed = (new StepRegistry())->installed($this->context($project));

        $names = array_map(static fn ($s) => $s->name(), $installed);
        $this->assertContains('pint', $names);
    }

    public function test_installed_excludes_steps_whose_tool_is_missing(): void
    {
        $project = new TempProject(); // no vendor/bin/pint

        $installed = (new StepRegistry())->installed($this->context($project));

        $names = array_map(static fn ($s) => $s->name(), $installed);
        $this->assertNotContains('pint', $names);
    }
}
