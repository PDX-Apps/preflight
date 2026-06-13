<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * Generates starter config-file content for the tools the install command sets up.
 *
 * Each config maps to a stub under `stubs/configs/`. Tools whose config must list real
 * directories (phpstan, phpcs, rector) carry a `{{ paths }}`/`{{ files }}` token that's
 * filled from the project's actual source dirs ({@see sourcePaths()}); the rest are copied
 * verbatim. Keeping the templates as real files — not inline heredocs — makes them lintable
 * and free of string-escaping.
 */
final readonly class ConfigScaffolder
{
    /** Candidate source directories, in the order they should appear when present. */
    private const array CANDIDATE_DIRS = ['app', 'src', 'tests'];

    /** Config filename => the stub that backs it, relative to the stubs/configs dir. */
    private const array STUBS = [
        'phpstan.neon' => 'phpstan.neon.stub',
        'phpcs.xml' => 'phpcs.xml.stub',
        'rector.php' => 'rector.php.stub',
        'pint.json' => 'pint.json.stub',
        'phpmd.xml' => 'phpmd.xml.stub',
    ];

    public function __construct(private string $projectRoot)
    {
    }

    /**
     * Existing source directories to scan, or `['src']` as a fallback when none are present.
     *
     * @return list<string>
     */
    public function sourcePaths(): array
    {
        $found = array_values(array_filter(
            self::CANDIDATE_DIRS,
            fn (string $dir): bool => is_dir(rtrim($this->projectRoot, '/') . '/' . $dir),
        ));

        return $found !== [] ? $found : ['src'];
    }

    /**
     * Starter content for the given config filename, or null if it isn't one we scaffold.
     */
    public function contentsFor(string $file): ?string
    {
        $stub = self::STUBS[$file] ?? null;
        if ($stub === null) {
            return null;
        }

        $template = (string) file_get_contents($this->stubDir() . '/' . $stub);

        return strtr($template, [
            '{{ paths }}' => $this->pathList($file),
            '{{ files }}' => $this->fileList(),
        ]);
    }

    /** Format the source paths the way the given config expects, one per line. */
    private function pathList(string $file): string
    {
        $format = match ($file) {
            'phpstan.neon' => static fn (string $p): string => '        - ' . $p,
            'rector.php' => static fn (string $p): string => "        __DIR__ . '/" . $p . "',",
            default => static fn (string $p): string => $p,
        };

        return implode("\n", array_map($format, $this->sourcePaths()));
    }

    /** PHPCS lists source dirs as `<file>` elements. */
    private function fileList(): string
    {
        return implode("\n", array_map(
            static fn (string $p): string => '    <file>' . $p . '</file>',
            $this->sourcePaths(),
        ));
    }

    private function stubDir(): string
    {
        return dirname(__DIR__, 2) . '/stubs/configs';
    }
}
