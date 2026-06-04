<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses Deptrac's JSON report (`--formatter=json`).
 *
 * The document is `{"Report": {...counts}, "files": {"<abs path>": {"messages": [...]}}}`,
 * where `files` is an empty array (`[]`) when clean but an object keyed by path when there
 * are violations. Each message becomes a {@see Finding}: a `type` of `error` (an architecture
 * violation) maps to an error, anything else to a warning. Deptrac reports absolute paths, so
 * they are relativized against the project root; it never fixes, so findings aren't fixable.
 *
 * On a hard failure with no parseable JSON (e.g. a missing or invalid depfile, emitted to
 * stderr), a single finding is produced from stderr so the failure is never silently dropped.
 */
final readonly class DeptracParser implements OutputParser
{
    public function __construct(
        private string $projectRoot,
        private string $tool = 'deptrac',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $decoded = json_decode($result->stdout, true);

        if (! is_array($decoded) || ! array_key_exists('files', $decoded)) {
            return $this->fallback($result);
        }

        $findings = $this->fileFindings($decoded['files']);

        return $findings !== [] ? ParseResult::ofFindings($findings) : $this->fallback($result);
    }

    /**
     * @return list<Finding>
     */
    private function fileFindings(mixed $files): array
    {
        if (! is_array($files)) {
            return [];
        }

        $findings = [];
        foreach ($files as $path => $file) {
            $messages = is_array($file['messages'] ?? null) ? $file['messages'] : [];
            foreach ($messages as $message) {
                if (is_array($message)) {
                    $findings[] = $this->finding((string) $path, $message);
                }
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function finding(string $absolutePath, array $message): Finding
    {
        $type = isset($message['type']) ? (string) $message['type'] : 'error';

        return new Finding(
            tool: $this->tool,
            severity: $type === 'error' ? Severity::Error : Severity::Warning,
            message: (string) ($message['message'] ?? 'Architecture violation.'),
            file: $this->relativize($absolutePath),
            line: isset($message['line']) ? (int) $message['line'] : null,
        );
    }

    private function relativize(string $path): string
    {
        $root = rtrim($this->projectRoot, '/') . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    private function fallback(ProcessResult $result): ParseResult
    {
        if ($result->failed()) {
            $message = trim($result->stderr) !== '' ? trim($result->stderr) : 'Deptrac failed.';

            return ParseResult::ofFindings([new Finding($this->tool, Severity::Error, $message)]);
        }

        return ParseResult::empty();
    }
}
