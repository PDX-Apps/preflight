<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Runner;

use Closure;
use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Process\ProcessSpec;
use Symfony\Component\Process\Process;

/**
 * The production {@see ProcessExecutor}: runs a {@see ProcessSpec} via Symfony Process.
 *
 * Captures stdout and stderr separately (tools differ in which stream carries their
 * machine-readable report). An optional output callback receives streamed chunks as they
 * arrive, so the console can show live progress while the full output is still captured.
 */
final readonly class SymfonyProcessExecutor implements ProcessExecutor
{
    /**
     * @param  (Closure(string $type, string $chunk): void)|null  $onOutput
     */
    public function __construct(
        private ?Closure $onOutput = null,
    ) {
    }

    public function execute(ProcessSpec $spec): ProcessResult
    {
        $process = new Process(
            command: $spec->command,
            cwd: $spec->workingDirectory,
            env: $spec->env === [] ? null : $spec->env,
            timeout: $spec->timeout,
        );

        $stdout = '';
        $stderr = '';

        $process->run(function (string $type, string $chunk) use (&$stdout, &$stderr): void {
            if ($type === Process::ERR) {
                $stderr .= $chunk;
            } else {
                $stdout .= $chunk;
            }

            if ($this->onOutput instanceof \Closure) {
                ($this->onOutput)($type, $chunk);
            }
        });

        return new ProcessResult($process->getExitCode() ?? 1, $stdout, $stderr);
    }
}
