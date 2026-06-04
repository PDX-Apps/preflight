<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PhpmdParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * PHPMD — mess detector (code smells: complexity, unused code, naming, …). Check-only.
 *
 * Runs the PHPMD 3.x CLI: `phpmd analyze --format=json --ruleset=<file|names> -- <paths>`.
 * Explicit {@see rulesets()} always win; otherwise a root `phpmd.xml` is used as the ruleset
 * when present; otherwise the default rule sets
 * (cleancode/codesize/controversial/design/naming/unusedcode). PHPMD 3 parses modern PHP
 * (8.4+) and writes clean JSON to stdout (its progress bar goes to stderr), so no deprecation
 * filtering is needed and its exit code is authoritative.
 */
final class Phpmd extends AbstractStep
{
    /** Rule sets used only as a zero-config fallback (no explicit rule sets, no config file). */
    private const array DEFAULT_RULESETS = ['cleancode', 'codesize', 'controversial', 'design', 'naming', 'unusedcode'];

    /** @var list<string> */
    private array $scanPaths = ['src'];

    /** @var list<string>|null */
    private ?array $rulesets = null;

    public function label(): string
    {
        return 'PHPMD';
    }

    public function defaultConfig(): string
    {
        return 'phpmd.xml';
    }

    public function tool(): Tool
    {
        return Tool::vendorBin('phpmd', 'phpmd/phpmd');
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
        return Targeting::Paths;
    }

    /**
     * The directories PHPMD scans on a whole-project run (a narrowed run overrides these).
     *
     * @param  list<string>  $paths
     */
    public function paths(array $paths): static
    {
        $clone = clone $this;
        $clone->scanPaths = $paths;

        return $clone;
    }

    /**
     * Set the built-in rule-set names. Explicit rule sets always take precedence — they are
     * used even when a `phpmd.xml` is present.
     *
     * @param  list<string>  $rulesets
     */
    public function rulesets(array $rulesets): static
    {
        $clone = clone $this;
        $clone->rulesets = $rulesets;

        return $clone;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $command = [
            $context->toolPath($this->tool()),
            'analyze',
            '--format=json',
            '--ruleset=' . $this->resolveRuleset($context),
            ...$this->extraArgs(),
            '--',
            ...$this->resolvePaths($context),
        ];

        $plan = StepPlan::command($this->name(), $command)
            ->parseWith(new PhpmdParser($context->projectRoot(), $this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }

    /**
     * A narrowed run supplies its (dir-widened) target paths; otherwise the configured scan paths.
     *
     * @return list<string>
     */
    private function resolvePaths(Context $context): array
    {
        $targeted = $context->pathsFor($this->targeting());

        return $targeted !== [] ? $targeted : $this->scanPaths;
    }

    /**
     * The ruleset argument, in precedence order: explicit {@see rulesets()}, else a root
     * phpmd.xml (or {@see config()} override) when it exists, else the default rule sets.
     */
    private function resolveRuleset(Context $context): string
    {
        if ($this->rulesets !== null) {
            return implode(',', $this->rulesets);
        }

        $config = $this->effectiveConfig();
        if ($config !== null && $context->configExists($config)) {
            return (string) $context->configPath($config);
        }

        return implode(',', self::DEFAULT_RULESETS);
    }
}
