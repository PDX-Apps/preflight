<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Scope;

use PdxApps\Preflight\Config\ModuleConfig;
use PdxApps\Preflight\Support\GitFiles;
use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;

/**
 * Turns a {@see ScopeRequest} into a {@see TargetSet} the engine runs against.
 *
 * Precedence (most specific first): explicit `--files` win outright and git is never
 * consulted; otherwise positional paths, `--dirty` (working-tree changes), `--since`
 * (changes vs a ref), and `--module` (a module's app/tests dirs) each contribute
 * file/dir targets. An empty request is the whole project. A scope that resolves to no
 * files is still a *narrowed* set — so steps that cannot scope to a subset skip themselves
 * rather than running against everything.
 */
final readonly class ScopeResolver
{
    public function __construct(
        private GitFiles $git = new GitFiles(),
    ) {
    }

    public function resolve(ScopeRequest $request, string $projectRoot, ?ModuleConfig $modules = null): TargetSet
    {
        if ($request->isEmpty()) {
            return TargetSet::wholeProject();
        }

        if ($request->files !== []) {
            return TargetSet::narrowed($this->fileTargets($request->files));
        }

        $targets = [];

        foreach ($request->paths as $path) {
            $targets[] = $this->classify($projectRoot, $path);
        }

        if ($request->dirty) {
            $targets = [...$targets, ...$this->fileTargets($this->git->dirty($projectRoot))];
        }

        if ($request->since !== null) {
            $targets = [...$targets, ...$this->fileTargets($this->git->since($projectRoot, $request->since))];
        }

        if ($request->module !== null) {
            $targets = [...$targets, ...$this->moduleTargets($request->module, $modules, $projectRoot)];
        }

        return TargetSet::narrowed($targets);
    }

    /**
     * The app and tests directories of a named module, per the project's module layout —
     * only those that actually exist on disk, so a missing or partial module narrows to an
     * empty set (tools skip cleanly) rather than passing non-existent paths to every tool.
     * Without a {@see ModuleConfig} (modules disabled), the module can't be located.
     *
     * @return list<Target>
     */
    private function moduleTargets(string $module, ?ModuleConfig $modules, string $projectRoot): array
    {
        if (! $modules instanceof ModuleConfig) {
            return [];
        }

        $base = $modules->dir . '/' . $module;
        $root = rtrim($projectRoot, '/') . '/';

        $targets = [];
        foreach ([$modules->app, $modules->tests] as $subdir) {
            $relative = $base . '/' . $subdir;
            if (is_dir($root . $relative)) {
                $targets[] = Target::directory($relative);
            }
        }

        return $targets;
    }

    /**
     * @param list<string> $files
     * @return list<Target>
     */
    private function fileTargets(array $files): array
    {
        return array_map(Target::file(...), $files);
    }

    /**
     * Classify a positional path against the filesystem; a directory on disk becomes a
     * directory target, anything else a file target.
     */
    private function classify(string $projectRoot, string $path): Target
    {
        $absolute = rtrim($projectRoot, '/') . '/' . ltrim($path, '/');

        return is_dir($absolute) ? Target::directory($path) : Target::file($path);
    }
}
