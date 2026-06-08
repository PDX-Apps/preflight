<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Result;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Severity;

/**
 * Drops findings whose file matches a configured exclude path, across every step.
 *
 * Because Preflight normalizes each tool's output to a common {@see Finding}, filtering by the
 * finding's file works uniformly for all tools — including ones with no CLI exclude (PHPStan,
 * Psalm, Rector). A step whose findings are all excluded becomes a pass (its verdict is then
 * finding-driven); a step that failed with no findings (a crash) is left untouched, so real
 * failures aren't masked. Findings with no file (tool-level messages) are always kept.
 */
final readonly class FindingExcluder
{
    /**
     * @param list<string> $patterns project-relative path globs/prefixes
     */
    public function __construct(private array $patterns)
    {
    }

    public function apply(RunResult $result): RunResult
    {
        if ($this->patterns === []) {
            return $result;
        }

        return new RunResult(array_map($this->filterStep(...), $result->steps));
    }

    private function filterStep(StepResult $step): StepResult
    {
        if (! $step->status->didRun()) {
            return $step;
        }

        $kept = array_values(array_filter(
            $step->findings,
            fn (Finding $finding): bool => ! $this->isExcluded($finding),
        ));

        if (count($kept) === count($step->findings)) {
            return $step;
        }

        return $step->withFindings($kept, $this->statusFor($step, $kept));
    }

    /**
     * @param list<Finding> $kept
     */
    private function statusFor(StepResult $step, array $kept): StepStatus
    {
        if ($step->status !== StepStatus::Failed) {
            return $step->status;
        }

        foreach ($kept as $finding) {
            if ($finding->severity === Severity::Error) {
                return StepStatus::Failed;
            }
        }

        // Every error came from an excluded path — the step now passes.
        return StepStatus::Passed;
    }

    private function isExcluded(Finding $finding): bool
    {
        if ($finding->file === null) {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if ($this->matches(trim($pattern, '/'), $finding->file)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $pattern, string $file): bool
    {
        return $file === $pattern
            || str_starts_with($file, $pattern . '/')
            || fnmatch($pattern, $file)
            || fnmatch($pattern . '/*', $file);
    }
}
