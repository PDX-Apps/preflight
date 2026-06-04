<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Scope;

/**
 * The parsed scope inputs from the CLI, before resolution: the raw intent of
 * `--files`, `--dirty`, `--since`, `--module`, and positional paths.
 *
 * A plain data carrier so {@see ScopeResolver} (which needs git and the filesystem) can be
 * tested against explicit requests, and the console layer can build one straight from flags.
 */
final readonly class ScopeRequest
{
    /**
     * @param list<string> $files explicit files (--files); most specific, wins outright
     * @param list<string> $paths positional path arguments (files or directories)
     * @param bool $dirty restrict to working-tree changes (--dirty)
     * @param ?string $since restrict to changes vs a git ref (--since=<ref>)
     * @param ?string $module restrict to a module's app/tests dirs (--module=Name)
     */
    public function __construct(
        public array $files = [],
        public array $paths = [],
        public bool $dirty = false,
        public ?string $since = null,
        public ?string $module = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->files === []
            && $this->paths === []
            && ! $this->dirty
            && $this->since === null
            && $this->module === null;
    }
}
