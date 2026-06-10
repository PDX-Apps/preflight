<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Support;

use PdxApps\Preflight\Contracts\ProgressReporter;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Result\StepResult;

/**
 * A {@see ProgressReporter} double that records the start/finish events it receives, so a
 * test can assert the runner narrates steps in the right order.
 *
 * @phpstan-type Event string
 */
final class RecordingProgressReporter implements ProgressReporter
{
    /** @var list<string> */
    public array $events = [];

    public function stepStarted(Step $step): void
    {
        $this->events[] = 'started:' . $step->name();
    }

    public function stepFinished(StepResult $result): void
    {
        $this->events[] = sprintf('finished:%s:%s', $result->name, $result->status->value);
    }
}
