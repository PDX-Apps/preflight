<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Runner;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Contracts\ProgressReporter;
use PdxApps\Preflight\Contracts\Runner;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Process\ProcessSpec;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;

/**
 * Runs steps one after another. The default v1 execution policy.
 *
 * Owns the cross-cutting decisions a step must not make for itself: skipping a step
 * whose tool is absent, skipping a Whole step under a narrowed run, ordering a step's
 * before-commands ahead of its main command, and (optionally) aborting the remaining
 * steps after the first failure. A parallel or caching runner can replace this without
 * any step changing.
 */
final readonly class SequentialRunner implements Runner
{
    public function __construct(
        private ProcessExecutor $executor,
        private bool $failFast = false,
        private ProgressReporter $progress = new NullProgressReporter(),
    ) {
    }

    public function run(iterable $steps, Context $context, Mode $mode): RunResult
    {
        $results = [];
        $aborted = false;

        foreach ($steps as $step) {
            $this->progress->stepStarted($step);

            $result = $aborted
                ? StepResult::skipped($step->name(), $step->label(), 'skipped after an earlier failure (fail-fast)')
                : $this->runStep($step, $context, $mode);

            $results[] = $result;
            $this->progress->stepFinished($result);

            if (! $aborted && $this->failFast && $result->isFailure()) {
                $aborted = true;
            }
        }

        return new RunResult($results);
    }

    private function runStep(Step $step, Context $context, Mode $mode): StepResult
    {
        $tool = $step->tool();
        if ($tool instanceof \PdxApps\Preflight\Support\Tool && ! $context->toolAvailable($tool)) {
            return StepResult::missingTool($step->name(), $step->label(), $this->missingToolReason($tool->binary, $tool->requireHint));
        }

        if ($context->targets()->forcesSkip($step->targeting())) {
            return StepResult::skipped($step->name(), $step->label(), 'cannot scope to a file subset; run without scope flags to include it');
        }

        $plan = $step->plan($context, $mode);
        $start = microtime(true);

        $beforeFailure = $this->runBefore($plan, $context);
        if ($beforeFailure instanceof \PdxApps\Preflight\Runner\BeforeFailure) {
            return StepResult::failed(
                $step->name(),
                $step->label(),
                findings: [$beforeFailure->finding],
                durationSeconds: microtime(true) - $start,
                exitCode: $beforeFailure->result->exitCode,
                output: $beforeFailure->result->combinedOutput(),
            );
        }

        $result = $this->execute($plan, $context);
        if ($plan->filtersDeprecations) {
            $result = $this->stripDeprecations($result);
        }

        $parsed = $plan->parser->parse($result);
        $duration = microtime(true) - $start;

        $succeeded = $plan->judgesByFindings ? $parsed->findings === [] : $result->successful();

        // Advisory notes (e.g. "coverage skipped: no driver") ride along on the result but
        // never change the outcome, which was decided above from the parser findings alone.
        $findings = [...$parsed->findings, ...$plan->notes];

        if ($succeeded) {
            return StepResult::passed(
                $step->name(),
                $step->label(),
                $duration,
                $result->combinedOutput(),
                $findings,
                changed: $parsed->changed,
                metrics: $parsed->metrics,
            );
        }

        return StepResult::failed(
            $step->name(),
            $step->label(),
            $findings,
            $duration,
            $result->exitCode,
            $result->combinedOutput(),
            changed: $parsed->changed,
            metrics: $parsed->metrics,
        );
    }

    /**
     * Run each before-command in order. Returns the first failure (with a describing
     * finding) or null if all succeeded.
     */
    private function runBefore(StepPlan $plan, Context $context): ?BeforeFailure
    {
        foreach ($plan->before as $command) {
            $result = $this->executor->execute($this->spec($command, $plan, $context));
            if ($result->failed()) {
                $finding = new Finding(
                    tool: 'preflight',
                    severity: Severity::Error,
                    message: sprintf('Pre-command failed (exit %d): %s', $result->exitCode, implode(' ', $command)),
                );

                return new BeforeFailure($result, $finding);
            }
        }

        return null;
    }

    /**
     * Execute the plan's main command. When the plan reads a report file, allocate a temp
     * path, substitute it for {@see StepPlan::REPORT_FILE} in the command, run, then read
     * that file into the result's stdout for the parser and delete it.
     */
    private function execute(StepPlan $plan, Context $context): ProcessResult
    {
        if (! $plan->readsReportFile) {
            return $this->executor->execute($this->spec($plan->command, $plan, $context));
        }

        $reportFile = tempnam(sys_get_temp_dir(), 'preflight-report-');
        if ($reportFile === false) {
            // Only when the temp dir is unwritable — not reproducible in tests.
            // @codeCoverageIgnoreStart
            return new ProcessResult(1, '', 'Could not create a temporary report file.');
            // @codeCoverageIgnoreEnd
        }

        try {
            $command = $this->substitute($plan->command, StepPlan::REPORT_FILE, $reportFile);
            $result = $this->executor->execute($this->spec($command, $plan, $context));

            $report = is_file($reportFile) ? (string) file_get_contents($reportFile) : '';

            // Hand the report contents to the parser via stdout; keep stderr for diagnostics.
            return new ProcessResult($result->exitCode, $report, $result->combinedOutput());
        } finally {
            if (is_file($reportFile)) {
                unlink($reportFile);
            }
        }
    }

    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    private function substitute(array $command, string $placeholder, string $value): array
    {
        return array_map(
            static fn (string $arg): string => str_replace($placeholder, $value, $arg),
            $command,
        );
    }

    /**
     * @param  list<string>  $command
     */
    private function spec(array $command, StepPlan $plan, Context $context): ProcessSpec
    {
        return new ProcessSpec(
            command: $command,
            workingDirectory: $context->projectRoot(),
            env: $plan->env,
        );
    }

    private function missingToolReason(string $binary, ?string $requireHint): string
    {
        if ($requireHint !== null) {
            return sprintf('"%s" is not installed. Run: composer require --dev %s', $binary, $requireHint);
        }

        return sprintf('"%s" is not installed.', $binary);
    }

    private function stripDeprecations(ProcessResult $result): ProcessResult
    {
        return new ProcessResult(
            $result->exitCode,
            $this->withoutDeprecationLines($result->stdout),
            $this->withoutDeprecationLines($result->stderr),
        );
    }

    private function withoutDeprecationLines(string $output): string
    {
        $lines = array_filter(
            explode("\n", $output),
            static fn (string $line): bool => ! str_starts_with($line, 'PHP Deprecated:') && ! str_starts_with($line, 'Deprecated:'),
        );

        return implode("\n", $lines);
    }
}
