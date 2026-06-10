<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Result\StepStatus;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The human-format building blocks: one step's line (status badge, label, timing, and any
 * findings/metrics beneath it) and the closing summary.
 *
 * Shared by {@see HumanRenderer} (which prints them all at the end) and the CLI's live
 * progress reporter (which prints each step the moment it finishes), so the streamed and
 * batched human output are guaranteed identical.
 */
final class HumanStepView
{
    public function step(StepResult $step, OutputInterface $output): void
    {
        $output->writeln(sprintf('%s  %s%s', $this->badge($step->status), $step->label, $this->timing($step)));

        if ($step->status === StepStatus::Skipped || $step->status === StepStatus::MissingTool) {
            if ($step->skipReason !== null) {
                $output->writeln(sprintf('       <comment>%s</comment>', $step->skipReason));
            }

            return;
        }

        foreach ($step->findings as $finding) {
            $output->writeln('       ' . $this->finding($finding));
        }

        foreach ($step->changed as $file) {
            $output->writeln(sprintf('       <fg=green>fixed</> <fg=cyan>%s</>', $file));
        }

        foreach ($step->metrics as $metric) {
            $output->writeln(sprintf('       <fg=gray>%s</>', $metric));
        }
    }

    public function summary(RunResult $result, OutputInterface $output): void
    {
        $parts = [
            sprintf('<fg=green>%d passed</>', count($result->passed())),
            sprintf('<fg=red>%d failed</>', count($result->failed())),
            sprintf('<fg=yellow>%d skipped</>', count($result->skipped())),
        ];

        $output->writeln(implode(', ', $parts) . sprintf('  <fg=gray>(%s)</>', $this->duration($result->totalDurationSeconds())));

        $output->writeln($result->isSuccess()
            ? '<fg=green;options=bold>✓ All checks passed.</>'
            : '<fg=red;options=bold>✗ Checks failed.</>');
    }

    private function finding(Finding $finding): string
    {
        $location = $finding->file ?? '';
        if ($finding->file !== null && $finding->line !== null) {
            $location .= ':' . $finding->line;
            if ($finding->column !== null) {
                $location .= ':' . $finding->column;
            }
        }

        $rule = $finding->rule !== null ? sprintf(' <fg=gray>(%s)</>', $finding->rule) : '';
        $where = $location !== '' ? sprintf('<fg=cyan>%s</> ', $location) : '';

        return sprintf('%s%s%s', $where, $finding->message, $rule);
    }

    private function badge(StepStatus $status): string
    {
        return match ($status) {
            StepStatus::Passed => '<fg=black;bg=green> PASS </>',
            StepStatus::Failed => '<fg=white;bg=red> FAIL </>',
            StepStatus::Skipped => '<fg=black;bg=yellow> SKIP </>',
            StepStatus::MissingTool => '<fg=black;bg=yellow> MISS </>',
        };
    }

    private function timing(StepResult $step): string
    {
        if ($step->status === StepStatus::Skipped || $step->status === StepStatus::MissingTool) {
            return '';
        }

        return sprintf(' <fg=gray>(%s)</>', $this->duration($step->durationSeconds));
    }

    private function duration(float $seconds): string
    {
        if ($seconds < 1.0) {
            return sprintf('%dms', (int) round($seconds * 1000.0));
        }

        return sprintf('%.2fs', $seconds);
    }
}
