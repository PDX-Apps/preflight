<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Contracts\Renderer;
use PdxApps\Preflight\Result\RunResult;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders a {@see RunResult} for a human reading a terminal: one line per step with a
 * coloured status, each failure's findings indented beneath it, and a closing summary of
 * passed/failed/skipped counts plus total time.
 *
 * The per-step and summary blocks come from {@see HumanStepView}, the same building blocks
 * the CLI streams live as each step finishes — so batched and streamed output match.
 *
 * The default format on an interactive TTY (see {@see \PdxApps\Preflight\OutputFormat}).
 */
final readonly class HumanRenderer implements Renderer
{
    public function __construct(private HumanStepView $view = new HumanStepView())
    {
    }

    public function render(RunResult $result, OutputInterface $output): void
    {
        $output->writeln('');

        foreach ($result->steps as $step) {
            $this->view->step($step, $output);
        }

        $output->writeln('');
        $this->view->summary($result, $output);
    }
}
