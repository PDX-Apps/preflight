<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Parsing\PintParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PintParser::class)]
final class PintParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/pint/' . $name);
    }

    public function test_a_passing_result_yields_nothing(): void
    {
        $result = (new PintParser())->parse(new ProcessResult(0, $this->fixture('clean.json'), ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_check_mode_reports_one_fixable_warning_per_file(): void
    {
        $findings = (new PintParser())->parse(new ProcessResult(1, $this->fixture('with-issues.json'), ''))->findings;

        $this->assertCount(2, $findings);
        $this->assertSame('app/Other.php', $findings[0]->file);
        $this->assertSame('app/Http/Messy.php', $findings[1]->file);

        foreach ($findings as $finding) {
            $this->assertSame('pint', $finding->tool);
            $this->assertSame(Severity::Warning, $finding->severity);
            $this->assertTrue($finding->fixable);
            $this->assertNull($finding->line);
        }
    }

    public function test_fix_mode_reports_changed_files_not_findings(): void
    {
        $result = (new PintParser())->parse(new ProcessResult(0, $this->fixture('fixed.json'), ''));

        $this->assertSame([], $result->findings, 'a fix is a resolution, not a finding');
        $this->assertSame(['app/Other.php', 'app/Http/Messy.php'], $result->changed);
    }

    public function test_the_message_names_the_fixers_that_would_apply(): void
    {
        $findings = (new PintParser())->parse(new ProcessResult(1, $this->fixture('with-issues.json'), ''))->findings;

        $this->assertStringContainsString('binary_operator_spaces', $findings[0]->message);
    }

    public function test_unparseable_output_on_failure_still_yields_one_finding(): void
    {
        $findings = (new PintParser())->parse(new ProcessResult(1, 'not json at all', 'fatal error'))->findings;

        $this->assertCount(1, $findings);
        $this->assertSame('pint', $findings[0]->tool);
        $this->assertTrue($findings[0]->fixable);
        $this->assertNull($findings[0]->file);
    }

    public function test_unparseable_output_on_success_yields_nothing(): void
    {
        $result = (new PintParser())->parse(new ProcessResult(0, 'not json', ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_the_tool_label_is_configurable(): void
    {
        $findings = (new PintParser('format'))->parse(new ProcessResult(1, $this->fixture('with-issues.json'), ''))->findings;

        $this->assertSame('format', $findings[0]->tool);
    }
}
