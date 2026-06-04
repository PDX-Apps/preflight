<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\DeptracParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * Deptrac — enforces architectural layer boundaries. Check-only.
 *
 * Runs `deptrac analyse --formatter=json --no-progress` for {@see DeptracParser}; each layer
 * violation becomes an error finding. A root `deptrac.yaml` (or `depfile.yaml`) defines the
 * layers and rules and is passed via `--config-file`; without one Deptrac looks for its own
 * default.
 *
 * It's opt-in — not in the default set — because it only does something once you've defined
 * an architecture (a depfile). Add it with `->withSteps([..., Deptrac::class])`; if the tool
 * isn't installed the run skips it with an install hint, like any missing tool.
 */
final class Deptrac extends AbstractStep
{
    /** Depfile names to look for, in order of preference. */
    private const array CONFIG_CANDIDATES = ['deptrac.yaml', 'depfile.yaml'];

    public function label(): string
    {
        return 'Deptrac';
    }

    public function defaultConfig(): string
    {
        return 'deptrac.yaml';
    }

    public function tool(): Tool
    {
        return Tool::vendorBin('deptrac', 'deptrac/deptrac');
    }

    /**
     * @return list<Mode>
     */
    public function modes(): array
    {
        return [Mode::Check];
    }

    public function targeting(): Targeting
    {
        return Targeting::Whole;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $command = [
            $context->toolPath($this->tool()),
            'analyse',
            '--formatter=json',
            '--no-progress',
        ];

        $config = $this->resolveConfig($context);
        if ($config !== null) {
            $command[] = '--config-file=' . $context->configPath($config);
        }

        $command = [...$command, ...$this->extraArgs()];

        $plan = StepPlan::command($this->name(), $command)
            ->parseWith(new DeptracParser($context->projectRoot(), $this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }

    /**
     * The depfile to use: an explicit {@see config()} override if it exists, else the first
     * present default candidate, else null (Deptrac falls back to its own default lookup).
     */
    private function resolveConfig(Context $context): ?string
    {
        $override = $this->configReference();
        if ($override !== null) {
            return $context->configExists($override) ? $override : null;
        }

        foreach (self::CONFIG_CANDIDATES as $candidate) {
            if ($context->configExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
