<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Report;

use PdxApps\Preflight\Contracts\Clock;
use PdxApps\Preflight\Support\SystemClock;

/**
 * The internal freshness cache backing `--skip-if-fresh`: a small gitignored file
 * (`.preflight.cache.json` in the project root) recording the last run's input hash,
 * outcome, and time.
 *
 * A run is "fresh" — safe to skip — only when the current input hash matches the stored one
 * AND the last run passed. A failed (or absent/corrupt) cache is never fresh, so failures
 * always re-run. Lives in the project root (not vendor/, which is regenerated) so it
 * survives `composer install`.
 */
final readonly class FreshnessCache
{
    private const string FILENAME = '.preflight.cache.json';

    public function __construct(
        private string $projectRoot,
        private Clock $clock = new SystemClock(),
    ) {
    }

    public function isFresh(string $hash): bool
    {
        $entry = $this->read();

        return $entry !== null
            && ($entry['success'] ?? false) === true
            && ($entry['hash'] ?? null) === $hash;
    }

    public function store(string $hash, bool $success): void
    {
        $data = [
            'hash' => $hash,
            'success' => $success,
            'ranAt' => $this->clock->now()->format('c'),
        ];

        file_put_contents($this->path(), json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(): ?array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function path(): string
    {
        return rtrim($this->projectRoot, '/') . '/' . self::FILENAME;
    }
}
