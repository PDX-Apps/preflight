<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Report;

use DateTimeImmutable;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;

/**
 * A durable, serializable record of one run — the artifact written by `--report`.
 *
 * Metadata (preflight version, timestamp, mode, overall success, summary counts) is always
 * present; the optional sections (findings, steps, raw output) are layered on per the
 * requested {@see ReportInclude} parts. Distinct from the transient per-tool report file
 * the runner manages internally.
 */
final readonly class RunReport
{
    /**
     * @param  list<ReportInclude>  $include
     */
    public function __construct(
        private RunResult $result,
        private DateTimeImmutable $ranAt,
        private string $version,
        private Mode $mode,
        private array $include,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $report = [
            'preflight' => $this->version,
            'ranAt' => $this->ranAt->format('c'),
            'mode' => $this->mode->value,
            'success' => $this->result->isSuccess(),
            'durationSeconds' => $this->result->totalDurationSeconds(),
            'summary' => [
                'passed' => count($this->result->passed()),
                'failed' => count($this->result->failed()),
                'skipped' => count($this->result->skipped()),
            ],
        ];

        if ($this->has(ReportInclude::Steps)) {
            $report['steps'] = $this->steps();
        }

        if ($this->has(ReportInclude::Findings)) {
            $report['findings'] = array_map(
                static fn (Finding $f): array => $f->toArray(),
                $this->result->findings(),
            );
        }

        return $report;
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The per-step section. Without {@see ReportInclude::Passing} only failed steps are
     * listed; with it, all steps are. Raw output is added only with {@see ReportInclude::Output}.
     *
     * @return list<array<string, mixed>>
     */
    private function steps(): array
    {
        $withOutput = $this->has(ReportInclude::Output);
        $all = $this->has(ReportInclude::Passing);

        $steps = [];
        foreach ($this->result->steps as $step) {
            if (! $all && ! $step->isFailure()) {
                continue;
            }
            $steps[] = $this->step($step, $withOutput);
        }

        return $steps;
    }

    /**
     * @return array<string, mixed>
     */
    private function step(StepResult $step, bool $withOutput): array
    {
        $data = $step->toArray();
        if ($withOutput) {
            $data['output'] = $step->output;
        }

        return $data;
    }

    private function has(ReportInclude $include): bool
    {
        return in_array($include, $this->include, true);
    }
}
