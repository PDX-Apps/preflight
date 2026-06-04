<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Process\ProcessSpec;
use PdxApps\Preflight\Support\Tool;

/**
 * Executes an {@see InstallPlan}: sets `minimum-stability: dev` when a dev-branch package was
 * approved, runs `composer require --dev`, then (unless disabled) scaffolds config files and
 * delegates to a tool's own init (psalm). Side effects go through the injected
 * {@see ProcessExecutor} and the filesystem, so it's testable; progress is returned as an
 * {@see InstallOutcome} rather than printed.
 */
final readonly class Installer
{
    public function __construct(
        private string $projectRoot,
        private ProcessExecutor $executor,
    ) {
    }

    /**
     * @param  array<string, Step>  $stepsByName  used to resolve a tool's init binary
     */
    public function apply(InstallPlan $plan, array $stepsByName, bool $writeConfigs, bool $force): InstallOutcome
    {
        $messages = [];

        if ($plan->setsMinimumStabilityDev()) {
            $messages[] = 'Setting minimum-stability to dev…';
            $this->run(['composer', 'config', 'minimum-stability', 'dev']);
            $this->run(['composer', 'config', 'prefer-stable', 'true']);
        }

        $messages[] = 'Installing: ' . implode(' ', $plan->requirements());
        $require = $this->run(['composer', 'require', '--dev', ...$plan->requirements()]);
        if (! $require->successful()) {
            $messages[] = '<fg=red>composer require failed:</>';
            $messages[] = $require->combinedOutput();

            return new InstallOutcome(false, $messages);
        }

        if ($writeConfigs) {
            $messages = [
                ...$messages,
                ...$this->scaffoldConfigs($plan, $force),
                ...$this->runInits($plan, $stepsByName),
            ];
        }

        $messages[] = '<fg=green>Done.</>';

        return new InstallOutcome(true, $messages);
    }

    /**
     * @return list<string>
     */
    private function scaffoldConfigs(InstallPlan $plan, bool $force): array
    {
        $scaffolder = new ConfigScaffolder($this->projectRoot);
        $messages = [];

        foreach ($plan->installs as $install) {
            $file = $install->recipe->configFile;
            $content = $file !== null ? $scaffolder->contentsFor($file) : null;
            if ($file === null || $content === null) {
                continue;
            }

            $path = rtrim($this->projectRoot, '/') . '/' . $file;
            if (is_file($path) && ! $force) {
                $messages[] = sprintf('  config %s: <fg=gray>exists, skipped</>', $file);

                continue;
            }

            file_put_contents($path, $content);
            $messages[] = sprintf('  config %s: <fg=green>created</>', $file);
        }

        return $messages;
    }

    /**
     * Delegate config creation to a tool's own init command where it does that better (psalm).
     *
     * @param  array<string, Step>  $stepsByName
     * @return list<string>
     */
    private function runInits(InstallPlan $plan, array $stepsByName): array
    {
        $messages = [];

        foreach ($plan->installs as $install) {
            $initArgs = $install->recipe->initArgs;
            $tool = ($stepsByName[$install->stepName] ?? null)?->tool();
            if ($initArgs === null || ! $tool instanceof Tool) {
                continue;
            }

            $messages[] = sprintf('  running %s %s…', $tool->binary, implode(' ', $initArgs));
            $this->run([rtrim($this->projectRoot, '/') . '/vendor/bin/' . $tool->binary, ...$initArgs]);
        }

        return $messages;
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): ProcessResult
    {
        return $this->executor->execute(new ProcessSpec(command: $command, workingDirectory: $this->projectRoot));
    }
}
