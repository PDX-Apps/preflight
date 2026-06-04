<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Parsing\ExitCodeParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExitCodeParser::class)]
final class ExitCodeParserTest extends TestCase
{
    public function test_a_successful_process_yields_no_findings(): void
    {
        $parser = new ExitCodeParser('composer-audit');

        $result = $parser->parse(new ProcessResult(0, 'all good', ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_a_failed_process_yields_a_single_error_finding_tagged_with_the_tool(): void
    {
        $parser = new ExitCodeParser('composer-audit');

        $findings = $parser->parse(new ProcessResult(2, '', 'CVE found'))->findings;

        $this->assertCount(1, $findings);
        $this->assertSame('composer-audit', $findings[0]->tool);
        $this->assertSame(Severity::Error, $findings[0]->severity);
        $this->assertStringContainsString('exit code 2', $findings[0]->message);
    }

    public function test_a_custom_failure_message_overrides_the_default(): void
    {
        $parser = new ExitCodeParser('composer-audit', failureMessage: 'Vulnerable dependencies detected');

        $findings = $parser->parse(new ProcessResult(1, '', ''))->findings;

        $this->assertSame('Vulnerable dependencies detected', $findings[0]->message);
    }
}
