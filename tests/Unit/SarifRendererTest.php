<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Render\SarifRenderer;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(SarifRenderer::class)]
final class SarifRendererTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function render(RunResult $result): array
    {
        $output = new BufferedOutput();
        (new SarifRenderer())->render($result, $output);

        return (array) json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_it_emits_a_valid_sarif_envelope(): void
    {
        $sarif = $this->render(new RunResult([StepResult::passed('pint', 'Pint', durationSeconds: 0.5)]));

        $this->assertSame('2.1.0', $sarif['version']);
        $this->assertArrayHasKey('$schema', $sarif);
        $this->assertIsArray($sarif['runs']);
    }

    public function test_a_clean_run_still_emits_a_run_per_executed_tool_with_empty_results(): void
    {
        // GitHub's SARIF upload rejects a document with zero runs, so a clean project must
        // still produce a run per tool that ran — each with empty results.
        $sarif = $this->render(new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 0.5),
            StepResult::passed('phpstan', 'PHPStan', durationSeconds: 1.0),
        ]));

        $drivers = array_map(static fn (array $run): string => $run['tool']['driver']['name'], $sarif['runs']);
        $this->assertSame(['pint', 'phpstan'], $drivers);
        $this->assertSame([], $sarif['runs'][0]['results']);
        $this->assertSame([], $sarif['runs'][1]['results']);
    }

    public function test_skipped_and_missing_tool_steps_produce_no_run(): void
    {
        $sarif = $this->render(new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 0.5),
            StepResult::skipped('composer-audit', 'Composer Audit', 'narrowed run'),
            StepResult::missingTool('phpstan', 'PHPStan', 'not installed'),
        ]));

        $drivers = array_map(static fn (array $run): string => $run['tool']['driver']['name'], $sarif['runs']);
        $this->assertSame(['pint'], $drivers, 'only the step that actually ran becomes a run');
    }

    public function test_a_finding_becomes_a_result_with_rule_level_message_and_location(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'Undefined variable $x', 'app/Foo.php', 12, 5, 'variable.undefined');
        $sarif = $this->render(new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]));

        $run = $sarif['runs'][0];
        $this->assertSame('phpstan', $run['tool']['driver']['name']);

        $res = $run['results'][0];
        $this->assertSame('variable.undefined', $res['ruleId']);
        $this->assertSame('error', $res['level']);
        $this->assertSame('Undefined variable $x', $res['message']['text']);

        $location = $res['locations'][0]['physicalLocation'];
        $this->assertSame('app/Foo.php', $location['artifactLocation']['uri']);
        $this->assertSame(12, $location['region']['startLine']);
        $this->assertSame(5, $location['region']['startColumn']);
    }

    public function test_severity_maps_to_the_sarif_level(): void
    {
        $findings = [
            new Finding('t', Severity::Error, 'e', 'a.php', 1),
            new Finding('t', Severity::Warning, 'w', 'a.php', 1),
            new Finding('t', Severity::Info, 'i', 'a.php', 1),
        ];
        $sarif = $this->render(new RunResult([
            StepResult::failed('t', 'T', findings: $findings, durationSeconds: 1.0, exitCode: 1),
        ]));

        $levels = array_map(static fn (array $r): string => $r['level'], $sarif['runs'][0]['results']);
        $this->assertSame(['error', 'warning', 'note'], $levels);
    }

    public function test_each_executed_step_becomes_its_own_run(): void
    {
        $sarif = $this->render(new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [new Finding('phpstan', Severity::Error, 'a', 'a.php', 1)], durationSeconds: 1.0, exitCode: 1),
            StepResult::failed('psalm', 'Psalm', findings: [new Finding('psalm', Severity::Error, 'b', 'b.php', 2)], durationSeconds: 1.0, exitCode: 1),
        ]));

        $drivers = array_map(static fn (array $run): string => $run['tool']['driver']['name'], $sarif['runs']);
        $this->assertSame(['phpstan', 'psalm'], $drivers);
    }

    public function test_a_ruleless_finding_falls_back_to_the_tool_as_rule_id(): void
    {
        $finding = new Finding('pint', Severity::Warning, 'Style issue', 'a.php', fixable: true);
        $sarif = $this->render(new RunResult([
            StepResult::failed('pint', 'Pint', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]));

        $this->assertSame('pint', $sarif['runs'][0]['results'][0]['ruleId']);
    }

    public function test_a_finding_without_a_file_has_no_locations(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'General error');
        $sarif = $this->render(new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]));

        $this->assertSame([], $sarif['runs'][0]['results'][0]['locations']);
    }
}
