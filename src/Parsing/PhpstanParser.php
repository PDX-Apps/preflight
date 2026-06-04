<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses PHPStan's JSON report (`--error-format=json`).
 *
 * The document is `{"files": {"<abs path>": {"messages": [...]}}, "errors": [...]}`. Each
 * file message becomes an error {@see Finding} (with `line`, `message`, and the rule
 * `identifier`); each top-level string in `errors` is a general, file-less finding. PHPStan
 * reports absolute paths, so they are relativized against the project root. PHPStan has no
 * column data and never fixes, so findings carry no column and are not fixable.
 *
 * On a hard failure with no parseable JSON (bad path, out of memory — emitted to stderr),
 * a single finding is produced from stderr so the failure is never silently dropped.
 */
final readonly class PhpstanParser implements OutputParser
{
    public function __construct(
        private string $projectRoot,
        private string $tool = 'phpstan',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $decoded = json_decode($result->stdout, true);

        if (! is_array($decoded) || ! isset($decoded['files']) && ! isset($decoded['errors'])) {
            return $this->fallback($result);
        }

        $findings = [
            ...$this->fileFindings($decoded['files'] ?? null),
            ...$this->generalFindings($decoded['errors'] ?? null),
        ];

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
                $findings[] = $this->fileFinding((string) $path, $message);
            }
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function generalFindings(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        return array_map(
            fn (mixed $message): Finding => new Finding($this->tool, Severity::Error, (string) $message),
            array_values($errors),
        );
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function fileFinding(string $absolutePath, array $message): Finding
    {
        return new Finding(
            tool: $this->tool,
            severity: Severity::Error,
            message: (string) ($message['message'] ?? 'Error.'),
            file: $this->relativize($absolutePath),
            line: isset($message['line']) ? (int) $message['line'] : null,
            rule: isset($message['identifier']) ? (string) $message['identifier'] : null,
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
            $message = trim($result->stderr) !== '' ? trim($result->stderr) : 'PHPStan failed.';

            return ParseResult::ofFindings([new Finding($this->tool, Severity::Error, $message)]);
        }

        return ParseResult::empty();
    }
}
