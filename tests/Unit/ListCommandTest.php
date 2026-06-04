<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Console\ListStepsCommand;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ListStepsCommand::class)]
final class ListCommandTest extends TestCase
{
    private function tester(TempProject $project): CommandTester
    {
        return new CommandTester(new ListStepsCommand($project->root));
    }

    public function test_it_lists_each_step_with_its_name(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');

        $tester = $this->tester($project);
        $exit = $tester->execute([], ['decorated' => false]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('pint', $tester->getDisplay());
    }

    public function test_json_format_lists_steps_with_availability(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');

        $tester = $this->tester($project);
        $tester->execute(['--format' => 'json'], ['decorated' => false]);

        $decoded = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('pint', $decoded[0]['name']);
        $this->assertTrue($decoded[0]['willRun']);
    }
}
