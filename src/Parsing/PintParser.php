<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses Laravel Pint's JSON report (`--format=json`).
 *
 * Pint's `result` field distinguishes the outcomes:
 * - `passed` — clean, nothing to report.
 * - `fail`   — check mode found issues (exit 1); each listed file becomes a fixable warning.
 * - `fixed`  — fix mode rewrote files (exit 0); each listed file is reported as *changed*,
 *              not as a finding, because it has been resolved.
 *
 * In both `fail` and `fixed`, `files[].fixers` names the rules involved. If the output
 * can't be decoded but the process failed, a single generic finding is returned so a
 * failure is never silently dropped.
 */
final readonly class PintParser implements OutputParser
{
    public function __construct(private string $tool = 'pint')
    {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $decoded = json_decode($result->stdout, true);

        if (! is_array($decoded) || ! isset($decoded['result'])) {
            return $this->fallback($result);
        }

        $files = $this->files($decoded);

        return match ($decoded['result']) {
            'fixed' => ParseResult::ofChanged(array_map(static fn (array $f): string => $f['path'], $files)),
            'fail' => $this->findings($files, $result),
            default => ParseResult::empty(),
        };
    }

    /**
     * @param  list<array{path: string, fixers: list<mixed>}>  $files
     */
    private function findings(array $files, ProcessResult $result): ParseResult
    {
        $findings = [];
        foreach ($files as $file) {
            $findings[] = new Finding(
                tool: $this->tool,
                severity: Severity::Warning,
                message: $this->message($file['fixers']),
                file: $file['path'],
                fixable: true,
            );
        }

        return $findings !== [] ? ParseResult::ofFindings($findings) : $this->fallback($result);
    }

    /**
     * Normalize the decoded `files` array to a clean, typed list.
     *
     * @param  array<string, mixed>  $decoded
     * @return list<array{path: string, fixers: list<mixed>}>
     */
    private function files(array $decoded): array
    {
        $raw = is_array($decoded['files'] ?? null) ? $decoded['files'] : [];

        $files = [];
        foreach ($raw as $file) {
            if (is_array($file) && isset($file['path'])) {
                $files[] = [
                    'path' => (string) $file['path'],
                    'fixers' => is_array($file['fixers'] ?? null) ? array_values($file['fixers']) : [],
                ];
            }
        }

        return $files;
    }

    /**
     * @param  list<mixed>  $fixers
     */
    private function message(array $fixers): string
    {
        if ($fixers === []) {
            return 'Code style issues found.';
        }

        return 'Code style issues found: ' . implode(', ', array_map(strval(...), $fixers)) . '.';
    }

    private function fallback(ProcessResult $result): ParseResult
    {
        if ($result->failed()) {
            return ParseResult::ofFindings([
                new Finding($this->tool, Severity::Warning, 'Code style issues found.', fixable: true),
            ]);
        }

        return ParseResult::empty();
    }
}
