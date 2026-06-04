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
