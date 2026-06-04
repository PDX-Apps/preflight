<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PintParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * Laravel Pint — opinionated PHP code-style fixer.
 *
 * Check mode runs `pint --test --format=json` (report only); fix mode runs
 * `pint --format=json` (rewrite files). The JSON reporter is requested so {@see PintParser}
 * gets a structured, complete file list. The root `pint.json` is used automatically when
 * present, or a custom path via {@see config()}; with neither, Pint falls back to its own
 * default preset.
 */
final class Pint extends AbstractStep
{
    public function label(): string
    {
        return 'Pint';
    }

    public function defaultConfig(): string
    {
        return 'pint.json';
    }

    public function tool(): Tool
    {
        return Tool::vendorBin('pint', 'laravel/pint');
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
        $command = [$context->toolPath($this->tool()), '--format=json'];

        $config = $this->effectiveConfig();
        if ($config !== null && $context->configExists($config)) {
            $command[] = '--config=' . $context->configPath($config);
        }

        if ($mode === Mode::Check) {
            $command[] = '--test';
        }

        $command = [
            ...$command,
            ...$context->pathsFor($this->targeting()),
            ...$this->extraArgs(),
        ];

        $plan = StepPlan::command($this->name(), $command)->parseWith(new PintParser($this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }
}
