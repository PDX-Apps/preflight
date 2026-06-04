<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses Rector's JSON report (`--output-format=json`).
 *
 * Rector's output is the same in both modes — `{"file_diffs":[{"file","diff",
 * "applied_rectors":[...]}]}` — so the parser is mode-aware: in check mode (`--dry-run`,
 * which exits 2 when changes are pending) each diff is a fixable {@see Finding}; in fix
 * mode (apply, exit 0) each diff is a *changed* file. A clean run omits `file_diffs`
 * entirely.
 *
 * On a hard failure with no parseable JSON, a single finding is produced from stderr.
 */
final readonly class RectorParser implements OutputParser
{
    public function __construct(
        private Mode $mode,
        private string $tool = 'rector',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $decoded = json_decode($result->stdout, true);

        if (! is_array($decoded)) {
            return $this->fallback($result);
        }

        $diffs = is_array($decoded['file_diffs'] ?? null) ? array_values($decoded['file_diffs']) : [];
        $files = $this->files($diffs);

        if ($files === []) {
            return $result->failed() && $diffs === [] ? $this->fallback($result) : ParseResult::empty();
        }

        if ($this->mode === Mode::Fix) {
            return ParseResult::ofChanged(array_map(static fn (array $f): string => $f['file'], $files));
        }

        return ParseResult::ofFindings(array_map($this->finding(...), $files));
    }

    /**
     * @param  array{file: string, rectors: list<string>}  $file
     */
    private function finding(array $file): Finding
    {
        return new Finding(
            tool: $this->tool,
            severity: Severity::Warning,
            message: $this->message($file['rectors']),
            file: $file['file'],
            fixable: true,
        );
    }

    /**
     * @param  list<string>  $rectors
     */
    private function message(array $rectors): string
    {
        if ($rectors === []) {
            return 'Refactoring suggested.';
        }

        $short = array_map($this->shortName(...), $rectors);

        return 'Refactoring suggested: ' . implode(', ', $short) . '.';
    }

    private function shortName(string $rector): string
    {
        $pos = strrpos($rector, '\\');

        return $pos === false ? $rector : substr($rector, $pos + 1);
    }

    /**
     * Normalize the decoded `file_diffs` to a typed list of file + rule names.
     *
     * @param  list<mixed>  $diffs
     * @return list<array{file: string, rectors: list<string>}>
     */
    private function files(array $diffs): array
    {
        $files = [];
        foreach ($diffs as $diff) {
            if (is_array($diff) && isset($diff['file'])) {
                $rectors = is_array($diff['applied_rectors'] ?? null) ? $diff['applied_rectors'] : [];
                $files[] = [
                    'file' => (string) $diff['file'],
                    'rectors' => array_values(array_map(strval(...), $rectors)),
                ];
            }
        }

        return $files;
    }

    private function fallback(ProcessResult $result): ParseResult
    {
        if ($result->failed()) {
            $message = trim($result->stderr) !== '' ? trim($result->stderr) : 'Rector failed.';

            return ParseResult::ofFindings([new Finding($this->tool, Severity::Warning, $message, fixable: true)]);
        }

        return ParseResult::empty();
    }
}
