<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PsalmParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * Psalm — static analysis. Check-only.
 *
 * Runs `psalm --output-format=json --no-progress` so the output is the flat JSON array
 * {@see PsalmParser} expects. A root `psalm.xml` (or `psalm.xml.dist`) is used when present
 * (Psalm requires a config). {@see threads()} enables multi-threaded analysis (an integer,
 * not "auto"); {@see noCache()} disables Psalm's cache.
 */
final class Psalm extends AbstractStep
{
    private const array CONFIG_CANDIDATES = ['psalm.xml', 'psalm.xml.dist'];

    private ?int $threads = null;

    private bool $noCache = false;

    public function label(): string
    {
        return 'Psalm';
    }

    public function defaultConfig(): string
    {
        return 'psalm.xml';
    }

    public function tool(): Tool
    {
        return Tool::vendorBin('psalm', 'vimeo/psalm');
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
     * Run analysis across N threads (must be > 1 to have effect).
     */
    public function threads(int $threads): static
    {
        $clone = clone $this;
        $clone->threads = $threads;

        return $clone;
    }

    public function noCache(): static
    {
        $clone = clone $this;
        $clone->noCache = true;

        return $clone;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $command = [
            $context->toolPath($this->tool()),
            '--output-format=json',
            '--no-progress',
        ];

        $config = $this->resolveConfig($context);
        if ($config !== null) {
            $command[] = '--config=' . $context->configPath($config);
        }

        if ($this->threads !== null) {
            $command[] = '--threads=' . $this->threads;
        }

        if ($this->noCache) {
            $command[] = '--no-cache';
        }

        $command = [
            ...$command,
            ...$context->pathsFor($this->targeting()),
            ...$this->extraArgs(),
        ];

        $plan = StepPlan::command($this->name(), $command)->parseWith(new PsalmParser($this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }

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
