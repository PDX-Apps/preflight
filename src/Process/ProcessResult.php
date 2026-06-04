<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Process;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;

/**
 * The raw outcome of executing a {@see ProcessSpec}: exit code and captured streams.
 *
 * An {@see OutputParser} turns this into normalized
 * {@see Finding}s; stdout and stderr are kept separate because tools
 * vary in which stream carries their machine-readable report.
 */
final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return $this->exitCode !== 0;
    }

    public function combinedOutput(): string
    {
        return $this->stdout . $this->stderr;
    }
}
