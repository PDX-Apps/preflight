<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Result;

use PdxApps\Preflight\Finding;

/**
 * The outcome of running (or not running) one step: its status, timing, raw output,
 * and the normalized {@see Finding} list parsed from the tool's output.
 *
 * Construct via the named factories rather than the constructor so each terminal state
 * is unambiguous at the call site.
 */
final readonly class StepResult
{
    /**
     * @param  list<Finding>  $findings
     * @param  list<string>  $changed files a fixer rewrote during this step
     * @param  list<string>  $metrics informational one-liners (e.g. a coverage %) for a passing run
     */
    private function __construct(
        public string $name,
        public string $label,
        public StepStatus $status,
        public array $findings,
        public float $durationSeconds,
        public ?int $exitCode,
        public string $output,
        public ?string $skipReason,
        public array $changed = [],
        public array $metrics = [],
    ) {
    }

    /**
     * @param  list<Finding>  $findings
     * @param  list<string>  $changed
     * @param  list<string>  $metrics
     */
    public static function passed(
        string $name,
        string $label,
        float $durationSeconds,
        string $output = '',
        array $findings = [],
        int $exitCode = 0,
        array $changed = [],
        array $metrics = [],
    ): self {
        return new self($name, $label, StepStatus::Passed, $findings, $durationSeconds, $exitCode, $output, null, $changed, $metrics);
    }

    /**
     * @param  list<Finding>  $findings
     * @param  list<string>  $changed
     * @param  list<string>  $metrics
     */
    public static function failed(
        string $name,
        string $label,
        array $findings,
        float $durationSeconds,
        int $exitCode,
        string $output = '',
        array $changed = [],
        array $metrics = [],
    ): self {
        return new self($name, $label, StepStatus::Failed, $findings, $durationSeconds, $exitCode, $output, null, $changed, $metrics);
    }

    public static function skipped(string $name, string $label, string $reason): self
    {
        return new self($name, $label, StepStatus::Skipped, [], 0.0, null, '', $reason);
    }

    public static function missingTool(string $name, string $label, string $reason): self
    {
        return new self($name, $label, StepStatus::MissingTool, [], 0.0, null, '', $reason);
    }

    public function isFailure(): bool
    {
        return $this->status->isFailure();
    }

    public function isSuccess(): bool
    {
        return ! $this->status->isFailure();
    }

    /**
     * @return array{
     *     name: string,
     *     label: string,
     *     status: string,
     *     durationSeconds: float,
     *     exitCode: ?int,
     *     skipReason: ?string,
     *     findings: list<array<string, mixed>>,
     *     changed: list<string>,
     *     metrics: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'status' => $this->status->value,
            'durationSeconds' => $this->durationSeconds,
            'exitCode' => $this->exitCode,
            'skipReason' => $this->skipReason,
            'findings' => array_map(static fn (Finding $f): array => $f->toArray(), $this->findings),
            'changed' => $this->changed,
            'metrics' => $this->metrics,
        ];
    }
}
