<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Coverage;

/**
 * Reads a Clover coverage report into per-file, per-line hit counts.
 *
 * Clover lists every executable line of every file as `<line num="N" type="stmt" count="H"/>`
 * under a `<file name="/abs/path">` element. We keep only those lines (the ones coverage can
 * actually measure) and normalize the absolute filenames to project-relative paths so they
 * line up with the relative paths git reports for the diff.
 *
 * Parsing streams via XMLReader rather than loading the whole DOM, so a large report on a big
 * codebase stays memory-bounded — the same memory-consciousness the rest of the runner keeps.
 */
final readonly class CloverReport
{
    /**
     * @param array<string, array<int, int>> $lines file (project-relative) => [lineNumber => hits]
     */
    private function __construct(private array $lines)
    {
    }

    /**
     * Read a Clover file. Returns an empty report when the file is missing or unreadable, so a
     * coverage run that produced nothing degrades to "no data" rather than erroring.
     */
    public static function fromFile(string $path, string $projectRoot): self
    {
        // is_readable also guards the open() below, which would otherwise warn on a file we
        // can't read (e.g. no read permission) — no error-control operator needed.
        if (! is_file($path) || ! is_readable($path)) {
            return new self([]);
        }

        $reader = new \XMLReader();
        $reader->open($path);

        $lines = self::read($reader, rtrim($projectRoot, '/') . '/');
        $reader->close();

        return new self($lines);
    }

    /**
     * @return array<string, array<int, int>>
     */
    private static function read(\XMLReader $reader, string $rootPrefix): array
    {
        $lines = [];
        $file = null;

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'file') {
                $file = self::relativeName((string) $reader->getAttribute('name'), $rootPrefix);

                continue;
            }

            if ($reader->name === 'line' && $file !== null && $reader->getAttribute('type') === 'stmt') {
                $lines[$file][(int) $reader->getAttribute('num')] = (int) $reader->getAttribute('count');
            }
        }

        return $lines;
    }

    private static function relativeName(string $name, string $rootPrefix): string
    {
        return str_starts_with($name, $rootPrefix) ? substr($name, strlen($rootPrefix)) : $name;
    }

    /**
     * The executable lines coverage measured for a file, as [lineNumber => hits], or [] if the
     * file isn't in the report (e.g. not PHP, or never loaded by the test suite).
     *
     * @return array<int, int>
     */
    public function linesFor(string $file): array
    {
        return $this->lines[$file] ?? [];
    }
}
