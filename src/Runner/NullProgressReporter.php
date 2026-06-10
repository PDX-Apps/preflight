<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Runner;

use PdxApps\Preflight\Contracts\ProgressReporter;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Result\StepResult;

/**
 * The default {@see ProgressReporter}: ignores every event.
 *
 * Lets the runner call the reporter unconditionally — a programmatic run that wants no
 * live output simply gets this and pays nothing.
 */
final class NullProgressReporter implements ProgressReporter
{
    public function stepStarted(Step $step): void
    {
    }

    public function stepFinished(StepResult $result): void
    {
    }
}
