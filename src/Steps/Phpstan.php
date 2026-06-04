<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PhpstanParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * PHPStan — static analysis. Check-only (it reports, it does not fix).
 *
 * Runs `phpstan analyse --error-format=json --no-progress` so the output is a clean JSON
 * document for {@see PhpstanParser}. A root `phpstan.neon` (or `phpstan.neon.dist`) is used
 * when present. An explicit {@see level()} always wins (it is passed as `--level`, which
 * overrides the neon's level); with no explicit level and no config file, a default level is
 * applied so a zero-config run still does something useful. {@see memoryLimit()} tunes
 * `--memory-limit`.
 */
final class Phpstan extends AbstractStep
{
    /** Config filenames to look for, in order of preference. */
    private const array CONFIG_CANDIDATES = ['phpstan.neon', 'phpstan.neon.dist'];

    /** The level used only as a zero-config fallback (no explicit level, no config file). */
    private const int DEFAULT_LEVEL = 5;

    private ?int $level = null;

    private ?string $memoryLimit = null;

    public function label(): string
    {
        return 'PHPStan';
    }

    public function defaultConfig(): string
    {
        return 'phpstan.neon';
    }

    public function tool(): Tool
    {
        return Tool::vendorBin('phpstan', 'phpstan/phpstan');
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
     * Set the analysis level (0–9). An explicit level always takes precedence — it is passed
     * as `--level` even when a `phpstan.neon` is present, overriding the file's level.
     */
    public function level(int $level): static
    {
        $clone = clone $this;
        $clone->level = $level;

        return $clone;
    }

    public function memoryLimit(string $limit): static
    {
        $clone = clone $this;
        $clone->memoryLimit = $limit;

        return $clone;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $command = [
            $context->toolPath($this->tool()),
            'analyse',
            '--error-format=json',
            '--no-progress',
        ];

        $config = $this->resolveConfig($context);
        if ($config !== null) {
            $command[] = '--configuration=' . $context->configPath($config);
        }

        if ($this->level !== null) {
            // An explicit level wins, overriding the config file's level when both exist.
            $command[] = '--level=' . $this->level;
        } elseif ($config === null) {
            // No explicit level and no config file: apply the zero-config default.
            $command[] = '--level=' . self::DEFAULT_LEVEL;
        }

        if ($this->memoryLimit !== null) {
            $command[] = '--memory-limit=' . $this->memoryLimit;
        }

        $command = [
            ...$command,
            ...$context->pathsFor($this->targeting()),
            ...$this->extraArgs(),
        ];

        $plan = StepPlan::command($this->name(), $command)
            ->parseWith(new PhpstanParser($context->projectRoot(), $this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }

    /**
     * The config file to use: an explicit {@see config()} override if it exists, else the
     * first present default candidate, else null (command-line level instead).
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
