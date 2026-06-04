<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Result;

use PdxApps\Preflight\Finding;

/**
 * The aggregate outcome of a full run: every {@see StepResult}, plus convenience views
 * (collected findings, per-outcome partitions, total duration) used by renderers and
 * the programmatic API.
 */
final readonly class RunResult
{
    /**
     * @param  list<StepResult>  $steps
     */
    public function __construct(
        /** @var list<StepResult> */
        public array $steps,
    ) {
    }

    public function isFailure(): bool
    {
        return array_any($this->steps, fn ($step) => $step->isFailure());
    }

    public function isSuccess(): bool
    {
        return ! $this->isFailure();
    }

    /**
     * All findings across every step, ordered most-severe-first (stable within a severity).
     *
     * @return list<Finding>
     */
    public function findings(): array
    {
        $findings = [];
        foreach ($this->steps as $step) {
            foreach ($step->findings as $finding) {
                $findings[] = $finding;
            }
        }

        usort(
            $findings,
            static fn (Finding $a, Finding $b): int => $b->severity->weight() <=> $a->severity->weight(),
        );

        return $findings;
    }

    /**
     * @return list<StepResult>
     */
    public function failed(): array
    {
        return array_values(array_filter($this->steps, static fn (StepResult $s): bool => $s->status === StepStatus::Failed));
    }

    /**
     * @return list<StepResult>
     */
    public function passed(): array
    {
        return array_values(array_filter($this->steps, static fn (StepResult $s): bool => $s->status === StepStatus::Passed));
    }

    /**
     * @return list<StepResult>
     */
    public function skipped(): array
    {
        return array_values(array_filter($this->steps, static fn (StepResult $s): bool => $s->status === StepStatus::Skipped));
    }

    public function totalDurationSeconds(): float
    {
        return array_sum(array_map(static fn (StepResult $s): float => $s->durationSeconds, $this->steps));
    }

    /**
     * @return array{
     *     success: bool,
     *     steps: list<array<string, mixed>>,
     *     findings: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccess(),
            'steps' => array_map(static fn (StepResult $s): array => $s->toArray(), $this->steps),
            'findings' => array_map(static fn (Finding $f): array => $f->toArray(), $this->findings()),
        ];
    }
}
