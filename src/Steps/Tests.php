<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\JUnitParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * Runs the test suite via PHPUnit, Paratest, or Pest. Check-only.
 *
 * The three runners share a near-identical CLI and all emit the same JUnit XML, so the step
 * builds one command shape — `<binary> --no-coverage --log-junit=<report> [-c <config>]
 * [--filter X] [paths]` — and {@see JUnitParser} reads the report. They differ only in the
 * binary and how parallelism is requested:
 *   - paratest: `--processes=auto`
 *   - pest:     `--parallel`
 *   - phpunit:  (serial)
 *
 * {@see runner()} selects one; the default `auto` picks paratest, then pest, then phpunit by
 * what's installed — so Paratest is optional. The Laravel `php artisan config:clear` step is
 * not built in; add it with {@see before()} when needed.
 */
final class Tests extends AbstractStep
{
    /** Runner -> [binary, extra args]. 'auto' is resolved by availability. */
    private const array RUNNERS = [
        'paratest' => ['paratest', ['--processes=auto']],
        'pest' => ['pest', ['--parallel']],
        'phpunit' => ['phpunit', []],
    ];

    /** Preference order for auto-detection. */
    private const array AUTO_ORDER = ['paratest', 'pest', 'phpunit'];

    private string $runner = 'auto';

    private ?string $filter = null;

    #[\Override]
    public function name(): string
    {
        return 'test';
    }

    public function label(): string
    {
        return 'Tests';
    }

    public function defaultConfig(): string
    {
        return 'phpunit.xml';
    }

    public function tool(): Tool
    {
        // The concrete runner (paratest/pest/phpunit) is resolved at plan() time, but
        // phpunit underlies all of them — both Paratest and Pest depend on it — so its
        // presence is the right signal for whether tests can run at all (and for doctor).
        return Tool::vendorBin('phpunit', 'phpunit/phpunit');
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
     * Choose the runner: auto (default), paratest, pest, or phpunit.
     */
    public function runner(string $runner): static
    {
        $clone = clone $this;
        $clone->runner = $runner;

        return $clone;
    }

    /**
     * Run only tests matching a filter pattern (PHPUnit/Pest --filter).
     */
    public function filter(string $pattern): static
    {
        $clone = clone $this;
        $clone->filter = $pattern;

        return $clone;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        [$binary, $parallelArgs] = $this->resolveRunner($context);

        $command = [
            $context->toolPath(Tool::vendorBin($binary)),
            '--no-coverage',
            '--log-junit=' . StepPlan::REPORT_FILE,
            ...$parallelArgs,
        ];

        $config = $this->effectiveConfig();
        if ($config !== null && $context->configExists($config)) {
            $command[] = '--configuration=' . $context->configPath($config);
        }

        if ($this->filter !== null) {
            $command[] = '--filter=' . $this->filter;
        }

        $command = [
            ...$command,
            ...$this->extraArgs(),
            ...$context->pathsFor($this->targeting()),
        ];

        $plan = StepPlan::command($this->name(), $command)
            ->parseWith(new JUnitParser($context->projectRoot(), $this->name()))
            ->readingReportFile();

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }

    /**
     * Resolve the [binary, parallelArgs] to use: the explicit runner, or — for 'auto' — the
     * first installed runner in preference order (falling back to phpunit).
     *
     * @return array{0: string, 1: list<string>}
     */
    private function resolveRunner(Context $context): array
    {
        if ($this->runner !== 'auto') {
            return self::RUNNERS[$this->runner] ?? self::RUNNERS['phpunit'];
        }

        foreach (self::AUTO_ORDER as $candidate) {
            [$binary] = self::RUNNERS[$candidate];
            if ($context->toolAvailable(Tool::vendorBin($binary))) {
                return self::RUNNERS[$candidate];
            }
        }

        return self::RUNNERS['phpunit'];
    }
}
