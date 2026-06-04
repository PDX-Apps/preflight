<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Process;

use PdxApps\Preflight\Contracts\Runner;

/**
 * An immutable description of a single command to execute.
 *
 * This is pure data — it deliberately does not depend on Symfony Process. A {@see Runner}
 * translates a spec into an actual process, which keeps execution policy (sequential,
 * parallel, cached) out of the steps that produce specs.
 */
final readonly class ProcessSpec
{
    /**
     * @param  list<string>  $command  Argv-style command; first element is the executable.
     * @param  array<string, string>  $env  Extra environment variables.
     * @param  float|null  $timeout  Seconds before timing out, or null for no limit.
     */
    public function __construct(
        public array $command,
        public ?string $workingDirectory = null,
        public array $env = [],
        public ?float $timeout = null,
    ) {
    }
}
