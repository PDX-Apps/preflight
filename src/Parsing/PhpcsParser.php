<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses PHP_CodeSniffer output. The two binaries emit different formats, so the parser is
 * mode-aware:
 *
 * - Check (`phpcs --report=json`): `{"files":{"<abs>":{"messages":[{source,type,line,
 *   column,fixable,...}]}}}`. Each message becomes a {@see Finding} (severity from `type`,
 *   rule from `source`), with the per-message `fixable` flag carried through.
 * - Fix (`phpcbf`): a plain-text summary table with `FILE / FIXED / REMAINING` columns.
 *   Each row with FIXED > 0 is a changed file; a row with REMAINING > 0 also yields a
 *   finding noting the unfixable issues left behind.
 *
 * Paths are relativized against the project root. A clean run yields nothing in either mode.
 */
final readonly class PhpcsParser implements OutputParser
{
    public function __construct(
        private Mode $mode,
        private string $projectRoot,
        private string $tool = 'phpcs',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        return $this->mode === Mode::Fix
            ? $this->parseFixTable($result)
            : $this->parseCheckJson($result);
    }

    private function parseCheckJson(ProcessResult $result): ParseResult
    {
        $decoded = json_decode($result->stdout, true);

        if (! is_array($decoded) || ! isset($decoded['files'])) {
            return $this->fallback($result);
        }

        $files = is_array($decoded['files']) ? $decoded['files'] : [];

        $findings = [];
        foreach ($files as $path => $file) {
            $messages = is_array($file['messages'] ?? null) ? $file['messages'] : [];
            foreach ($messages as $message) {
                if (is_array($message)) {
                    $findings[] = $this->checkFinding((string) $path, $message);
                }
            }
        }

        return ParseResult::ofFindings($findings);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function checkFinding(string $absolutePath, array $message): Finding
    {
        $type = is_string($message['type'] ?? null) ? $message['type'] : 'ERROR';

        return new Finding(
            tool: $this->tool,
            severity: $type === 'WARNING' ? Severity::Warning : Severity::Error,
            message: (string) ($message['message'] ?? 'Code standards violation.'),
            file: $this->relativize($absolutePath),
            line: isset($message['line']) ? (int) $message['line'] : null,
            column: isset($message['column']) ? (int) $message['column'] : null,
            rule: isset($message['source']) ? (string) $message['source'] : null,
            fixable: (bool) ($message['fixable'] ?? false),
        );
    }

    /**
     * Parse the phpcbf text summary: rows of `<path> <fixed> <remaining>`.
     */
    private function parseFixTable(ProcessResult $result): ParseResult
    {
        $changed = [];
        $findings = [];

        foreach (explode("\n", $result->stdout) as $line) {
            $row = $this->parseTableRow($line);
            if ($row === null) {
                continue;
            }

            [$path, $fixed, $remaining] = $row;
            $relative = $this->relativize($path);

            if ($fixed > 0) {
                $changed[] = $relative;
            }

            if ($remaining > 0) {
                $findings[] = new Finding(
                    tool: $this->tool,
                    severity: Severity::Warning,
                    message: sprintf('%d issue(s) remaining after fixing (not auto-fixable).', $remaining),
                    file: $relative,
                );
            }
        }

        if ($changed === [] && $findings === [] && $result->failed() && ! str_contains($result->stdout, 'PHPCBF RESULT SUMMARY')) {
            return $this->fallback($result);
        }

        return new ParseResult(findings: $findings, changed: $changed);
    }

    /**
     * Parse one summary-table data row into [absolutePath, fixed, remaining], or null if the
     * line is a header/separator/footer rather than a file row.
     *
     * @return array{0: string, 1: int, 2: int}|null
     */
    private function parseTableRow(string $line): ?array
    {
        if (preg_match('/^(\/\S+\.php)\s+(\d+)\s+(\d+)\s*$/', trim($line), $m) !== 1) {
            return null;
        }

        return [$m[1], (int) $m[2], (int) $m[3]];
    }

    private function relativize(string $path): string
    {
        $root = rtrim($this->projectRoot, '/') . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    private function fallback(ProcessResult $result): ParseResult
    {
        if ($result->failed()) {
            $message = trim($result->stderr) !== '' ? trim($result->stderr) : 'PHP_CodeSniffer failed.';

            return ParseResult::ofFindings([new Finding($this->tool, Severity::Error, $message)]);
        }

        return ParseResult::empty();
    }
}
