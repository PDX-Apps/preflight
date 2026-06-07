<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Parsing\CoveragePatchParser;
use PdxApps\Preflight\Parsing\ParseResult;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PdxApps\Preflight\Tests\Support\NoFindingsParser;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoveragePatchParser::class)]
final class CoveragePatchParserTest extends TestCase
{
    /**
     * @param array<int, int> $lines line => hits
     */
    private function projectWithClover(array $lines): TempProject
    {
        $project = new TempProject();
        $body = sprintf('<file name="%s/src/Foo.php">', $project->root);
        foreach ($lines as $line => $hits) {
            $body .= sprintf('<line num="%d" type="stmt" count="%d"/>', $line, $hits);
        }
        $body .= '</file>';
        $project->file('build/coverage.xml', '<coverage><project>' . $body . '</project></coverage>');

        return $project;
    }

    private function parser(TempProject $project, float $min, array $changed): CoveragePatchParser
    {
        return new CoveragePatchParser(
            new NoFindingsParser(),
            $project->root . '/build/coverage.xml',
            $min,
            $project->root,
            $changed,
        );
    }

    private function emptyResult(): ProcessResult
    {
        return new ProcessResult(0, '', '');
    }

    public function test_it_passes_through_when_there_are_no_changed_lines(): void
    {
        $project = $this->projectWithClover([10 => 0]);
        $parser = $this->parser($project, 100, []);

        $this->assertSame([], $parser->parse($this->emptyResult())->findings, 'no diff means nothing to gate');
    }

    public function test_it_passes_through_when_the_patch_meets_the_threshold(): void
    {
        $project = $this->projectWithClover([10 => 1, 11 => 1]);
        $parser = $this->parser($project, 100, ['src/Foo.php' => [[10, 11]]]);

        $this->assertSame([], $parser->parse($this->emptyResult())->findings);
    }

    public function test_below_threshold_it_adds_a_summary_finding_and_a_per_file_finding(): void
    {
        $project = $this->projectWithClover([10 => 1, 11 => 0, 12 => 0]);
        $parser = $this->parser($project, 100, ['src/Foo.php' => [[10, 12]]]);

        $findings = $parser->parse($this->emptyResult())->findings;

        $this->assertCount(2, $findings);
        $this->assertSame(Severity::Error, $findings[0]->severity);
        $this->assertStringContainsString('Patch coverage 33.33% (1/3 changed lines)', $findings[0]->message);
        $this->assertStringContainsString('required 100.00%', $findings[0]->message);

        $this->assertSame('src/Foo.php', $findings[1]->file);
        $this->assertSame(11, $findings[1]->line);
        $this->assertStringContainsString('Uncovered changed lines: 11-12', $findings[1]->message);
    }

    public function test_it_preserves_inner_findings(): void
    {
        $project = $this->projectWithClover([10 => 0]);
        $inner = new class () implements \PdxApps\Preflight\Contracts\OutputParser {
            public function parse(ProcessResult $result): ParseResult
            {
                return ParseResult::ofFindings([new Finding('test', Severity::Error, 'a test failed')]);
            }
        };
        $parser = new CoveragePatchParser($inner, $project->root . '/build/coverage.xml', 100, $project->root, ['src/Foo.php' => [[10, 10]]]);

        $findings = $parser->parse($this->emptyResult())->findings;

        $this->assertSame('a test failed', $findings[0]->message, 'inner findings come first');
        $this->assertStringContainsString('Patch coverage', $findings[1]->message);
    }

    public function test_it_collapses_uncovered_lines_into_compact_ranges(): void
    {
        $project = $this->projectWithClover([1 => 0, 2 => 0, 3 => 0, 5 => 0, 8 => 0, 9 => 0]);
        $parser = $this->parser($project, 100, ['src/Foo.php' => [[1, 9]]]);

        $findings = $parser->parse($this->emptyResult())->findings;

        // 4,6,7 aren't executable (absent from clover); the rest collapse to ranges.
        $this->assertStringContainsString('Uncovered changed lines: 1-3, 5, 8-9', $findings[1]->message);
    }
}
