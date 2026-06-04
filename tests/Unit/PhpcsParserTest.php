<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PhpcsParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpcsParser::class)]
final class PhpcsParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/phpcs/' . $name);
    }

    // --- check mode (phpcs, JSON) ---

    public function test_check_clean_yields_nothing(): void
    {
        $result = (new PhpcsParser(Mode::Check, '/project'))->parse(new ProcessResult(0, $this->fixture('clean.json'), ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_check_emits_one_finding_per_message_with_relative_path(): void
    {
        $findings = (new PhpcsParser(Mode::Check, '/project'))
            ->parse(new ProcessResult(3, $this->fixture('with-violations.json'), ''))->findings;

        $this->assertCount(3, $findings);
        $this->assertSame('src/Foo.php', $findings[0]->file);
        $this->assertSame(12, $findings[0]->line);
        $this->assertSame(20, $findings[0]->column);
        $this->assertSame('PSR12.Functions.NullableTypeDeclaration', $findings[0]->rule);
        $this->assertTrue($findings[0]->fixable);
    }

    public function test_check_maps_error_and_warning_severities(): void
    {
        $findings = (new PhpcsParser(Mode::Check, '/project'))
            ->parse(new ProcessResult(3, $this->fixture('with-violations.json'), ''))->findings;

        $this->assertSame(Severity::Error, $findings[0]->severity);   // type ERROR
        $this->assertSame(Severity::Warning, $findings[2]->severity); // type WARNING (line length)
    }

    public function test_check_marks_only_fixable_messages_fixable(): void
    {
        $findings = (new PhpcsParser(Mode::Check, '/project'))
            ->parse(new ProcessResult(3, $this->fixture('with-violations.json'), ''))->findings;

        $this->assertTrue($findings[0]->fixable, 'space-after-comma is fixable');
        $this->assertFalse($findings[1]->fixable, 'forbidden dd() is not fixable');
    }

    // --- fix mode (phpcbf, text table) ---

    public function test_fix_reports_changed_files_from_the_summary_table(): void
    {
        $parsed = (new PhpcsParser(Mode::Fix, '/project'))->parse(new ProcessResult(2, $this->fixture('fixed.txt'), ''));

        $this->assertSame(
            ['src/Config/ConfigLoader.php', 'src/Render/HumanRenderer.php', 'src/Steps/Pint.php'],
            $parsed->changed,
        );
    }

    public function test_fix_reports_a_finding_for_files_with_remaining_issues(): void
    {
        $findings = (new PhpcsParser(Mode::Fix, '/project'))->parse(new ProcessResult(2, $this->fixture('fixed.txt'), ''))->findings;

        $this->assertCount(1, $findings);
        $this->assertSame('src/Render/HumanRenderer.php', $findings[0]->file);
        $this->assertStringContainsString('remaining', strtolower($findings[0]->message));
        $this->assertFalse($findings[0]->fixable, 'remaining issues are the unfixable ones');
    }

    public function test_fix_clean_yields_nothing(): void
    {
        $parsed = (new PhpcsParser(Mode::Fix, '/project'))->parse(new ProcessResult(0, $this->fixture('fixed-clean.txt'), ''));

        $this->assertSame([], $parsed->findings);
        $this->assertSame([], $parsed->changed);
    }
}
