<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses Psalm's JSON report (`--output-format=json`).
 *
 * Unlike PHPStan, Psalm emits a flat array of issue objects:
 * `[{"severity","line_from","column_from","type","message","file_name",...}]`. Each becomes
 * a {@see Finding} with line, column, the rule `type`, and a severity mapped from Psalm's
 * `error`/`info`. `file_name` is already project-relative. A clean run is `[]`. Psalm only
 * reports (never fixes), so findings are not fixable.
 *
 * On a hard failure with no parseable JSON (bad config — emitted to stderr), a single
 * finding is produced from stderr.
 */
final readonly class PsalmParser implements OutputParser
{
    public function __construct(private string $tool = 'psalm')
    {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $decoded = json_decode($result->stdout, true);

        if (! is_array($decoded)) {
            return $this->fallback($result);
        }

        $findings = [];
        foreach ($decoded as $issue) {
            if (is_array($issue) && isset($issue['message'])) {
                $findings[] = $this->finding($issue);
            }
        }

        return $findings !== [] ? ParseResult::ofFindings($findings) : $this->fallback($result);
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function finding(array $issue): Finding
    {
        return new Finding(
            tool: $this->tool,
            severity: $this->severity(is_string($issue['severity'] ?? null) ? $issue['severity'] : 'error'),
            message: (string) $issue['message'],
            file: isset($issue['file_name']) ? (string) $issue['file_name'] : null,
            line: isset($issue['line_from']) ? (int) $issue['line_from'] : null,
            column: isset($issue['column_from']) ? (int) $issue['column_from'] : null,
            rule: isset($issue['type']) ? (string) $issue['type'] : null,
        );
    }

    private function severity(string $psalm): Severity
    {
        return match ($psalm) {
            'info' => Severity::Info,
            default => Severity::Error,
        };
    }

    private function fallback(ProcessResult $result): ParseResult
    {
        if ($result->failed()) {
            $message = trim($result->stderr) !== '' ? trim($result->stderr) : 'Psalm failed.';

            return ParseResult::ofFindings([new Finding($this->tool, Severity::Error, $message)]);
        }

        return ParseResult::empty();
    }
}
