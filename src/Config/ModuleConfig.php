<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Config;

/**
 * Where modular code lives, for projects laid out as `<dir>/<Module>/<app>` and
 * `<dir>/<Module>/<tests>` (the nwidart/laravel-modules convention by default).
 */
final readonly class ModuleConfig
{
    public function __construct(
        public string $dir,
        public string $app,
        public string $tests,
    ) {
    }

    public static function default(): self
    {
        return new self(dir: 'Modules', app: 'app', tests: 'tests');
    }
}
