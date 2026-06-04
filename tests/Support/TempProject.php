<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Support;

/**
 * Creates a throwaway directory tree for tests that need a real filesystem layout
 * (project root discovery, config resolution, file scanning). Cleaned up on destruct.
 */
final class TempProject
{
    public readonly string $root;

    public function __construct()
    {
        $base = sys_get_temp_dir() . '/preflight-test-' . bin2hex(random_bytes(6));
        mkdir($base, 0o777, true);
        $this->root = $base;
    }

    /**
     * Create a file (and any parent directories) relative to the project root.
     */
    public function file(string $relativePath, string $contents = ''): string
    {
        $full = $this->root . '/' . ltrim($relativePath, '/');
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($full, $contents);

        return $full;
    }

    public function dir(string $relativePath): string
    {
        $full = $this->root . '/' . ltrim($relativePath, '/');
        if (! is_dir($full)) {
            mkdir($full, 0o777, true);
        }

        return $full;
    }

    public function __destruct()
    {
        $this->delete($this->root);
    }

    private function delete(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        if (is_dir($path) && ! is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->delete($path . '/' . $entry);
                }
            }
            rmdir($path);

            return;
        }

        unlink($path);
    }
}
