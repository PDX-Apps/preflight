<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Coverage\CloverReport;
use PdxApps\Preflight\Coverage\PatchCoverage;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Wraps the test parser to enforce line-level patch (diff) coverage: every line the current
 * change touched must be covered to the required percentage.
 *
 * Where {@see CoverageParser} gates the whole project's line %, this one judges only the
 * changed lines — the signal an agent (or a PR) actually wants: "did you test what you just
 * wrote?" It reads the Clover report the test run produced, intersects it with the changed
 * ranges, and on a shortfall adds one error {@see Finding} per file naming the exact uncovered
 * lines, so the fix is unambiguous. Inner findings (test failures) pass through unchanged.
 *
 * It is inert unless the run is scoped to a change (`--since`/`--dirty`); a whole-project run
 * has no changed ranges, so there is nothing to gate and the inner result passes through.
 */
final readonly class CoveragePatchParser implements OutputParser
{
    /**
     * @param array<string, list<array{int, int}>> $changedRanges file => list of [start, end]
     */
    public function __construct(
        private OutputParser $inner,
        private string $cloverPath,
        private float $minimum,
        private string $projectRoot,
        private array $changedRanges,
        private string $tool = 'test',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $base = $this->inner->parse($result);

        if ($this->changedRanges === []) {
            return $base;
        }

        $report = CloverReport::fromFile($this->cloverPath, $this->projectRoot);
        $patch = PatchCoverage::compute($this->changedRanges, $report);

        $percent = $patch->percent();
        $metrics = $percent === null
            ? $base->metrics
            : [...$base->metrics, sprintf('patch coverage %.2f%% (%d/%d changed lines)', $percent, $patch->covered, $patch->total)];

        if ($patch->meets($this->minimum)) {
            return new ParseResult($base->findings, $base->changed, $metrics);
        }

        return new ParseResult([...$base->findings, ...$this->findings($patch)], $base->changed, $metrics);
    }

    /**
     * One error finding per file with uncovered changed lines, plus a lead finding stating the
     * overall patch percentage so the gate's reason is visible even in summary output.
     *
     * @return list<Finding>
     */
    private function findings(PatchCoverage $patch): array
    {
        $findings = [new Finding(
            tool: $this->tool,
            severity: Severity::Error,
            message: sprintf(
                'Patch coverage %.2f%% (%d/%d changed lines) is below the required %.2f%%.',
                (float) $patch->percent(),
                $patch->covered,
                $patch->total,
                $this->minimum,
            ),
        )];

        foreach ($patch->uncovered as $file => $lines) {
            $findings[] = new Finding(
                tool: $this->tool,
                severity: Severity::Error,
                message: sprintf('Uncovered changed lines: %s', $this->ranges($lines)),
                file: $file,
                line: $lines[0],
            );
        }

        return $findings;
    }

    /**
     * Collapse a sorted list of line numbers into compact ranges, e.g. [42,43,44,51] => "42-44, 51".
     *
     * @param list<int> $lines
     */
    private function ranges(array $lines): string
    {
        $spans = [];
        $start = $prev = $lines[0];

        foreach (array_slice($lines, 1) as $line) {
            if ($line === $prev + 1) {
                $prev = $line;

                continue;
            }

            $spans[] = $this->span($start, $prev);
            $start = $prev = $line;
        }

        $spans[] = $this->span($start, $prev);

        return implode(', ', $spans);
    }

    private function span(int $start, int $end): string
    {
        return $start === $end ? (string) $start : sprintf('%d-%d', $start, $end);
    }
}
