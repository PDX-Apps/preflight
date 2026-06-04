<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * The default parser: a non-zero exit code becomes a single error {@see Finding}.
 *
 * Used by steps whose tool has no machine-readable report — the only signal is
 * pass/fail. Location data is absent because the tool gives none.
 */
final readonly class ExitCodeParser implements OutputParser
{
    public function __construct(
        private string $tool,
        private ?string $failureMessage = null,
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        if ($result->successful()) {
            return ParseResult::empty();
        }

        return ParseResult::ofFindings([
            new Finding(
                tool: $this->tool,
                severity: Severity::Error,
                message: $this->failureMessage ?? sprintf('%s failed with exit code %d.', $this->tool, $result->exitCode),
            ),
        ]);
    }
}
