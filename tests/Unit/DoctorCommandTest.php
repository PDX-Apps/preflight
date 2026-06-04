<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Console\DoctorCommand;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DoctorCommand::class)]
final class DoctorCommandTest extends TestCase
{
    private function tester(TempProject $project): CommandTester
    {
        return new CommandTester(new DoctorCommand($project->root));
    }

    public function test_human_output_reports_an_installed_step_as_ready(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');

        $tester = $this->tester($project);
        $exit = $tester->execute([], ['decorated' => false]);

        $this->assertSame(0, $exit);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Pint', $display);
        $this->assertStringContainsString($project->root, $display);
    }

    public function test_human_output_shows_the_install_hint_for_a_missing_tool(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}'); // no vendor/bin/pint

        $tester = $this->tester($project);
        $tester->execute([], ['decorated' => false]);

        $this->assertStringContainsString('laravel/pint', $tester->getDisplay());
    }

    public function test_json_output_is_a_parseable_diagnostics_document(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');

        $tester = $this->tester($project);
        $tester->execute(['--format' => 'json'], ['decorated' => false]);

        $decoded = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('projectRoot', $decoded);
        $this->assertArrayHasKey('steps', $decoded);
        $this->assertSame('pint', $decoded['steps'][0]['name']);
        $this->assertTrue($decoded['steps'][0]['toolInstalled']);
    }

    public function test_doctor_always_exits_zero_it_is_a_report_not_a_check(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}'); // nothing installed

        $exit = $this->tester($project)->execute([], ['decorated' => false]);

        $this->assertSame(0, $exit);
    }
}
