<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\RectorParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RectorParser::class)]
final class RectorParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/rector/' . $name);
    }

    public function test_a_clean_result_yields_nothing_in_either_mode(): void
    {
        $clean = new ProcessResult(0, $this->fixture('clean.json'), '');

        $check = (new RectorParser(Mode::Check))->parse($clean);
        $fix = (new RectorParser(Mode::Fix))->parse($clean);

        $this->assertSame([], $check->findings);
        $this->assertSame([], $check->changed);
        $this->assertSame([], $fix->findings);
        $this->assertSame([], $fix->changed);
    }

    public function test_check_mode_reports_each_diff_as_a_fixable_finding(): void
    {
        // Dry-run exits 2 when changes are pending.
        $result = new ProcessResult(2, $this->fixture('with-changes.json'), '');

        $findings = (new RectorParser(Mode::Check))->parse($result)->findings;

        $this->assertCount(2, $findings);
        $this->assertSame('src/Parsing/PintParser.php', $findings[0]->file);
        foreach ($findings as $finding) {
            $this->assertSame('rector', $finding->tool);
            $this->assertSame(Severity::Warning, $finding->severity);
            $this->assertTrue($finding->fixable, 'rector can fix what it reports');
        }
    }

    public function test_check_mode_names_the_applied_rector_in_the_message(): void
    {
        $result = new ProcessResult(2, $this->fixture('with-changes.json'), '');

        $findings = (new RectorParser(Mode::Check))->parse($result)->findings;

        $this->assertStringContainsString('FunctionFirstClassCallableRector', $findings[0]->message);
    }

    public function test_check_mode_reports_no_changed_files(): void
    {
        $result = new ProcessResult(2, $this->fixture('with-changes.json'), '');

        $this->assertSame([], (new RectorParser(Mode::Check))->parse($result)->changed);
    }

    public function test_fix_mode_reports_changed_files_not_findings(): void
    {
        // Apply mode exits 0 after writing.
        $result = new ProcessResult(0, $this->fixture('with-changes.json'), '');

        $parsed = (new RectorParser(Mode::Fix))->parse($result);

        $this->assertSame([], $parsed->findings, 'an applied refactor is a resolution');
        $this->assertSame(['src/Parsing/PintParser.php', 'src/Preflight.php'], $parsed->changed);
    }

    public function test_a_failure_with_unparseable_output_falls_back_to_stderr(): void
    {
        $result = new ProcessResult(1, 'not json', 'Could not load configuration');

        $findings = (new RectorParser(Mode::Check))->parse($result)->findings;

        $this->assertCount(1, $findings);
        $this->assertNull($findings[0]->file);
        $this->assertStringContainsString('Could not load configuration', $findings[0]->message);
    }
}
