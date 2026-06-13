<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Console;

use PdxApps\Preflight\Contracts\ProgressReporter;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Render\HumanStepView;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Runner\NullProgressReporter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Streams the human format live: each step's result line is printed the moment it finishes,
 * instead of all of them appearing at the end. On an interactive terminal a transient
 * "running …" line shows which step is in flight, then is erased and replaced by the step's
 * permanent result line.
 *
 * The step lines come from {@see HumanStepView} — the same blocks {@see HumanRenderer} would
 * print in a batch — so the only thing streaming adds is *when* they appear. The closing
 * summary is printed by the command after the run, not here.
 */
final readonly class StreamingProgressReporter implements ProgressReporter
{
    private const string ERASE_LINE = "\r\033[2K";

    public function __construct(
        private OutputInterface $output,
        private HumanStepView $view = new HumanStepView(),
    ) {
    }

    /**
     * A reporter that narrates a machine-format run on *stderr*, keeping the stdout document
     * intact. Active only when stderr is an interactive terminal; for captured or piped output
     * — where a CI log is indistinguishable from an agent reading combined streams — it's a
     * no-op, so progress lines can never leak into a parsed payload.
     */
    public static function forMachineFormat(OutputInterface $output): ProgressReporter
    {
        if ($output instanceof ConsoleOutputInterface) {
            $stderr = $output->getErrorOutput();
            if ($stderr->isDecorated()) {
                return new self($stderr);
            }
        }

        return new NullProgressReporter();
    }

    public function stepStarted(Step $step): void
    {
        // The transient line only makes sense where we can erase it again; on a non-TTY
        // (a pipe or CI log) we skip it and just print each result line as it lands.
        if ($this->output->isDecorated()) {
            $this->output->write(sprintf('<fg=gray>  running %s…</>', $step->label()));
        }
    }

    public function stepFinished(StepResult $result): void
    {
        if ($this->output->isDecorated()) {
            $this->output->write(self::ERASE_LINE);
        }

        $this->view->step($result, $this->output);
    }
}
