<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

use PdxApps\Preflight\Targeting;

/**
 * The resolved scope for a run: either the whole project (each tool uses its own
 * configured scope) or an explicit narrowing to specific files/directories produced by
 * scope flags (--files, --dirty, --module, --include/--exclude).
 *
 * Adapts that scope to each step's {@see Targeting}, so steps never reason about scope
 * themselves.
 */
final readonly class TargetSet
{
    /**
     * @param  list<Target>  $targets
     */
    private function __construct(
        /** @var list<Target> */
        public array $targets,
        public bool $narrowed,
    ) {
    }

    public static function wholeProject(): self
    {
        return new self([], false);
    }

    /**
     * @param  list<Target>  $targets
     */
    public static function narrowed(array $targets): self
    {
        return new self($targets, true);
    }

    public function isNarrowed(): bool
    {
        return $this->narrowed;
    }

    public function isEmpty(): bool
    {
        return $this->targets === [];
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        return $this->pathsWhere(static fn (Target $t): bool => $t->isFile);
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return $this->pathsWhere(static fn (Target $t): bool => $t->isDirectory);
    }

    /**
     * The CLI path arguments to pass to a step with the given targeting. Empty when the
     * set is the whole project (the tool uses its configured scope) or the step is Whole.
     *
     * @return list<string>
     */
    public function pathsFor(Targeting $targeting): array
    {
        if (! $this->narrowed || $targeting === Targeting::Whole) {
            return [];
        }

        if ($targeting === Targeting::Files) {
            return array_map(static fn (Target $t): string => $t->path, $this->targets);
        }

        // Paths: widen files to containing directories, dedupe, preserve first-seen order.
        $dirs = [];
        foreach ($this->targets as $target) {
            $dir = $target->containingDirectory();
            if (! in_array($dir, $dirs, true)) {
                $dirs[] = $dir;
            }
        }

        return $dirs;
    }

    /**
     * Whether a step with this targeting must be skipped because the run is narrowed to a
     * subset the tool cannot honor.
     */
    public function forcesSkip(Targeting $targeting): bool
    {
        return $this->narrowed && ! $targeting->canScope();
    }

    /**
     * @param  callable(Target): bool  $predicate
     * @return list<string>
     */
    private function pathsWhere(callable $predicate): array
    {
        $paths = [];
        foreach ($this->targets as $target) {
            if ($predicate($target)) {
                $paths[] = $target->path;
            }
        }

        return $paths;
    }
}
