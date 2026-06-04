<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Parsing\JUnitParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JUnitParser::class)]
final class JUnitParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/junit/' . $name);
    }

    private function parser(): JUnitParser
    {
        return new JUnitParser('/project');
    }

    public function test_a_passing_suite_yields_no_findings(): void
    {
        $result = $this->parser()->parse(new ProcessResult(0, $this->fixture('passing.xml'), ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_it_emits_one_error_finding_per_failure_and_error(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-failures.xml'), ''))->findings;

        // 1 <failure> + 1 <error> = 2 findings (the passing testcase is ignored).
        $this->assertCount(2, $findings);
        foreach ($findings as $finding) {
            $this->assertSame('test', $finding->tool);
            $this->assertSame(Severity::Error, $finding->severity);
            $this->assertFalse($finding->fixable);
        }
    }

    public function test_a_failure_carries_the_test_name_file_line_and_message(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-failures.xml'), ''))->findings;

        $fail = $findings[0];
        $this->assertSame('tests/SampleTest.php', $fail->file, 'absolute path relativized');
        $this->assertSame(5, $fail->line);
        $this->assertStringContainsString('test_fails', $fail->message);
        $this->assertStringContainsString('one is not two', $fail->message);
    }

    public function test_it_distinguishes_failures_from_errors_in_the_message(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-failures.xml'), ''))->findings;

        $error = $findings[1];
        $this->assertSame(9, $error->line);
        $this->assertStringContainsString('test_errors', $error->message);
        $this->assertStringContainsString('boom', $error->message);
    }

    public function test_unparseable_xml_on_a_failed_run_falls_back_to_stderr(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, 'not xml', 'fatal: bootstrap missing'))->findings;

        $this->assertCount(1, $findings);
        $this->assertNull($findings[0]->file);
        $this->assertStringContainsString('bootstrap missing', $findings[0]->message);
    }

    public function test_unparseable_xml_on_a_passing_run_yields_nothing(): void
    {
        $result = $this->parser()->parse(new ProcessResult(0, '', ''));

        $this->assertSame([], $result->findings);
    }

    public function test_the_tool_label_is_configurable(): void
    {
        $findings = (new JUnitParser('/project', 'pest'))
            ->parse(new ProcessResult(1, $this->fixture('with-failures.xml'), ''))->findings;

        $this->assertSame('pest', $findings[0]->tool);
    }
}
