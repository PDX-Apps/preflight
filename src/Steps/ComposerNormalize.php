<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\ComposerNormalizeParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * Composer Normalize — keeps `composer.json` tidy and deterministic (sorted keys, consistent
 * formatting). Supports check and fix.
 *
 * Runs `composer normalize` (the ergebnis/composer-normalize plugin): check mode adds
 * `--dry-run` (exit non-zero when not normalized); fix mode rewrites the file. `--no-update-lock`
 * keeps it to `composer.json` rather than churning `composer.lock`.
 *
 * It's opt-in — not in the default set — because it depends on a Composer plugin most
 * projects don't have. Add it with `->withSteps([..., ComposerNormalize::class])`; if the
 * plugin isn't installed the run skips it with an "install" hint, like any missing tool.
 */
final class ComposerNormalize extends AbstractStep
{
    public function label(): string
    {
        return 'Composer Normalize';
    }

    public function tool(): Tool
    {
        return Tool::composerPlugin('ergebnis/composer-normalize');
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
        return Targeting::Whole;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $command = [
            $context->toolPath($this->tool()),
            'normalize',
            '--no-update-lock',
        ];

        if ($mode === Mode::Check) {
            $command[] = '--dry-run';
        }

        $command = [...$command, ...$this->extraArgs()];

        $plan = StepPlan::command($this->name(), $command)
            ->parseWith(new ComposerNormalizeParser($mode, $this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }
}
