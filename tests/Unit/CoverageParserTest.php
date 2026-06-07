<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Parsing\CoverageParser;
use PdxApps\Preflight\Parsing\ParseResult;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoverageParser::class)]
final class CoverageParserTest extends TestCase
{
    /**
     * @param  list<Finding>  $innerFindings
     */
    private function parser(float $minimum, array $innerFindings = []): CoverageParser
    {
        $inner = new class ($innerFindings) implements OutputParser {
            /** @param list<Finding> $findings */
            public function __construct(private readonly array $findings)
            {
            }

            public function parse(ProcessResult $result): ParseResult
            {
                return ParseResult::ofFindings($this->findings);
            }
        };

        return new CoverageParser($inner, $minimum, 'test');
    }

    private function withCoverageText(string $percentLine): ProcessResult
    {
        // The runner places the console output (where --coverage-text prints) in stderr.
        return new ProcessResult(0, '<testsuites/>', "PHPUnit output\n  {$percentLine}\n");
    }

    public function test_it_adds_an_error_finding_when_below_the_minimum(): void
    {
        $result = $this->parser(90.0)->parse($this->withCoverageText('Lines:   87.50% (140/160)'));

        $this->assertCount(1, $result->findings);
        $this->assertSame(Severity::Error, $result->findings[0]->severity);
        $this->assertStringContainsString('87.50%', $result->findings[0]->message);
        $this->assertStringContainsString('90.00%', $result->findings[0]->message);
    }

    public function test_it_is_clean_when_at_or_above_the_minimum(): void
    {
        $this->assertSame([], $this->parser(90.0)->parse($this->withCoverageText('Lines:   90.00% (90/100)'))->findings);
        $this->assertSame([], $this->parser(90.0)->parse($this->withCoverageText('Lines:   100.00% (10/10)'))->findings);
    }

    public function test_it_preserves_inner_findings_and_appends_the_coverage_one(): void
    {
        $testFailure = new Finding('test', Severity::Error, 'FooTest failed');
        $result = $this->parser(90.0, [$testFailure])->parse($this->withCoverageText('Lines:   10.00% (1/10)'));

        $this->assertCount(2, $result->findings);
        $this->assertSame($testFailure, $result->findings[0]);
        $this->assertStringContainsString('below the required', $result->findings[1]->message);
    }

    public function test_it_passes_through_when_no_percentage_is_present(): void
    {
        $result = $this->parser(90.0)->parse(new ProcessResult(0, '<testsuites/>', 'no coverage summary here'));

        $this->assertSame([], $result->findings);
    }
}
