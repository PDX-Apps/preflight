<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Parsing\CoverageParser;
use PdxApps\Preflight\Parsing\JUnitParser;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Severity;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;

/**
 * Runs the test suite via PHPUnit, Paratest, or Pest. Check-only.
 *
 * The three runners share a near-identical CLI and all emit the same JUnit XML, so the step
 * builds one command shape — `<binary> --log-junit=<report> [coverage flags] [-c <config>]
 * [--filter X] [paths]` — and {@see JUnitParser} reads the report. They differ only in the
 * binary and how parallelism is requested:
 *   - paratest: `--processes=auto`
 *   - pest:     `--parallel`
 *   - phpunit:  (serial)
 *
 * {@see runner()} selects one; the default `auto` picks paratest, then pest, then phpunit by
 * what's installed — so Paratest is optional. The Laravel `php artisan config:clear` step is
 * not built in; add it with {@see before()} when needed.
 *
 * Coverage is **off by default** (`--no-coverage`) because it's slow and needs a driver. Opt
 * in with {@see coverage()} to emit reports and {@see minCoverage()} to fail under a line-%
 * threshold. When no coverage driver (PCOV/phpdbg/Xdebug) is active the step runs the tests
 * anyway and attaches a non-failing warning rather than erroring — see {@see plan()}.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") A fluent step builder: the Step contract
 *   plus the runner/filter/coverage/minCoverage setters legitimately exceed the default cap.
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

    /** Supported coverage report formats -> the PHPUnit/Pest flag that emits them. */
    private const array COVERAGE_FLAGS = [
        'clover' => '--coverage-clover',
        'cobertura' => '--coverage-cobertura',
        'xml' => '--coverage-xml',
        'html' => '--coverage-html',
        'php' => '--coverage-php',
        'text' => '--coverage-text',
    ];

    private string $runner = 'auto';

    private ?string $filter = null;

    /** @var array<string, ?string> Coverage format => output path (null = stdout, text only). */
    private array $coverage = [];

    private ?float $minCoverage = null;

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

    /**
     * Emit coverage reports, given as `format => path` (coverage is otherwise off). Supported
     * formats: clover, cobertura, xml, html, php, text. `text` may use a null path to print a
     * summary to stdout; the others require a path. Multiple formats run from one execution:
     *
     *     Tests::make()->coverage([
     *         'clover' => 'build/coverage.xml',
     *         'html'   => 'build/coverage',
     *     ])
     *
     * Coverage needs a driver (PCOV/phpdbg/Xdebug); without one the tests still run and a
     * non-failing warning is attached instead.
     *
     * @param  array<string, ?string>  $reports
     */
    public function coverage(array $reports): static
    {
        foreach ($reports as $format => $path) {
            if (! array_key_exists($format, self::COVERAGE_FLAGS)) {
                throw new \InvalidArgumentException(sprintf(
                    'Unknown coverage format "%s". Supported: %s.',
                    $format,
                    implode(', ', array_keys(self::COVERAGE_FLAGS)),
                ));
            }

            if ($format !== 'text' && ($path === null || $path === '')) {
                throw new \InvalidArgumentException(sprintf('Coverage format "%s" requires an output path.', $format));
            }
        }

        $clone = clone $this;
        $clone->coverage = $reports;

        return $clone;
    }

    /**
     * Fail the run if line coverage is below this percentage (0–100). Implies coverage is on.
     * Pest enforces it natively (`--min`); for PHPUnit/Paratest the step reads the percentage
     * from `--coverage-text` and fails when it falls short. With no driver active the gate is
     * skipped (a warning is attached) rather than failing — so local runs without Xdebug/PCOV
     * aren't blocked.
     */
    public function minCoverage(float $percent): static
    {
        if ($percent < 0.0 || $percent > 100.0) {
            throw new \InvalidArgumentException('minCoverage must be between 0 and 100.');
        }

        $clone = clone $this;
        $clone->minCoverage = $percent;

        return $clone;
    }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        $coverageOn = $this->coverageRequested();
        [$binary, $parallelArgs] = $this->resolveRunner($context, $coverageOn);

        $command = [
            $context->toolPath(Tool::vendorBin($binary)),
            '--log-junit=' . StepPlan::REPORT_FILE,
            ...$parallelArgs,
        ];

        $coverage = $this->coverageFor($context, $binary === 'pest');
        $command = [...$command, ...$coverage['args']];

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
            ->parseWith($coverage['parser'])
            ->readingReportFile();

        if ($coverage['env'] !== []) {
            $plan = $plan->withEnv($coverage['env']);
        }

        if ($coverage['gate']) {
            $plan = $plan->judgeByFindings();
        }

        if ($coverage['note'] instanceof Finding) {
            $plan = $plan->note($coverage['note']);
        }

        foreach ($this->beforeCommands() as $before) {
            $plan = $plan->before($before);
        }

        return $plan;
    }

    private function coverageRequested(): bool
    {
        return $this->coverage !== [] || $this->minCoverage !== null;
    }

    /**
     * Resolve coverage for this run: the extra command args, the test process env, the parser
     * (wrapped to gate on a minimum when one is set), an optional advisory note, and whether
     * to judge pass/fail by findings. Coverage is off unless requested, and degrades to a
     * non-failing warning when no driver is active.
     *
     * @return array{args: list<string>, env: array<string, string>, parser: OutputParser, note: ?Finding, gate: bool}
     */
    private function coverageFor(Context $context, bool $isPest): array
    {
        $parser = new JUnitParser($context->projectRoot(), $this->name());

        if (! $this->coverageRequested()) {
            return ['args' => ['--no-coverage'], 'env' => [], 'parser' => $parser, 'note' => null, 'gate' => false];
        }

        $driver = $context->coverageDriver();
        if (!$driver instanceof \PdxApps\Preflight\Support\CoverageDriver) {
            // Safety net: coverage was asked for but no driver is active. Run the tests
            // (still a useful gate) without coverage, and warn rather than fail.
            return ['args' => ['--no-coverage'], 'env' => [], 'parser' => $parser, 'note' => $this->noDriverWarning(), 'gate' => false];
        }

        $args = $isPest ? ['--coverage'] : [];
        $args = [...$args, ...$this->coverageReportArgs($isPest)];
        $gate = false;

        if ($this->minCoverage !== null) {
            if ($isPest) {
                $args[] = sprintf('--min=%s', $this->minCoverage);
            } else {
                $args[] = '--coverage-text=php://stdout';
                $parser = new CoverageParser($parser, $this->minCoverage, $this->name());
                $gate = true;
            }
        }

        return ['args' => $args, 'env' => $driver->env(), 'parser' => $parser, 'note' => null, 'gate' => $gate];
    }

    /**
     * The coverage report flags from {@see coverage()}. A PHPUnit/Paratest minimum owns
     * `--coverage-text` itself, so a `text` entry is skipped in that case.
     *
     * @return list<string>
     */
    private function coverageReportArgs(bool $isPest): array
    {
        $args = [];
        foreach ($this->coverage as $format => $path) {
            if ($format === 'text' && $this->minCoverage !== null && ! $isPest) {
                continue;
            }

            $args[] = $this->coverageFlag($format, $path);
        }

        return $args;
    }

    /**
     * Build the coverage flag for one format. `text` with no path goes to stdout; every other
     * format carries its (validated) path.
     */
    private function coverageFlag(string $format, ?string $path): string
    {
        if ($format === 'text') {
            return self::COVERAGE_FLAGS['text'] . '=' . ($path ?? 'php://stdout');
        }

        return self::COVERAGE_FLAGS[$format] . '=' . $path;
    }

    private function noDriverWarning(): Finding
    {
        return new Finding(
            tool: $this->name(),
            severity: Severity::Warning,
            message: 'Coverage was requested but no coverage driver (PCOV, phpdbg, or Xdebug) is active — '
                . 'ran tests without coverage. Install PCOV (pecl install pcov) or enable Xdebug coverage '
                . '(XDEBUG_MODE=coverage).',
        );
    }

    /**
     * Resolve the [binary, parallelArgs] to use: the explicit runner, or — for 'auto' — the
     * first installed runner in preference order (falling back to phpunit). When coverage is
     * on, 'auto' picks phpunit (serial) for reliable coverage; choose paratest/pest
     * explicitly to run coverage in parallel.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function resolveRunner(Context $context, bool $coverageOn): array
    {
        if ($this->runner !== 'auto') {
            return self::RUNNERS[$this->runner] ?? self::RUNNERS['phpunit'];
        }

        if ($coverageOn) {
            return self::RUNNERS['phpunit'];
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
