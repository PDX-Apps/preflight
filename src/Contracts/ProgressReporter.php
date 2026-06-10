<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Contracts;

use PdxApps\Preflight\Result\StepResult;

/**
 * Observes a run as it happens, one step at a time.
 *
 * The {@see \PdxApps\Preflight\Contracts\Runner} calls {@see stepStarted()} just before a
 * step runs and {@see stepFinished()} the moment its result is known — so a console can
 * stream live progress instead of waiting for the whole run to finish. The default is a
 * no-op ({@see \PdxApps\Preflight\Runner\NullProgressReporter}); the rendered result is
 * still the source of truth, this only narrates the wait.
 */
interface ProgressReporter
{
    public function stepStarted(Step $step): void;

    public function stepFinished(StepResult $result): void;
}
