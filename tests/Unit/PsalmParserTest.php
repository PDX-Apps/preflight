<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Parsing\PsalmParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PsalmParser::class)]
final class PsalmParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/psalm/' . $name);
    }

    public function test_an_empty_array_yields_nothing(): void
    {
        $result = (new PsalmParser())->parse(new ProcessResult(0, $this->fixture('clean.json'), ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_it_emits_one_finding_per_issue(): void
    {
        $findings = (new PsalmParser())->parse(new ProcessResult(2, $this->fixture('with-issues.json'), ''))->findings;

        $this->assertCount(2, $findings);
        foreach ($findings as $finding) {
            $this->assertSame('psalm', $finding->tool);
            $this->assertFalse($finding->fixable);
        }
    }

    public function test_it_carries_file_line_column_message_and_rule_type(): void
    {
        $first = (new PsalmParser())->parse(new ProcessResult(2, $this->fixture('with-issues.json'), ''))->findings[0];

        $this->assertSame('src/Config/Configuration.php', $first->file);
        $this->assertSame(63, $first->line);
        $this->assertSame(21, $first->column);
        $this->assertSame('PossiblyUnusedMethod', $first->rule);
        $this->assertStringContainsString('usesModules', $first->message);
    }

    public function test_it_maps_psalm_severity_to_finding_severity(): void
    {
        $findings = (new PsalmParser())->parse(new ProcessResult(2, $this->fixture('with-issues.json'), ''))->findings;

        $this->assertSame(Severity::Error, $findings[0]->severity);
        $this->assertSame(Severity::Info, $findings[1]->severity, 'psalm "info" maps to Info');
    }

    public function test_a_failure_with_unparseable_output_falls_back_to_stderr(): void
    {
        $findings = (new PsalmParser())->parse(new ProcessResult(1, '', 'Invalid composer.json'))->findings;

        $this->assertCount(1, $findings);
        $this->assertNull($findings[0]->file);
        $this->assertStringContainsString('Invalid composer.json', $findings[0]->message);
    }

    public function test_the_tool_label_is_configurable(): void
    {
        $findings = (new PsalmParser('psalm-check'))
            ->parse(new ProcessResult(2, $this->fixture('with-issues.json'), ''))->findings;

        $this->assertSame('psalm-check', $findings[0]->tool);
    }
}
