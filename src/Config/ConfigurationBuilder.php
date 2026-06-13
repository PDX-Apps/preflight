<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Config;

use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\OutputFormat;
use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Steps\AbstractStep;

/**
 * The fluent surface a `preflight.php` config file uses (via {@see Preflight::configure()}).
 *
 * Steps are immutable instances, referenced by class for defaults or configured inline:
 *
 *     return Preflight::configure()
 *         ->withSteps([Pint::class, Phpstan::make()->memoryLimit('1G')])  // explicit set + order
 *         ->tune(Psalm::make()->config('psalm.xml'))                       // overlay onto the set
 *         ->without(Phpmd::class);                                         // drop one
 *
 * Use {@see withSteps()} to take full control of the set and order; use {@see addSteps()},
 * {@see tune()} / {@see without()} to adjust the default (auto-detected) set without
 * re-listing it.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") A fluent builder is one public method per
 * configurable option by design.
 */
final class ConfigurationBuilder
{
    /** @var list<string>|null */
    private ?array $paths = null;

    private ?ModuleConfig $modules;

    /** @var list<string> */
    private array $skip = [];

    /** @var list<string> */
    private array $only = [];

    /** @var list<Step>|null */
    private ?array $steps = null;

    /** @var list<Step> */
    private array $added = [];

    /** @var array<class-string<Step>, Step> */
    private array $tunes = [];

    /** @var list<class-string<Step>> */
    private array $without = [];

    private bool $failFast = false;

    private OutputFormat $defaultFormat = OutputFormat::Auto;

    private bool $fixByDefault = false;

    private bool $dirtyByDefault = false;

    /** @var list<string> */
    private array $exclude = [];

    public function __construct()
    {
        $this->modules = ModuleConfig::default();
    }

    /**
     * @param  list<string>  $paths
     */
    public function withPaths(array $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    public function withModules(string $dir = 'Modules', string $app = 'app', string $tests = 'tests'): self
    {
        $this->modules = new ModuleConfig($dir, $app, $tests);

        return $this;
    }

    public function withoutModules(): self
    {
        $this->modules = null;

        return $this;
    }

    /**
     * Drop these steps from the resolved set, by step name (e.g. `['phpmd']`).
     *
     * @param  list<string>  $skip
     */
    public function withSkip(array $skip): self
    {
        $this->skip = $skip;

        return $this;
    }

    /**
     * Run only these steps, by step name, dropping all others (e.g. `['phpstan', 'test']`).
     *
     * @param  list<string>  $only
     */
    public function only(array $only): self
    {
        $this->only = $only;

        return $this;
    }

    /**
     * Set the explicit, ordered set of steps, replacing the auto-detected default set.
     * Each entry is a step class (defaults) or a configured step instance.
     *
     * @param  list<class-string<Step>|Step>  $steps
     */
    public function withSteps(array $steps): self
    {
        $this->steps = array_map($this->normalize(...), $steps);

        return $this;
    }

    /**
     * Append extra steps to whichever base set applies — the auto-detected defaults, or an
     * explicit {@see withSteps()} list — without re-listing it. Use this for "the defaults
     * plus a couple more", e.g. the opt-in steps:
     *
     *     return Preflight::configure()->addSteps([Deptrac::class]);
     *
     * Each entry is a step class (defaults) or a configured instance, added in order after
     * the base set. A class already in the base keeps its position and instance; use
     * {@see tune()} to reconfigure it.
     *
     * @param  list<class-string<Step>|Step>  $steps
     */
    public function addSteps(array $steps): self
    {
        $this->added = [...$this->added, ...array_map($this->normalize(...), $steps)];

        return $this;
    }

    /**
     * Overlay a configured step onto whichever set applies, matched by class: it replaces
     * a same-class step already in the set, or is appended if absent. Use this to tweak
     * one step while keeping the rest of the default pipeline.
     */
    public function tune(Step $step): self
    {
        $this->tunes[$step::class] = $step;

        return $this;
    }

    /**
     * Drop a step class from the resolved set (wins over a tune for the same class).
     *
     * @param  class-string<Step>  $class
     */
    public function without(string $class): self
    {
        $this->without[] = $class;

        return $this;
    }

    public function failFast(bool $failFast = true): self
    {
        $this->failFast = $failFast;

        return $this;
    }

    /**
     * Drop findings whose file matches any of these paths, across every tool — useful for
     * framework-scaffolded code the analysers misjudge (e.g. service providers, Fortify
     * actions). A pattern matches a finding's project-relative file when it equals it, is a
     * parent directory of it, or matches it as a glob (`fnmatch`), e.g.
     * `'app/Providers'`, `'app/Actions/Fortify'`, `'database/*'`.
     *
     * Because Preflight normalizes every tool's output to a common finding, this works for all
     * of them — including ones with no CLI exclude (PHPStan, Psalm, Rector). A step whose only
     * findings were excluded passes; the tools still scan the files, their findings are just
     * not reported. Calls accumulate.
     *
     * @param  list<string>  $paths
     */
    public function exclude(array $paths): self
    {
        $this->exclude = [...$this->exclude, ...$paths];

        return $this;
    }

    public function defaultFormat(OutputFormat|string $format): self
    {
        $this->defaultFormat = $format instanceof OutputFormat ? $format : OutputFormat::from($format);

        return $this;
    }

    /**
     * Apply fixes by default, so a bare `preflight` run fixes what it can. A run can still
     * be forced back to check-only with the CLI `--check` flag.
     */
    public function fixByDefault(bool $fix = true): self
    {
        $this->fixByDefault = $fix;

        return $this;
    }

    /**
     * Scope to working-tree changes by default, so a bare `preflight` run only checks what
     * you touched. A run can still be widened to everything with the CLI `--all` flag.
     */
    public function dirtyByDefault(bool $dirty = true): self
    {
        $this->dirtyByDefault = $dirty;

        return $this;
    }

    /**
     * The autonomous-agent preset: scope to changes, auto-fix what's fixable, and emit the
     * agent output format — so a bare `preflight` run does the right thing for an agent.
     * Equivalent to dirtyByDefault() + fixByDefault() + defaultFormat('agent'); each can
     * still be overridden afterwards or via CLI flags.
     *
     * Pass `dirty: false` to keep the auto-fix + agent-format behaviour but check the whole
     * project rather than only working-tree changes.
     */
    public function forAgents(bool $dirty = true): self
    {
        return $this->dirtyByDefault($dirty)->fixByDefault()->defaultFormat(OutputFormat::Agent);
    }

    public function build(): Configuration
    {
        return new Configuration(
            steps: $this->steps,
            modules: $this->modules,
            skip: $this->skip,
            added: $this->added,
            tunes: $this->tunes,
            without: $this->without,
            failFast: $this->failFast,
            defaultFormat: $this->defaultFormat,
            paths: $this->paths,
            fixByDefault: $this->fixByDefault,
            dirtyByDefault: $this->dirtyByDefault,
            exclude: $this->exclude,
            only: $this->only,
        );
    }

    /**
     * @param  class-string<Step>|Step  $step
     */
    private function normalize(string|Step $step): Step
    {
        if ($step instanceof Step) {
            return $step;
        }

        if (is_subclass_of($step, AbstractStep::class)) {
            return $step::make();
        }

        return new $step();
    }
}
