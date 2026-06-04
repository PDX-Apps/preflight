<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Parsing\DeptracParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeptracParser::class)]
final class DeptracParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/deptrac/' . $name);
    }

    private function parser(): DeptracParser
    {
        return new DeptracParser('/project');
    }

    public function test_a_clean_result_yields_nothing(): void
    {
        // "files" is an empty array ([]) when there are no violations.
        $result = $this->parser()->parse(new ProcessResult(0, $this->fixture('clean.json'), ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_each_violation_becomes_an_error_finding(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-violations.json'), ''))->findings;

        $this->assertCount(2, $findings);
        foreach ($findings as $finding) {
            $this->assertSame('deptrac', $finding->tool);
            $this->assertSame(Severity::Error, $finding->severity);
            $this->assertFalse($finding->fixable);
        }
    }

    public function test_a_violation_carries_file_line_and_message(): void
    {
        $first = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-violations.json'), ''))->findings[0];

        $this->assertSame('src/Domain/Service.php', $first->file);
        $this->assertSame(4, $first->line);
        $this->assertStringContainsString('must not depend on', $first->message);
    }

    public function test_a_failure_with_unparseable_output_falls_back_to_stderr(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, 'not json', 'Could not read depfile'))->findings;

        $this->assertCount(1, $findings);
        $this->assertNull($findings[0]->file);
        $this->assertStringContainsString('Could not read depfile', $findings[0]->message);
    }

    public function test_the_tool_label_is_configurable(): void
    {
        $findings = (new DeptracParser('/project', 'arch'))
            ->parse(new ProcessResult(1, $this->fixture('with-violations.json'), ''))->findings;

        $this->assertSame('arch', $findings[0]->tool);
    }
}
