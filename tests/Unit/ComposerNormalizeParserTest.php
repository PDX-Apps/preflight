<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\ComposerNormalizeParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerNormalizeParser::class)]
final class ComposerNormalizeParserTest extends TestCase
{
    public function test_check_mode_is_clean_on_a_zero_exit(): void
    {
        $result = (new ComposerNormalizeParser(Mode::Check))
            ->parse(new ProcessResult(0, './composer.json is already normalized.', ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_check_mode_reports_a_fixable_finding_on_a_nonzero_exit(): void
    {
        $result = (new ComposerNormalizeParser(Mode::Check))
            ->parse(new ProcessResult(1, './composer.json is not normalized.', ''));

        $this->assertCount(1, $result->findings);
        $finding = $result->findings[0];
        $this->assertSame('composer-normalize', $finding->tool);
        $this->assertSame(Severity::Warning, $finding->severity);
        $this->assertSame('composer.json', $finding->file);
        $this->assertTrue($finding->fixable);
    }

    public function test_fix_mode_reports_a_changed_file_when_it_normalized(): void
    {
        $result = (new ComposerNormalizeParser(Mode::Fix))
            ->parse(new ProcessResult(0, 'Successfully normalized ./composer.json.', ''));

        $this->assertSame([], $result->findings);
        $this->assertSame(['composer.json'], $result->changed);
    }

    public function test_fix_mode_reports_nothing_when_already_normalized(): void
    {
        $result = (new ComposerNormalizeParser(Mode::Fix))
            ->parse(new ProcessResult(0, './composer.json is already normalized.', ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_the_tool_label_is_configurable(): void
    {
        $finding = (new ComposerNormalizeParser(Mode::Check, 'normalize'))
            ->parse(new ProcessResult(1, 'not normalized', ''))->findings[0];

        $this->assertSame('normalize', $finding->tool);
    }
}
