<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\PhpcsParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * PHP_CodeSniffer — coding-standard checks that complement Pint (forbidden functions, line
 * length, sniffs Pint's fixer has no equivalent for). Supports check and fix.
 *
 * The two modes use different binaries and output formats, which {@see PhpcsParser} (handed
 * the mode) accounts for:
 * - Check: `phpcs --report=json --standard=<ruleset> <files>` — JSON.
 * - Fix:   `phpcbf --standard=<ruleset> <files>` — a text summary table (phpcbf has no JSON).
 *
 * An explicit {@see standard()} always wins; otherwise a root `phpcs.xml` (or
 * `phpcs.xml.dist`) is used as the standard when present; otherwise the default `PSR12`
 * (PHPCS always requires a standard).
 */
final class Phpcs extends AbstractStep
{
    private const array CONFIG_CANDIDATES = ['phpcs.xml', 'phpcs.xml.dist'];

    /** The standard used only as a zero-config fallback (no explicit standard, no config file). */
    private const string DEFAULT_STANDARD = 'PSR12';

    private ?string $standard = null;

    private ?int $parallel = null;

    public function label(): string
    {
        return 'PHPCS';
    }

    public function defaultConfig(): string
    {
        return 'phpcs.xml';
    }

    public function tool(): Tool
    {
        return Tool::vendorBin('phpcs', 'squizlabs/php_codesniffer');
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

    /**
     * Set the coding standard (a built-in like PSR12, or a path). An explicit standard always
     * takes precedence — it is used even when a `phpcs.xml` is present.
     */
    public function standard(string $standard): static
    {
        $clone = clone $this;
        $clone->standard = $standard;

        return $clone;
    }

    public function parallel(int $processes): static
    {
        $clone = clone $this;
        $clone->parallel = $processes;

        return $clone;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $binary = $context->toolPath(Tool::vendorBin($mode === Mode::Fix ? 'phpcbf' : 'phpcs'));

        $command = [$binary, '--standard=' . $this->resolveStandard($context)];

        if ($mode === Mode::Check) {
            $command[] = '--report=json';
        }

        if ($this->parallel !== null) {
            $command[] = '--parallel=' . $this->parallel;
        }

        $command = [
            ...$command,
            ...$this->extraArgs(),
            ...$context->pathsFor($this->targeting()),
        ];

        $plan = StepPlan::command($this->name(), $command)
            ->parseWith(new PhpcsParser($mode, $context->projectRoot(), $this->name()));

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }

    /**
     * The ruleset/standard, in precedence order: an explicit {@see standard()}, else a
     * {@see config()} override file when it exists, else a root phpcs.xml, else the default.
     */
    private function resolveStandard(Context $context): string
    {
        if ($this->standard !== null) {
            return $this->standard;
        }

        $override = $this->configReference();
        if ($override !== null && $context->configExists($override)) {
            return (string) $context->configPath($override);
        }

        foreach (self::CONFIG_CANDIDATES as $candidate) {
            if ($context->configExists($candidate)) {
                return (string) $context->configPath($candidate);
            }
        }

        return self::DEFAULT_STANDARD;
    }
}
