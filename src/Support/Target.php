<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

/**
 * A single resolved scope entry: a project-relative path known to be a file or directory.
 */
final readonly class Target
{
    private function __construct(
        public string $path,
        public bool $isFile,
        public bool $isDirectory,
    ) {
    }

    public static function file(string $path): self
    {
        return new self($path, isFile: true, isDirectory: false);
    }

    public static function directory(string $path): self
    {
        return new self($path, isFile: false, isDirectory: true);
    }

    /**
     * The directory that should represent this target when a tool only accepts directories:
     * a file's dirname, or the directory itself.
     */
    public function containingDirectory(): string
    {
        return $this->isFile ? dirname($this->path) : $this->path;
    }
}
