<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit\Fixtures;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Steps\AbstractStep;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * A minimal real {@see AbstractStep} subclass used to verify the immutable step builder:
 * make() defaults, clone-based with* methods (including a subclass-only property), and a
 * plan() that reads the step's own resolved settings.
 */
class ConfigurableStep extends AbstractStep
{
    private int $level = 1;

    public function label(): string
    {
        return 'Configurable';
    }

    public function tool(): ?Tool
    {
        return Tool::vendorBin('configurable');
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
        return Targeting::Files;
    }

    /**
     * Subclass-specific immutable setter, to prove cloning carries derived properties.
     */
    public function level(int $level): static
    {
        $clone = clone $this;
        $clone->level = $level;

        return $clone;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $command = ['configurable'];

        if ($this->configReference() !== null) {
            $command[] = '--config=' . $context->configPath($this->configReference());
        }

        if ($this->level !== 1) {
            $command[] = '--level=' . $this->level;
        }

        return StepPlan::command($this->name(), [...$command, ...$this->extraArgs()]);
    }
}
