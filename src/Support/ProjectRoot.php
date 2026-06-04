<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

use RuntimeException;

/**
 * Locates the host project root: the nearest ancestor directory containing a composer.json.
 *
 * This is what lets the package work both standalone and when installed under
 * vendor/pdxapps/preflight — discovery is based on the filesystem, not on a fixed
 * number of parent hops.
 */
final class ProjectRoot
{
    /**
     * @throws RuntimeException when no composer.json is found in the directory or its ancestors.
     */
    public static function discoverFrom(string $start): string
    {
        $dir = realpath($start);
        if ($dir === false) {
            throw new RuntimeException(sprintf('Path "%s" does not exist.', $start));
        }

        while (true) {
            if (is_file($dir . '/composer.json')) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                throw new RuntimeException(
                    sprintf('Could not locate a composer.json at or above "%s".', $start),
                );
            }

            $dir = $parent;
        }
    }
}
