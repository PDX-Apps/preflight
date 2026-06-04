<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\ComposerAuditParser;
use PdxApps\Preflight\Steps\ComposerAudit;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerAudit::class)]
final class ComposerAuditStepTest extends TestCase
{
    private function context(TempProject $project): Context
    {
        return new Context($project->root, TargetSet::wholeProject());
    }

    public function test_its_identity(): void
    {
        $step = ComposerAudit::make();

        $this->assertSame('composer-audit', $step->name());
        $this->assertSame('Composer Audit', $step->label());
        $this->assertSame('composer', $step->tool()?->binary);
        $this->assertFalse($step->tool()?->inVendorBin, 'composer is a system tool, not a vendor-bin one');
        $this->assertSame([Mode::Check], $step->modes());
        $this->assertSame(Targeting::Whole, $step->targeting());
        $this->assertNull($step->defaultConfig());
    }

    public function test_it_runs_composer_audit_as_json_against_the_lock_file(): void
    {
        $project = new TempProject();

        $plan = ComposerAudit::make()->plan($this->context($project), Mode::Check);

        $this->assertSame('composer', $plan->command[0]);
        $this->assertSame('audit', $plan->command[1]);
        $this->assertContains('--format=json', $plan->command);
        $this->assertContains('--locked', $plan->command);
        $this->assertInstanceOf(ComposerAuditParser::class, $plan->parser);
    }

    public function test_it_judges_by_exit_code_not_findings(): void
    {
        // composer audit's exit code is reliable (1=vulns, 2=abandoned, 3=both) and
        // --abandoned=report excludes abandoned from it, so abandoned warnings must NOT
        // fail the step. Findings-judging would wrongly fail on those warnings.
        $plan = ComposerAudit::make()->plan($this->context(new TempProject()), Mode::Check);

        $this->assertFalse($plan->judgesByFindings);
    }

    public function test_abandoned_packages_are_reported_not_failed_by_default(): void
    {
        $plan = ComposerAudit::make()->plan($this->context(new TempProject()), Mode::Check);

        $this->assertContains('--abandoned=report', $plan->command);
    }

    public function test_the_abandoned_handling_is_configurable(): void
    {
        $plan = ComposerAudit::make()->abandoned('ignore')->plan($this->context(new TempProject()), Mode::Check);

        $this->assertContains('--abandoned=ignore', $plan->command);
        $this->assertNotContains('--abandoned=report', $plan->command);
    }

    public function test_auditing_installed_packages_instead_of_the_lock_is_possible(): void
    {
        $plan = ComposerAudit::make()->locked(false)->plan($this->context(new TempProject()), Mode::Check);

        $this->assertNotContains('--locked', $plan->command);
    }

    public function test_before_commands_are_threaded_into_the_plan(): void
    {
        $plan = ComposerAudit::make()
            ->before(['composer', 'install'])
            ->plan($this->context(new TempProject()), Mode::Check);

        $this->assertSame([['composer', 'install']], $plan->before);
    }
}
