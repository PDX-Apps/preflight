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
 * Renders a {@see RunResult} as Markdown — a per-step summary table plus a findings list.
 *
 * Built for a GitHub Actions job summary (`preflight --format=markdown >> "$GITHUB_STEP_SUMMARY"`),
 * but it's plain Markdown usable anywhere. A clean run shows just the header and table; the
 * findings list appears only when there are findings.
 */
final class MarkdownRenderer implements Renderer
{
    public function render(RunResult $result, OutputInterface $output): void
    {
        $lines = [
            $this->header($result),
            '',
            '| Step | Status | Findings | Time |',
            '|------|:------:|---------:|-----:|',
        ];

        foreach ($result->steps as $step) {
            $lines[] = $this->row($step);
        }

        $findings = $result->findings();
        if ($findings !== []) {
            $lines[] = '';
            $lines[] = '### Findings';
            foreach ($findings as $finding) {
                $lines[] = $this->findingLine($finding);
            }
        }

        $output->writeln(implode("\n", $lines));
    }

    private function header(RunResult $result): string
    {
        $verdict = $result->isSuccess() ? '✓ all checks passed' : '✗ checks failed';

        return sprintf('## Preflight — %s   (%.1fs)', $verdict, $result->totalDurationSeconds());
    }

    private function row(StepResult $step): string
    {
        $ran = $step->status->didRun();

        return sprintf(
            '| %s | %s | %s | %s |',
            $step->label,
            $this->status($step->status),
            $ran ? (string) count($step->findings) : '–',
            $ran ? sprintf('%.2fs', $step->durationSeconds) : '–',
        );
    }

    private function status(StepStatus $status): string
    {
        return match ($status) {
            StepStatus::Passed => '✅',
            StepStatus::Failed => '❌',
            StepStatus::Skipped => '⏭️ skipped',
            StepStatus::MissingTool => '⚠️ not installed',
        };
    }

    private function findingLine(Finding $finding): string
    {
        $location = $finding->file !== null
            ? sprintf('`%s%s` — ', $finding->file, $finding->line !== null ? ':' . $finding->line : '')
            : '';
        $rule = $finding->rule !== null ? sprintf(' (`%s`)', $finding->rule) : '';

        return sprintf('- %s**[%s]** %s%s', $location, $finding->tool, $finding->message, $rule);
    }
}
