<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\ComposerAuditParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * Composer Audit — scans dependencies for known security advisories (CVEs). Check-only.
 *
 * Runs `composer audit --format=json --abandoned=report --locked`: a clean JSON document for
 * {@see ComposerAuditParser}. Unlike the other steps this needs no installed package —
 * `composer` itself ships the command (since 2.4) — so it runs by default in every project.
 *
 * `--locked` audits the committed `composer.lock` (the source of truth, and no install
 * needed in CI); {@see locked(false)} audits the installed packages instead.
 *
 * `--abandoned=report` surfaces abandoned packages as non-failing warnings while only real
 * advisories fail the run. Composer's exit code reflects exactly that (it omits abandoned
 * under `report`), so the step judges pass/fail by exit code, not by findings. Change the
 * policy with {@see abandoned()} (`report` | `ignore` | `fail`).
 *
 * Like the other whole-project steps it cannot scope to a file subset, so a narrowed run
 * (`--dirty`, `--files`) skips it — a dependency audit isn't tied to which files changed.
 */
final class ComposerAudit extends AbstractStep
{
    private string $abandoned = 'report';

    private bool $locked = true;

    public function label(): string
    {
        return 'Composer Audit';
    }

    public function tool(): Tool
    {
        return Tool::system('composer');
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

    /**
     * How to treat abandoned packages: `report` (warn, don't fail — the default), `ignore`
     * (omit entirely), or `fail` (treat as a failure).
     */
    public function abandoned(string $handling): static
    {
        $clone = clone $this;
        $clone->abandoned = $handling;

        return $clone;
    }

    /**
     * Audit the committed lock file (default) or, with `false`, the installed packages.
     */
    public function locked(bool $locked = true): static
    {
        $clone = clone $this;
        $clone->locked = $locked;

        return $clone;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $command = [
            $context->toolPath($this->tool()),
            'audit',
            '--format=json',
            '--abandoned=' . $this->abandoned,
        ];

        if ($this->locked) {
            $command[] = '--locked';
        }

        $command = [...$command, ...$this->extraArgs()];

        $plan = StepPlan::command($this->name(), $command)
            ->parseWith(new ComposerAuditParser($this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }
}
