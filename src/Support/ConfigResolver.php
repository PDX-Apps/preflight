<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

/**
 * Resolves a step's config-file reference to an absolute path.
 *
 * Bare filenames and relative paths resolve against the project root — the standard
 * location where tools and IDEs already expect their config (phpstan.neon, pint.json,
 * …). Absolute paths pass through. Null means "let the tool find its own config".
 */
final readonly class ConfigResolver
{
    public function __construct(private string $projectRoot)
    {
    }

    public function resolve(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        if ($this->isAbsolute($reference)) {
            return $reference;
        }

        return rtrim($this->projectRoot, '/') . '/' . ltrim($reference, '/');
    }

    public function exists(?string $reference): bool
    {
        $resolved = $this->resolve($reference);

        return $resolved !== null && is_file($resolved);
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path); // Windows drive paths
    }
}
