<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\RectorParser;
use PdxApps\Preflight\Steps\Rector;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rector::class)]
final class RectorStepTest extends TestCase
{
    private function context(TempProject $project, ?TargetSet $targets = null): Context
    {
        return new Context($project->root, $targets ?? TargetSet::wholeProject());
    }

    public function test_its_identity(): void
    {
        $step = Rector::make();

        $this->assertSame('rector', $step->name());
        $this->assertSame('Rector', $step->label());
        $this->assertSame('rector', $step->tool()?->binary);
        $this->assertSame('rector/rector', $step->tool()?->requireHint);
        $this->assertSame([Mode::Check, Mode::Fix], $step->modes());
        $this->assertSame(Targeting::Files, $step->targeting());
        $this->assertSame('rector.php', $step->defaultConfig());
    }

    public function test_check_mode_runs_process_dry_run_with_json(): void
    {
        $project = new TempProject();

        $plan = Rector::make()->plan($this->context($project), Mode::Check);

        $this->assertSame($project->root . '/vendor/bin/rector', $plan->command[0]);
        $this->assertContains('process', $plan->command);
        $this->assertContains('--dry-run', $plan->command);
        $this->assertContains('--output-format=json', $plan->command);
        $this->assertContains('--no-progress-bar', $plan->command);
        $this->assertInstanceOf(RectorParser::class, $plan->parser);
    }

    public function test_fix_mode_omits_the_dry_run_flag(): void
    {
        $project = new TempProject();

        $plan = Rector::make()->plan($this->context($project), Mode::Fix);

        $this->assertNotContains('--dry-run', $plan->command);
        $this->assertContains('process', $plan->command);
    }

    public function test_it_uses_the_root_config_when_present(): void
    {
        $project = new TempProject();
        $project->file('rector.php', '<?php');

        $plan = Rector::make()->plan($this->context($project), Mode::Check);

        $this->assertContains('--config=' . $project->root . '/rector.php', $plan->command);
    }

    public function test_a_narrowed_run_passes_target_files_as_path_arguments(): void
    {
        $project = new TempProject();
        $targets = TargetSet::narrowed([Target::file('src/A.php'), Target::file('src/B.php')]);

        $plan = Rector::make()->plan($this->context($project, $targets), Mode::Check);

        $this->assertContains('src/A.php', $plan->command);
        $this->assertContains('src/B.php', $plan->command);
    }
}
