<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Contracts\Renderer;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Result\StepStatus;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders a {@see RunResult} for a human reading a terminal: one line per step with a
 * coloured status, each failure's findings indented beneath it, and a closing summary of
 * passed/failed/skipped counts plus total time.
 *
 * The default format on an interactive TTY (see {@see \PdxApps\Preflight\OutputFormat}).
 */
final class HumanRenderer implements Renderer
{
    public function render(RunResult $result, OutputInterface $output): void
    {
        $output->writeln('');

        foreach ($result->steps as $step) {
            $this->renderStep($step, $output);
        }

        $output->writeln('');
        $this->renderSummary($result, $output);
    }

    private function renderStep(StepResult $step, OutputInterface $output): void
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

    private function renderSummary(RunResult $result, OutputInterface $output): void
    {
        $passed = count($result->passed());
        $failed = count($result->failed());
        $skipped = count($result->skipped());

        $parts = [
            sprintf('<fg=green>%d passed</>', $passed),
            sprintf('<fg=red>%d failed</>', $failed),
            sprintf('<fg=yellow>%d skipped</>', $skipped),
        ];

        $output->writeln(implode(', ', $parts) . sprintf('  <fg=gray>(%s)</>', $this->duration($result->totalDurationSeconds())));

        $output->writeln($result->isSuccess()
            ? '<fg=green;options=bold>✓ All checks passed.</>'
            : '<fg=red;options=bold>✗ Checks failed.</>');
    }

    private function duration(float $seconds): string
    {
        if ($seconds < 1.0) {
            return sprintf('%dms', (int) round($seconds * 1000.0));
        }

        return sprintf('%.2fs', $seconds);
    }
}
