<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Wraps the test parser to enforce a minimum line-coverage threshold.
 *
 * PHPUnit/Paratest have no native fail-under, so the step runs them with
 * `--coverage-text` and this parser reads the reported line percentage from the console
 * output, adding an error {@see Finding} when it falls below the required minimum. Inner
 * findings (test failures) pass through unchanged; a run is below threshold *or* has failing
 * tests fails the step (the step judges by findings when a minimum is set).
 *
 * If no percentage can be found (e.g. coverage produced no output) the threshold is treated
 * as unmet-but-unmeasurable and left to the inner result — the no-driver case is handled
 * upstream by the step, which never reaches this parser.
 */
final readonly class CoverageParser implements OutputParser
{
    public function __construct(
        private OutputParser $inner,
        private float $minimum,
        private string $tool = 'test',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $base = $this->inner->parse($result);

        $percent = $this->lineCoverage($result->combinedOutput());
        if ($percent === null || $percent >= $this->minimum) {
            return $base;
        }

        $finding = new Finding(
            tool: $this->tool,
            severity: Severity::Error,
            message: sprintf(
                'Line coverage %.2f%% is below the required %.2f%%.',
                $percent,
                $this->minimum,
            ),
        );

        return new ParseResult([...$base->findings, $finding], $base->changed);
    }

    /**
     * Pull the line-coverage percentage from PHPUnit's `--coverage-text` summary, which
     * prints a line like `  Lines:   87.50% (140/160)`.
     */
    private function lineCoverage(string $output): ?float
    {
        if (preg_match('/^\s*Lines:\s+(\d+(?:\.\d+)?)%/m', $output, $matches) !== 1) {
            return null;
        }

        return (float) $matches[1];
    }
}
