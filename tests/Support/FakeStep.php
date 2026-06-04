<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Support;

use Closure;
use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * A configurable {@see Step} double for runner tests.
 */
final class FakeStep implements Step
{
    /**
     * @param list<Mode> $modes
     * @param (Closure(Context, Mode): StepPlan)|StepPlan $plan
     */
    public function __construct(
        private string $name,
        private Closure|StepPlan $plan,
        private ?Tool $tool = null,
        private Targeting $targeting = Targeting::Files,
        private array $modes = [Mode::Check],
        private ?string $label = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return $this->label ?? ucfirst($this->name);
    }

    public function tool(): ?Tool
    {
        return $this->tool;
    }

    /**
     * @return list<Mode>
     */
    public function modes(): array
    {
        return $this->modes;
    }

    public function targeting(): Targeting
    {
        return $this->targeting;
    }

    public function defaultConfig(): ?string
    {
        return null;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        if ($this->plan instanceof Closure) {
            return ($this->plan)($context, $mode);
        }

        return $this->plan;
    }
}
