<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

/**
 * Computes a content hash over a run's inputs — the scoped source files plus the config
 * files (rulesets) and lock file (tool versions). Any byte change in any of them produces a
 * different hash, so it can answer "are the inputs identical to the last run?" for the
 * freshness cache.
 *
 * Content-addressed and order-independent: paths are sorted, and each path is folded in with
 * a hash of its contents (or a marker when absent, so creating/deleting a file registers).
 */
final readonly class InputHasher
{
    private const string ALGO = 'xxh128';

    private const string MISSING = '0:missing';

    public function __construct(private string $projectRoot)
    {
    }

    /**
     * @param  list<string>  $files  project-relative source files in scope
     * @param  list<string>  $configFiles  project-relative config/lock files
     */
    public function hash(array $files, array $configFiles): string
    {
        $parts = [];
        foreach ([...$files, ...$configFiles] as $relative) {
            $parts[$relative] = $relative . '=' . $this->fingerprint($relative);
        }

        ksort($parts);

        return hash(self::ALGO, implode("\n", $parts));
    }

    private function fingerprint(string $relative): string
    {
        $absolute = rtrim($this->projectRoot, '/') . '/' . ltrim($relative, '/');

        if (! is_file($absolute)) {
            return self::MISSING;
        }

        $hash = hash_file(self::ALGO, $absolute);

        return $hash === false ? self::MISSING : $hash;
    }
}
