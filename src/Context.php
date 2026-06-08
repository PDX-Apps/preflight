<?php

declare(strict_types=1);

namespace PdxApps\Preflight;

use PdxApps\Preflight\Support\ConfigResolver;
use PdxApps\Preflight\Support\CoverageDriver;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Support\Tool;

/**
 * The resolved environment a run executes against: project root, config resolution,
 * tool location, and the scope ({@see TargetSet}).
 *
 * Steps read everything they need from the Context and never touch globals or the
 * filesystem directly for these concerns, which keeps them pure and makes scope flags
 * (--files, --dirty, --module) and the in-process API behave identically.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") A read-only accessor hub for a run's
 *   environment — root, scope, config, tools, coverage — whose getters legitimately exceed
 *   the default cap.
 */
final readonly class Context
{
    private ConfigResolver $configResolver;

    /**
     * @param (\Closure(): array<string, list<array{int, int}>>)|array<string, list<array{int, int}>> $changedLines
     *        the changed line ranges, or a closure to compute them lazily (so the git diff only
     *        runs when a step actually reads patch coverage)
     */
    public function __construct(
        private string $projectRoot,
        private TargetSet $targets,
        private ?CoverageDriver $coverageDriver = null,
        private \Closure|array $changedLines = [],
    ) {
        $this->configResolver = new ConfigResolver($projectRoot);
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    /**
     * The active code-coverage driver, or null if none is available — detected at the
     * composition root and passed in, so steps that support coverage can adapt without
     * reading global PHP state themselves.
     */
    public function coverageDriver(): ?CoverageDriver
    {
        return $this->coverageDriver;
    }

    /**
     * The changed line ranges per file for this run, used for line-level patch coverage.
     * Empty unless the run is scoped to a change (`--since`/`--dirty`). Computed on first
     * access when given a closure, so the git diff is skipped if no step needs it.
     *
     * @return array<string, list<array{int, int}>>
     */
    public function changedLines(): array
    {
        return $this->changedLines instanceof \Closure ? ($this->changedLines)() : $this->changedLines;
    }

    public function configPath(?string $reference): ?string
    {
        return $this->configResolver->resolve($reference);
    }

    public function configExists(?string $reference): bool
    {
        return $this->configResolver->exists($reference);
    }

    public function toolPath(Tool $tool): string
    {
        return $tool->resolvePath($this->projectRoot);
    }

    /**
     * Whether a project-relative path (file or directory) exists on disk. Used by steps that
     * default to conventional source directories, so they can skip ones a given project lacks
     * (e.g. `src` in a Laravel app that uses `app`).
     */
    public function pathExists(string $relative): bool
    {
        return file_exists(rtrim($this->projectRoot, '/') . '/' . ltrim($relative, '/'));
    }

    /**
     * Whether the tool can actually be invoked. A Composer plugin is available when its
     * package is installed; vendor-bin tools must exist on disk; system tools are assumed
     * resolvable on the PATH.
     */
    public function toolAvailable(Tool $tool): bool
    {
        if ($tool->pluginPackage !== null) {
            return is_dir(rtrim($this->projectRoot, '/') . '/vendor/' . $tool->pluginPackage);
        }

        if (! $tool->inVendorBin) {
            return true;
        }

        return is_file($this->toolPath($tool));
    }

    public function targets(): TargetSet
    {
        return $this->targets;
    }

    public function isNarrowed(): bool
    {
        return $this->targets->isNarrowed();
    }

    /**
     * @return list<string>
     */
    public function pathsFor(Targeting $targeting): array
    {
        return $this->targets->pathsFor($targeting);
    }
}
