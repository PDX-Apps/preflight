<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Process\ProcessSpec;
use PdxApps\Preflight\Runner\SymfonyProcessExecutor;

/**
 * Resolves the set of changed files from git, for the `--dirty` and `--since` scope flags.
 *
 * `dirty()` is the working-tree set — staged, unstaged, and untracked — for the interactive
 * / agent loop ("check what I just touched"). `since()` is the diff against a ref, for CI /
 * PR checks. Both return project-relative paths and degrade to an empty list when git is
 * unavailable (not a repo, bad ref), so a scoped run simply finds nothing rather than erroring.
 */
final readonly class GitFiles
{
    public function __construct(
        private ProcessExecutor $executor = new SymfonyProcessExecutor(),
    ) {
    }

    /**
     * Files with working-tree changes (staged + unstaged + untracked), excluding deletions.
     *
     * @return list<string>
     */
    public function dirty(string $projectRoot): array
    {
        $result = $this->executor->execute(new ProcessSpec(
            command: ['git', 'status', '--porcelain', '--untracked-files=all'],
            workingDirectory: $projectRoot,
        ));

        if ($result->failed()) {
            return [];
        }

        $files = [];
        foreach ($this->lines($result->stdout) as $line) {
            $path = $this->parsePorcelainLine($line);
            if ($path !== null) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Files changed in the working tree relative to the given ref (e.g. the base branch).
     *
     * @return list<string>
     */
    public function since(string $projectRoot, string $ref): array
    {
        $result = $this->executor->execute(new ProcessSpec(
            command: ['git', 'diff', '--name-only', '--diff-filter=d', $ref],
            workingDirectory: $projectRoot,
        ));

        if ($result->failed()) {
            return [];
        }

        return $this->lines($result->stdout);
    }

    /**
     * The changed line ranges, per file, relative to the given ref — the new-side line spans
     * of every hunk (the lines that exist *now*). Used for line-level patch coverage.
     *
     * @return array<string, list<array{int, int}>> file => list of [startLine, endLine]
     */
    public function sinceRanges(string $projectRoot, string $ref): array
    {
        $result = $this->executor->execute(new ProcessSpec(
            command: ['git', 'diff', '--unified=0', '--diff-filter=d', $ref],
            workingDirectory: $projectRoot,
        ));

        if ($result->failed()) {
            return [];
        }

        return $this->parseUnifiedDiff($result->stdout);
    }

    /**
     * The changed line ranges, per file, for the working tree (staged + unstaged vs HEAD,
     * plus untracked files counted whole). The `--dirty` counterpart of {@see sinceRanges()}.
     *
     * @return array<string, list<array{int, int}>> file => list of [startLine, endLine]
     */
    public function dirtyRanges(string $projectRoot): array
    {
        $tracked = $this->executor->execute(new ProcessSpec(
            command: ['git', 'diff', '--unified=0', '--diff-filter=d', 'HEAD'],
            workingDirectory: $projectRoot,
        ));

        $ranges = $tracked->failed() ? [] : $this->parseUnifiedDiff($tracked->stdout);

        foreach ($this->untracked($projectRoot) as $path) {
            // A brand-new file has no diff base, so the whole file counts as added.
            $whole = $this->wholeFileRange($projectRoot, $path);
            if ($whole !== null) {
                $ranges[$path] = [$whole];
            }
        }

        return $ranges;
    }

    /**
     * Parse `git diff --unified=0` output into per-file new-side line ranges. Each `@@ -a,b
     * +c,d @@` header contributes the range [c, c+d-1]; `d == 0` (a pure deletion) is skipped.
     *
     * @return array<string, list<array{int, int}>>
     */
    private function parseUnifiedDiff(string $output): array
    {
        $ranges = [];
        $current = null;

        foreach ($this->rawLines($output) as $line) {
            if (str_starts_with($line, '+++ ')) {
                $current = $this->pathFromHeader($line);

                continue;
            }

            if ($current === null || ! str_starts_with($line, '@@')) {
                continue;
            }

            $span = $this->hunkRange($line);
            if ($span !== null) {
                $ranges[$current][] = $span;
            }
        }

        return $ranges;
    }

    /**
     * The new-side `+c,d` span of a hunk header, or null when the hunk adds nothing.
     *
     * @return ?array{int, int}
     */
    private function hunkRange(string $header): ?array
    {
        if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/', $header, $m) !== 1) {
            return null;
        }

        $start = (int) $m[1];
        $count = isset($m[2]) ? (int) $m[2] : 1;
        if ($count === 0) {
            return null;
        }

        return [$start, $start + $count - 1];
    }

    /**
     * The path from a `+++ b/path` diff header, or null for `/dev/null` (a deletion).
     */
    private function pathFromHeader(string $line): ?string
    {
        $path = substr($line, 4);
        if ($path === '/dev/null') {
            return null;
        }

        if (str_starts_with($path, 'b/') || str_starts_with($path, 'a/')) {
            $path = substr($path, 2);
        }

        return $this->unquote($path);
    }

    /**
     * Untracked file paths (new files git isn't tracking yet).
     *
     * @return list<string>
     */
    private function untracked(string $projectRoot): array
    {
        $result = $this->executor->execute(new ProcessSpec(
            command: ['git', 'ls-files', '--others', '--exclude-standard'],
            workingDirectory: $projectRoot,
        ));

        return $result->failed() ? [] : $this->lines($result->stdout);
    }

    /**
     * The [1, lineCount] range of a whole file on disk, or null if it can't be read or is empty.
     *
     * @return ?array{int, int}
     */
    private function wholeFileRange(string $projectRoot, string $path): ?array
    {
        $absolute = rtrim($projectRoot, '/') . '/' . $path;
        if (! is_file($absolute)) {
            return null;
        }

        $contents = file_get_contents($absolute);
        if ($contents === false || $contents === '') {
            return null;
        }

        return [1, substr_count(rtrim($contents, "\n"), "\n") + 1];
    }

    /**
     * Like {@see lines()} but keeps blank lines, so diff parsing sees every line.
     *
     * @return list<string>
     */
    private function rawLines(string $output): array
    {
        return array_map(static fn (string $line): string => rtrim($line, "\r"), explode("\n", $output));
    }

    /**
     * Parse one `git status --porcelain` line to its current path, or null if it should be
     * skipped (a pure deletion). Renames (`R  old -> new`) resolve to the new path.
     */
    private function parsePorcelainLine(string $line): ?string
    {
        // Format: XY<space>path  (X = index status, Y = worktree status).
        $status = substr($line, 0, 2);
        $path = substr($line, 3);

        if (str_contains($status, 'D')) {
            return null;
        }

        if (str_contains($status, 'R') && str_contains($path, ' -> ')) {
            $path = substr($path, (int) strpos($path, ' -> ') + 4);
        }

        return $this->unquote($path);
    }

    /**
     * git quotes paths containing unusual characters; strip the surrounding quotes.
     */
    private function unquote(string $path): string
    {
        if (str_starts_with($path, '"') && str_ends_with($path, '"')) {
            return stripcslashes(substr($path, 1, -1));
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    private function lines(string $output): array
    {
        $lines = [];
        foreach (explode("\n", $output) as $line) {
            $line = rtrim($line, "\r");
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
