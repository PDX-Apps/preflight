<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Report\ReportInclude;
use PdxApps\Preflight\Report\RunReport;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PdxApps\Preflight\Support\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunReport::class)]
#[CoversClass(ReportInclude::class)]
final class RunReportTest extends TestCase
{
    private function sampleResult(): RunResult
    {
        $finding = new Finding('phpstan', Severity::Error, 'boom', 'app/A.php', 12);

        return new RunResult([
            StepResult::passed('pint', 'Pint', durationSeconds: 0.5),
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
            StepResult::skipped('test', 'Tests', reason: 'no tool'),
        ]);
    }

    private function report(array $include): RunReport
    {
        return new RunReport(
            result: $this->sampleResult(),
            ranAt: FrozenClock::at('2026-01-02T03:04:05+00:00')->now(),
            version: '0.1.0',
            mode: Mode::Check,
            include: $include,
        );
    }

    public function test_metadata_is_always_present(): void
    {
        $array = $this->report([])->toArray();

        $this->assertSame('0.1.0', $array['preflight']);
        $this->assertSame('2026-01-02T03:04:05+00:00', $array['ranAt']);
        $this->assertSame('check', $array['mode']);
        $this->assertFalse($array['success']);
        $this->assertSame(['passed' => 1, 'failed' => 1, 'skipped' => 1], $array['summary']);
    }

    public function test_findings_are_included_by_request(): void
    {
        $array = $this->report([ReportInclude::Findings])->toArray();

        $this->assertArrayHasKey('findings', $array);
        $this->assertSame('app/A.php', $array['findings'][0]['file']);
    }

    public function test_findings_are_omitted_when_not_requested(): void
    {
        $this->assertArrayNotHasKey('findings', $this->report([ReportInclude::Steps])->toArray());
    }

    public function test_steps_are_included_by_request_without_raw_output(): void
    {
        // Without Passing, the steps section lists only failed steps (here: phpstan).
        $array = $this->report([ReportInclude::Steps])->toArray();

        $this->assertArrayHasKey('steps', $array);
        $this->assertSame('phpstan', $array['steps'][0]['name']);
        $this->assertSame('failed', $array['steps'][0]['status']);
        $this->assertArrayNotHasKey('output', $array['steps'][0], 'raw output is opt-in via Output');
    }

    public function test_output_include_adds_raw_tool_output_to_steps(): void
    {
        $result = new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [], durationSeconds: 1.0, exitCode: 1, output: 'RAW STDOUT'),
        ]);
        $report = new RunReport(
            result: $result,
            ranAt: FrozenClock::at('2026-01-02T03:04:05+00:00')->now(),
            version: '0.1.0',
            mode: Mode::Check,
            include: [ReportInclude::Steps, ReportInclude::Output],
        );

        $array = $report->toArray();
        $this->assertSame('RAW STDOUT', $array['steps'][0]['output']);
    }

    public function test_passing_include_is_reflected_but_default_excludes_passing_findings_noise(): void
    {
        // Findings list is always failures-derived; the Passing flag governs whether the
        // steps list includes passed/skipped steps or only the failed ones.
        $failedOnly = $this->report([ReportInclude::Steps])->toArray();
        $this->assertCount(1, $failedOnly['steps'], 'without Passing, only failed steps are listed');

        $withPassing = $this->report([ReportInclude::Steps, ReportInclude::Passing])->toArray();
        $this->assertCount(3, $withPassing['steps'], 'with Passing, all steps are listed');
    }

    public function test_it_encodes_to_json(): void
    {
        $json = $this->report([ReportInclude::Findings, ReportInclude::Steps])->toJson();

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('0.1.0', $decoded['preflight']);
    }

    public function test_include_parses_from_a_comma_list_with_all_expanding(): void
    {
        $this->assertSame(
            [ReportInclude::Findings, ReportInclude::Steps],
            ReportInclude::parse('findings,steps'),
        );

        $this->assertEqualsCanonicalizing(ReportInclude::cases(), ReportInclude::parse('all'));
    }
}
