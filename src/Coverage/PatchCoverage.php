<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Coverage;

/**
 * Line-level patch (diff) coverage: of the lines a change touched, how many are covered?
 *
 * It intersects the changed line ranges (from git) with the executable lines a {@see
 * CloverReport} measured. Only changed lines that coverage actually tracks count toward the
 * total — a changed brace, blank line, comment, or a line in a non-PHP / never-loaded file
 * isn't executable, so it neither helps nor hurts the percentage. This mirrors how diff-
 * coverage tools (and Codecov's patch check) judge a change: by the new code that *could* be
 * covered.
 */
final readonly class PatchCoverage
{
    /**
     * @param int $covered changed executable lines that were hit
     * @param int $total changed executable lines coverage could measure
     * @param array<string, list<int>> $uncovered file => sorted uncovered line numbers
     */
    private function __construct(
        public int $covered,
        public int $total,
        public array $uncovered,
    ) {
    }

    /**
     * Compute patch coverage from the changed ranges and the report.
     *
     * @param array<string, list<array{int, int}>> $changedRanges file => list of [start, end]
     */
    public static function compute(array $changedRanges, CloverReport $report): self
    {
        $covered = 0;
        $total = 0;
        $uncovered = [];

        foreach ($changedRanges as $file => $ranges) {
            $hits = $report->linesFor($file);

            foreach (self::linesIn($ranges) as $line) {
                if (! array_key_exists($line, $hits)) {
                    continue; // not an executable line coverage tracks
                }

                $total++;
                if ($hits[$line] > 0) {
                    $covered++;
                } else {
                    $uncovered[$file][] = $line;
                }
            }
        }

        return new self($covered, $total, $uncovered);
    }

    /**
     * The covered percentage, or null when no changed line was measurable (nothing to judge).
     */
    public function percent(): ?float
    {
        if ($this->total === 0) {
            return null;
        }

        return (float) $this->covered / (float) $this->total * 100.0;
    }

    public function meets(float $minimum): bool
    {
        $percent = $this->percent();

        return $percent === null || $percent >= $minimum;
    }

    /**
     * The unique, ordered line numbers covered by a set of ranges.
     *
     * @param list<array{int, int}> $ranges
     * @return list<int>
     */
    private static function linesIn(array $ranges): array
    {
        $lines = [];
        foreach ($ranges as [$start, $end]) {
            for ($line = $start; $line <= $end; $line++) {
                $lines[$line] = true;
            }
        }

        ksort($lines);

        return array_keys($lines);
    }
}
