<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Runner;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;

/**
 * Internal: the outcome of a before-command that failed, pairing the raw result with a
 * {@see Finding} describing it for the step's report.
 *
 * @internal
 */
final readonly class BeforeFailure
{
    public function __construct(
        public ProcessResult $result,
        public Finding $finding,
    ) {
    }
}
