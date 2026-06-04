<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\RectorParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * Rector — automated refactoring.
 *
 * Check mode runs `rector process --dry-run` (reports the refactorings it would make, and
 * exits 2 when any are pending); fix mode runs `rector process` (applies them). Both use
 * `--output-format=json --no-progress-bar` so {@see RectorParser} gets a clean document —
 * the parser is handed the mode so a diff is a finding when checking and a changed file
 * when fixing. A root `rector.php` is used when present (Rector requires a config to know
 * its rule set).
 */
final class Rector extends AbstractStep
{
    public function label(): string
    {
        return 'Rector';
    }

    public function defaultConfig(): string
    {
        return 'rector.php';
    }

    public function tool(): Tool
    {
        return Tool::vendorBin('rector', 'rector/rector');
    }

    /**
     * @return list<Mode>
     */
    public function modes(): array
    {
        return [Mode::Check, Mode::Fix];
    }

    public function targeting(): Targeting
    {
        return Targeting::Files;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $command = [
            $context->toolPath($this->tool()),
            'process',
            '--output-format=json',
            '--no-progress-bar',
        ];

        if ($mode === Mode::Check) {
            $command[] = '--dry-run';
        }

        $config = $this->effectiveConfig();
        if ($config !== null && $context->configExists($config)) {
            $command[] = '--config=' . $context->configPath($config);
        }

        $command = [
            ...$command,
            ...$context->pathsFor($this->targeting()),
            ...$this->extraArgs(),
        ];

        $plan = StepPlan::command($this->name(), $command)
            ->parseWith(new RectorParser($mode, $this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }
}
