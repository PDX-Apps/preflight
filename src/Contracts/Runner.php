<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Contracts;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Result\RunResult;

/**
 * Executes an ordered set of steps and collects their results.
 *
 * The runner owns execution policy — sequencing, fail-fast, missing-tool and scope
 * skips, process invocation. Swapping in a parallel or caching runner requires no change
 * to any {@see Step}.
 */
interface Runner
{
    /**
     * @param  iterable<Step>  $steps
     */
    public function run(iterable $steps, Context $context, Mode $mode): RunResult;
}
