<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PsalmParser;
use PdxApps\Preflight\Steps\Psalm;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Psalm::class)]
final class PsalmStepTest extends TestCase
{
    private function context(TempProject $project, ?TargetSet $targets = null): Context
    {
        return new Context($project->root, $targets ?? TargetSet::wholeProject());
    }

    public function test_its_identity(): void
    {
        $step = Psalm::make();

        $this->assertSame('psalm', $step->name());
        $this->assertSame('Psalm', $step->label());
        $this->assertSame('psalm', $step->tool()?->binary);
        $this->assertSame('vimeo/psalm', $step->tool()?->requireHint);
        $this->assertSame([Mode::Check], $step->modes());
        $this->assertSame(Targeting::Files, $step->targeting());
        $this->assertSame('psalm.xml', $step->defaultConfig());
    }

    public function test_it_analyses_with_json_output_and_no_progress(): void
    {
        $project = new TempProject();

        $plan = Psalm::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/psalm', $plan->command[0]);
        $this->assertContains('--output-format=json', $plan->command);
        $this->assertContains('--no-progress', $plan->command);
        $this->assertInstanceOf(PsalmParser::class, $plan->parser);
    }

    public function test_it_uses_the_root_config_when_present(): void
    {
        $project = new TempProject();
        $project->file('psalm.xml', '<?xml version="1.0"?><psalm/>');

        $plan = Psalm::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--config=' . $project->root . '/psalm.xml', $plan->command);
    }

    public function test_it_falls_back_to_psalm_xml_dist(): void
    {
        $project = new TempProject();
        $project->file('psalm.xml.dist', '<?xml version="1.0"?><psalm/>');

        $plan = Psalm::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--config=' . $project->root . '/psalm.xml.dist', $plan->command);
    }

    public function test_threads_are_added_only_when_configured(): void
    {
        $project = new TempProject();

        $default = Psalm::make()->plan($this->context($project), Mode::Check);
        foreach ($default->command as $arg) {
            $this->assertStringNotContainsString('--threads=', $arg);
        }

        $threaded = Psalm::make()->threads(4)->plan($this->context($project), Mode::Check);
        $this->assertContains('--threads=4', $threaded->command);
    }

    public function test_no_cache_can_be_enabled(): void
    {
        $project = new TempProject();

        $plan = Psalm::make()->noCache()->plan($this->context($project), Mode::Check);

        $this->assertContains('--no-cache', $plan->command);
    }

    public function test_a_narrowed_run_passes_target_files(): void
    {
        $project = new TempProject();
        $targets = TargetSet::narrowed([Target::file('src/A.php')]);

        $plan = Psalm::make()->plan($this->context($project, $targets), Mode::Check);

        $this->assertContains('src/A.php', $plan->command);
    }
}
