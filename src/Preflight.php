<?php

declare(strict_types=1);

namespace PdxApps\Preflight;

use PdxApps\Preflight\Config\Configuration;
use PdxApps\Preflight\Config\ConfigurationBuilder;
use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Contracts\ProgressReporter;
use PdxApps\Preflight\Result\FindingExcluder;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Runner\NullProgressReporter;
use PdxApps\Preflight\Runner\SequentialRunner;
use PdxApps\Preflight\Runner\SymfonyProcessExecutor;
use PdxApps\Preflight\Steps\StepRegistry;
use PdxApps\Preflight\Support\CoverageDriver;
use PdxApps\Preflight\Support\ProjectRoot;
use PdxApps\Preflight\Support\TargetSet;

/**
 * The package's public entry point and programmatic runner.
 *
 * {@see configure()} opens the fluent builder a `preflight.php` file returns.
 * {@see make()} wraps a resolved {@see Configuration} so it can be executed in-process:
 * `Preflight::make($config)->run(Mode::Check)`. The console commands are thin shells over
 * this same runner.
 */
final readonly class Preflight
{
    private function __construct(
        private Configuration $configuration,
        private string $projectRoot,
        private ProcessExecutor $executor,
        private StepRegistry $registry,
        private ProgressReporter $progress,
    ) {
    }

    /**
     * Open the fluent configuration builder (used inside a `preflight.php`).
     */
    public static function configure(): ConfigurationBuilder
    {
        return new ConfigurationBuilder();
    }

    /**
     * Build a runner for a resolved configuration. The project root is discovered from the
     * current directory when omitted; the executor defaults to running real processes.
     */
    public static function make(
        Configuration $configuration,
        ?string $projectRoot = null,
        ?ProcessExecutor $executor = null,
        ?ProgressReporter $progress = null,
    ): self {
        return new self(
            configuration: $configuration,
            projectRoot: $projectRoot ?? ProjectRoot::discoverFrom(getcwd() ?: '.'),
            executor: $executor ?? new SymfonyProcessExecutor(),
            registry: new StepRegistry(),
            progress: $progress ?? new NullProgressReporter(),
        );
    }

    /**
     * Resolve the configured steps against the given scope and run them.
     *
     * The whole project is the default scope; narrowing (via --files/--dirty/--module) is
     * applied by the console layer, which passes a narrowed {@see TargetSet} and, for
     * line-level patch coverage, the changed line ranges.
     *
     * @param (\Closure(): array<string, list<array{int, int}>>)|array<string, list<array{int, int}>> $changedLines
     */
    public function run(Mode $mode, ?TargetSet $targets = null, \Closure|array $changedLines = []): RunResult
    {
        $context = new Context($this->projectRoot, $targets ?? TargetSet::wholeProject(), CoverageDriver::detect(), $changedLines);
        $runner = new SequentialRunner($this->executor, failFast: $this->configuration->failFast, progress: $this->progress);

        // With no explicit steps configured, fall back to every installed built-in.
        $autoSteps = $this->configuration->hasExplicitSteps() ? [] : $this->registry->installed($context);
        $steps = $this->configuration->resolveSteps($autoSteps);

        $result = $runner->run($steps, $context, $mode);

        // Drop findings from excluded paths (e.g. framework scaffolding) before the verdict.
        return (new FindingExcluder($this->configuration->exclude))->apply($result);
    }
}
