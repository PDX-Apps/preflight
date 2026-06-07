<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Config;

use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\OutputFormat;

/**
 * The immutable, resolved result of {@see ConfigurationBuilder}: everything the engine
 * needs to plan a run, independent of any single invocation.
 *
 * Steps are immutable {@see Step} instances. `steps === null` means "auto-detect the
 * default set"; an explicit (possibly empty) list replaces it. `added` are extra steps
 * appended to whichever base set applies (defaults or explicit) without disabling
 * auto-detection. `tunes` overlay settings onto the set (matched by class), and `without`
 * drops classes — see {@see resolveSteps()}. `paths === null` means auto-detect paths;
 * `modules === null` disables module discovery.
 */
final readonly class Configuration
{
    /**
     * @param  list<Step>|null  $steps
     * @param  list<string>  $skip
     * @param  list<Step>  $added
     * @param  array<class-string<Step>, Step>  $tunes
     * @param  list<class-string<Step>>  $without
     * @param  list<string>|null  $paths
     */
    public function __construct(
        public ?array $steps = null,
        public ?ModuleConfig $modules = new ModuleConfig('Modules', 'app', 'tests'),
        public array $skip = [],
        public array $added = [],
        public array $tunes = [],
        public array $without = [],
        public bool $failFast = false,
        public OutputFormat $defaultFormat = OutputFormat::Auto,
        public ?array $paths = null,
        public bool $fixByDefault = false,
        public bool $dirtyByDefault = false,
    ) {
    }

    /**
     * A copy with fail-fast overridden — used when the CLI `--fail-fast` flag is set.
     */
    public function withFailFast(bool $failFast): self
    {
        return new self(
            steps: $this->steps,
            modules: $this->modules,
            skip: $this->skip,
            added: $this->added,
            tunes: $this->tunes,
            without: $this->without,
            failFast: $failFast,
            defaultFormat: $this->defaultFormat,
            paths: $this->paths,
            fixByDefault: $this->fixByDefault,
            dirtyByDefault: $this->dirtyByDefault,
        );
    }

    public function hasExplicitSteps(): bool
    {
        return $this->steps !== null;
    }

    public function usesModules(): bool
    {
        return $this->modules instanceof \PdxApps\Preflight\Config\ModuleConfig;
    }

    /**
     * Resolve the final ordered list of steps to run.
     *
     * Starts from the explicit list, or the given auto-detected set when none was listed,
     * then appends any `added` steps (a class already in the base keeps its position and
     * instance). Removes any class named in `without`, replaces matching classes with their
     * tuned instance (in place), and appends tuned classes not already present.
     *
     * @param  list<Step>  $autoSteps
     * @return list<Step>
     */
    public function resolveSteps(array $autoSteps = []): array
    {
        $base = $this->steps ?? $autoSteps;

        $resolved = [];
        foreach ([...$base, ...$this->added] as $step) {
            $class = $step::class;
            if (in_array($class, $this->without, true)) {
                continue;
            }
            $resolved[$class] = $this->tunes[$class] ?? $resolved[$class] ?? $step;
        }

        foreach ($this->tunes as $class => $step) {
            if (! isset($resolved[$class]) && ! in_array($class, $this->without, true)) {
                $resolved[$class] = $step;
            }
        }

        return array_values($resolved);
    }
}
