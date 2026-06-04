<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Contracts;

use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Process\ProcessSpec;

/**
 * Executes a {@see ProcessSpec} and returns its {@see ProcessResult}.
 *
 * This is the seam between the runner and the operating system. The real implementation
 * wraps Symfony Process; tests substitute a fake so runner logic is verified without
 * spawning processes.
 */
interface ProcessExecutor
{
    public function execute(ProcessSpec $spec): ProcessResult;
}
