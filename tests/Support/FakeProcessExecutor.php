<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Support;

use Closure;
use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Process\ProcessSpec;

/**
 * A {@see ProcessExecutor} that returns canned results and records what it was asked to
 * run, so runner behavior can be asserted without spawning real processes.
 */
final class FakeProcessExecutor implements ProcessExecutor
{
    /** @var list<ProcessResult> */
    private array $queue = [];

    /** @var list<ProcessSpec> */
    public array $executed = [];

    /** @var (Closure(ProcessSpec): void)|null Side effect run on each execute (e.g. write a report file). */
    private ?Closure $onExecute = null;

    public function queue(ProcessResult $result): self
    {
        $this->queue[] = $result;

        return $this;
    }

    public function queueSuccess(string $stdout = ''): self
    {
        return $this->queue(new ProcessResult(0, $stdout, ''));
    }

    public function queueFailure(int $exitCode = 1, string $stdout = '', string $stderr = ''): self
    {
        return $this->queue(new ProcessResult($exitCode, $stdout, $stderr));
    }

    /**
     * Register a side effect to run on each execute — e.g. writing a report file to the
     * temp path the runner substituted into the command.
     *
     * @param  Closure(ProcessSpec): void  $callback
     */
    public function onExecute(Closure $callback): self
    {
        $this->onExecute = $callback;

        return $this;
    }

    public function execute(ProcessSpec $spec): ProcessResult
    {
        $this->executed[] = $spec;

        if ($this->onExecute !== null) {
            ($this->onExecute)($spec);
        }

        return array_shift($this->queue) ?? new ProcessResult(0, '', '');
    }

    /**
     * @return list<list<string>> the argv of every executed command, in order
     */
    public function commands(): array
    {
        return array_map(static fn (ProcessSpec $s): array => $s->command, $this->executed);
    }
}
