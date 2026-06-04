<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Config;

use RuntimeException;

/**
 * Loads a project's `preflight.php` config file into a {@see Configuration}.
 *
 * The file is expected to `return` either a {@see ConfigurationBuilder}
 * (`Preflight::configure()->…`) or an already-built {@see Configuration}. When no file is
 * present, the default configuration is used so the tool works zero-config.
 */
final class ConfigLoader
{
    private const string FILENAME = 'preflight.php';

    public function load(string $projectRoot): Configuration
    {
        $path = $this->path($projectRoot);

        if (! is_file($path)) {
            return new Configuration();
        }

        $returned = require $path;

        if ($returned instanceof ConfigurationBuilder) {
            return $returned->build();
        }

        if ($returned instanceof Configuration) {
            return $returned;
        }

        throw new RuntimeException(sprintf(
            '%s must return a %s or %s, got %s.',
            self::FILENAME,
            ConfigurationBuilder::class,
            Configuration::class,
            get_debug_type($returned),
        ));
    }

    public function exists(string $projectRoot): bool
    {
        return is_file($this->path($projectRoot));
    }

    private function path(string $projectRoot): string
    {
        return rtrim($projectRoot, '/') . '/' . self::FILENAME;
    }
}
