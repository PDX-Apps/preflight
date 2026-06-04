<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Config\Configuration;
use PdxApps\Preflight\Diagnostics\Diagnostics;
use PdxApps\Preflight\Steps\Pint;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Diagnostics::class)]
final class DiagnosticsTest extends TestCase
{
    private function gather(TempProject $project, Configuration $config): Diagnostics
    {
        return Diagnostics::gather($config, $project->root);
    }

    public function test_it_reports_the_project_root_and_config_presence(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');

        $diagnostics = $this->gather($project, new Configuration());

        $this->assertSame($project->root, $diagnostics->projectRoot);
        $this->assertFalse($diagnostics->hasConfigFile);
    }

    public function test_it_detects_a_preflight_config_file(): void
    {
        $project = new TempProject();
        $project->file('preflight.php', '<?php return PdxApps\\Preflight\\Preflight::configure();');

        $this->assertTrue($this->gather($project, new Configuration())->hasConfigFile);
    }

    public function test_a_step_with_its_tool_installed_is_reported_runnable(): void
    {
        $project = new TempProject();
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');
        $project->file('pint.json', '{}');

        $steps = $this->gather($project, new Configuration())->steps;

        $pint = $this->stepNamed($steps, 'pint');
        $this->assertTrue($pint->toolInstalled);
        $this->assertTrue($pint->configFound);
        $this->assertTrue($pint->willRun);
    }

    public function test_a_step_with_a_missing_tool_is_not_runnable_and_shows_its_hint(): void
    {
        $project = new TempProject(); // no vendor/bin/pint

        $steps = $this->gather($project, new Configuration())->steps;
        $pint = $this->stepNamed($steps, 'pint');

        $this->assertFalse($pint->toolInstalled);
        $this->assertFalse($pint->willRun);
        $this->assertSame('laravel/pint', $pint->requireHint);
    }

    public function test_config_not_found_is_reported_without_blocking_the_run(): void
    {
        $project = new TempProject();
        $project->file('vendor/bin/pint', '#!/usr/bin/env php'); // tool present, but no pint.json

        $pint = $this->stepNamed($this->gather($project, new Configuration())->steps, 'pint');

        $this->assertTrue($pint->toolInstalled);
        $this->assertFalse($pint->configFound);
        $this->assertTrue($pint->willRun, 'a missing optional config does not stop the step');
    }

    public function test_an_explicit_step_list_drives_which_steps_are_reported(): void
    {
        $project = new TempProject();
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');

        $diagnostics = $this->gather($project, new Configuration(steps: [Pint::make()]));

        $this->assertCount(1, $diagnostics->steps);
        $this->assertSame('pint', $diagnostics->steps[0]->name);
    }

    public function test_it_serializes_to_a_stable_array_shape(): void
    {
        $project = new TempProject();
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');

        $array = $this->gather($project, new Configuration(steps: [Pint::make()]))->toArray();

        $this->assertArrayHasKey('projectRoot', $array);
        $this->assertArrayHasKey('hasConfigFile', $array);
        $this->assertArrayHasKey('steps', $array);
        $this->assertSame('pint', $array['steps'][0]['name']);
        $this->assertArrayHasKey('toolInstalled', $array['steps'][0]);
        $this->assertArrayHasKey('willRun', $array['steps'][0]);
    }

    /**
     * @param  list<\PdxApps\Preflight\Diagnostics\StepDiagnostic>  $steps
     */
    private function stepNamed(array $steps, string $name): \PdxApps\Preflight\Diagnostics\StepDiagnostic
    {
        foreach ($steps as $step) {
            if ($step->name === $name) {
                return $step;
            }
        }

        $this->fail("No step diagnostic named {$name}.");
    }
}
