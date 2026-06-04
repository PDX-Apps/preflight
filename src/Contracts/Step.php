<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Contracts;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Steps\Concerns\DerivesName;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * A single check in the pipeline (formatting, static analysis, tests, a custom audit…).
 *
 * A step describes work; it never executes it. {@see plan()} returns a {@see StepPlan}
 * the {@see Runner} runs. Built-in and user-authored steps implement this identically;
 * the {@see DerivesName} trait supplies {@see name()}.
 */
interface Step
{
    /**
     * Stable identifier used by --only/--skip, the summary table, and JSON output.
     */
    public function name(): string;

    /**
     * Human-readable label for display, e.g. "PHPStan Analysis".
     */
    public function label(): string;

    /**
     * The external tool this step needs, or null if it shells out to nothing locatable
     * (used for doctor and graceful missing-tool skips).
     */
    public function tool(): ?Tool;

    /**
     * Modes this step supports. A check-only step omits {@see Mode::Fix}.
     *
     * @return list<Mode>
     */
    public function modes(): array;

    /**
     * How this step consumes the resolved {@see TargetSet}.
     */
    public function targeting(): Targeting;

    /**
     * The tool config file this step looks for by default (e.g. `pint.json`), or null if
     * the tool has none. Used by `doctor` to report config presence; the step's own
     * {@see config()} override still takes precedence at run time.
     */
    public function defaultConfig(): ?string;

    /**
     * Produce the plan to execute for the given context and mode. Pure: no side effects.
     */
    public function plan(Context $context, Mode $mode): StepPlan;
}
