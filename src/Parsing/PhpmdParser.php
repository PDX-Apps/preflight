<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses PHPMD's JSON report.
 *
 * The document is `{"files": [{"file": "<abs path>", "violations": [...]}]}`. Each violation
 * (`beginLine`, `rule`, `description`, `priority`, …) becomes a warning {@see Finding} — PHPMD
 * reports code smells, not hard errors, and never fixes. Paths are absolute, so they are
 * relativized against the project root. A clean run has an empty `files` array.
 *
 * PHPMD 2.x on recent PHP prints `Deprecated:` notices that the runner strips before this
 * parser runs (see the step's deprecation filtering); `json_decode` is still given a trim()
 * to tolerate any leading whitespace left behind.
 */
final readonly class PhpmdParser implements OutputParser
{
    public function __construct(
        private string $projectRoot,
        private string $tool = 'phpmd',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $decoded = json_decode(trim($result->stdout), true);

        if (! is_array($decoded) || ! isset($decoded['files'])) {
            return $this->fallback($result);
        }

        $findings = [];

        $files = is_array($decoded['files']) ? $decoded['files'] : [];
        foreach ($files as $file) {
            $findings = [...$findings, ...$this->fileFindings($file)];
        }

        // PHPMD reports files it could not analyse (e.g. a parser that does not understand
        // newer PHP syntax) in a top-level `errors` array — surface those as findings too.
        $findings = [...$findings, ...$this->processingErrors($decoded['errors'] ?? null)];

        // Valid JSON with no violations is a clean result, regardless of exit code (PHPMD
        // exits non-zero merely for emitting deprecation notices).
        return ParseResult::ofFindings($findings);
    }

    /**
     * @return list<Finding>
     */
    private function processingErrors(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $findings = [];
        foreach ($errors as $error) {
            if (is_array($error) && isset($error['message'])) {
                $message = (string) $error['message'];
                // Keep only the first line — PHPMD appends a full stack trace.
                $message = strtok($message, "\n") ?: $message;

                $findings[] = new Finding(
                    tool: $this->tool,
                    severity: Severity::Warning,
                    message: $message,
                    file: isset($error['fileName']) && $error['fileName'] !== ''
                        ? $this->relativize((string) $error['fileName'])
                        : null,
                );
            }
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function fileFindings(mixed $file): array
    {
        if (! is_array($file) || ! isset($file['file'])) {
            return [];
        }

        $path = $this->relativize((string) $file['file']);
        $violations = is_array($file['violations'] ?? null) ? $file['violations'] : [];

        $findings = [];
        foreach ($violations as $violation) {
            if (is_array($violation)) {
                $findings[] = $this->finding($path, $violation);
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    private function finding(string $file, array $violation): Finding
    {
        return new Finding(
            tool: $this->tool,
            severity: Severity::Warning,
            message: (string) ($violation['description'] ?? 'Mess detected.'),
            file: $file,
            line: isset($violation['beginLine']) ? (int) $violation['beginLine'] : null,
            rule: isset($violation['rule']) ? (string) $violation['rule'] : null,
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
            $message = trim($result->stderr) !== '' ? trim($result->stderr) : 'PHPMD failed.';

            return ParseResult::ofFindings([new Finding($this->tool, Severity::Warning, $message)]);
        }

        return ParseResult::empty();
    }
}
