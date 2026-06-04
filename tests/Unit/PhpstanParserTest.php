<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Parsing\PhpstanParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpstanParser::class)]
final class PhpstanParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/phpstan/' . $name);
    }

    private function parser(): PhpstanParser
    {
        return new PhpstanParser('/project');
    }

    public function test_a_clean_result_yields_nothing(): void
    {
        $result = $this->parser()->parse(new ProcessResult(0, $this->fixture('clean.json'), ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_it_emits_one_error_finding_per_file_message(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-errors.json'), ''))->findings;

        // 2 in Foo.php + 1 in Bar.php + 1 general = 4
        $this->assertCount(4, $findings);

        foreach ($findings as $finding) {
            $this->assertSame('phpstan', $finding->tool);
            $this->assertSame(Severity::Error, $finding->severity);
            $this->assertFalse($finding->fixable, 'phpstan reports, it does not fix');
        }
    }

    public function test_file_paths_are_relativized_against_the_project_root(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-errors.json'), ''))->findings;

        $files = array_map(static fn ($f) => $f->file, $findings);
        $this->assertContains('src/Foo.php', $files);
        $this->assertContains('src/Bar.php', $files);
    }

    public function test_it_carries_line_message_and_rule_identifier(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-errors.json'), ''))->findings;

        $first = $findings[0];
        $this->assertSame('src/Foo.php', $first->file);
        $this->assertSame(3, $first->line);
        $this->assertSame('return.type', $first->rule);
        $this->assertStringContainsString('should return int', $first->message);
    }

    public function test_a_general_error_becomes_a_fileless_finding(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-errors.json'), ''))->findings;

        $general = array_values(array_filter($findings, static fn ($f) => $f->file === null));
        $this->assertCount(1, $general);
        $this->assertStringContainsString('something general', $general[0]->message);
    }

    public function test_a_failure_with_unparseable_output_falls_back_to_stderr(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, '', 'Path does not exist'))->findings;

        $this->assertCount(1, $findings);
        $this->assertNull($findings[0]->file);
        $this->assertStringContainsString('Path does not exist', $findings[0]->message);
    }

    public function test_the_tool_label_is_configurable(): void
    {
        $findings = (new PhpstanParser('/project', 'analyse'))
            ->parse(new ProcessResult(1, $this->fixture('with-errors.json'), ''))->findings;

        $this->assertSame('analyse', $findings[0]->tool);
    }
}
