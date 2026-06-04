<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Parsing\PhpmdParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpmdParser::class)]
final class PhpmdParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/phpmd/' . $name);
    }

    private function parser(): PhpmdParser
    {
        return new PhpmdParser('/project');
    }

    public function test_a_clean_result_yields_nothing(): void
    {
        $result = $this->parser()->parse(new ProcessResult(0, $this->fixture('clean.json'), ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_it_emits_one_warning_finding_per_violation(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(2, $this->fixture('with-violations.json'), ''))->findings;

        $this->assertCount(3, $findings);
        foreach ($findings as $finding) {
            $this->assertSame('phpmd', $finding->tool);
            $this->assertSame(Severity::Warning, $finding->severity);
            $this->assertFalse($finding->fixable);
        }
    }

    public function test_it_carries_file_line_message_and_rule(): void
    {
        $first = $this->parser()->parse(new ProcessResult(2, $this->fixture('with-violations.json'), ''))->findings[0];

        $this->assertSame('src/Config/Configuration.php', $first->file);
        $this->assertSame(35, $first->line);
        $this->assertSame('BooleanArgumentFlag', $first->rule);
        $this->assertStringContainsString('boolean flag argument', $first->message);
    }

    public function test_file_paths_are_relativized_against_the_project_root(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(2, $this->fixture('with-violations.json'), ''))->findings;

        $files = array_map(static fn ($f) => $f->file, $findings);
        $this->assertContains('src/Config/Configuration.php', $files);
        $this->assertContains('src/Steps/Pint.php', $files);
    }

    public function test_deprecation_noise_prepended_to_the_json_is_tolerated(): void
    {
        // Even if the runner's filter misses a stray blank line, leading whitespace must not break parsing.
        $noisy = "\n\n" . $this->fixture('with-violations.json');

        $findings = $this->parser()->parse(new ProcessResult(2, $noisy, ''))->findings;

        $this->assertCount(3, $findings);
    }

    public function test_valid_json_with_no_violations_is_clean_even_on_a_nonzero_exit(): void
    {
        // PHPMD exits non-zero for deprecation noise; valid empty JSON must still be clean.
        $result = $this->parser()->parse(new ProcessResult(3, $this->fixture('clean.json'), 'PHP Deprecated...'));

        $this->assertSame([], $result->findings);
    }

    public function test_processing_errors_become_findings_with_only_the_first_message_line(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(2, $this->fixture('with-processing-error.json'), ''))->findings;

        $this->assertCount(1, $findings);
        $this->assertSame('src/Foo.php', $findings[0]->file);
        $this->assertStringContainsString('Unexpected token', $findings[0]->message);
        $this->assertStringNotContainsString('#0', $findings[0]->message, 'the stack trace is dropped');
    }

    public function test_a_failure_with_unparseable_output_falls_back_to_stderr(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, 'not json', 'Unknown ruleset'))->findings;

        $this->assertCount(1, $findings);
        $this->assertNull($findings[0]->file);
        $this->assertStringContainsString('Unknown ruleset', $findings[0]->message);
    }

    public function test_the_tool_label_is_configurable(): void
    {
        $findings = (new PhpmdParser('/project', 'mess'))
            ->parse(new ProcessResult(2, $this->fixture('with-violations.json'), ''))->findings;

        $this->assertSame('mess', $findings[0]->tool);
    }
}
