<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses `composer normalize` output. It has no machine format, so this reads the (reliable)
 * exit code and the one-line status message, mode-aware:
 *
 * - Check (`--dry-run`): exit 0 is clean; a non-zero exit means composer.json is not
 *   normalized — a single fixable warning against `composer.json`.
 * - Fix: a "Successfully normalized" message means the file was rewritten, reported as a
 *   *changed* file; "already normalized" means nothing changed.
 */
final readonly class ComposerNormalizeParser implements OutputParser
{
    private const string ARTIFACT = 'composer.json';

    public function __construct(
        private Mode $mode,
        private string $tool = 'composer-normalize',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        if ($this->mode === Mode::Fix) {
            return str_contains($result->stdout, 'Successfully normalized')
                ? ParseResult::ofChanged([self::ARTIFACT])
                : ParseResult::empty();
        }

        if ($result->successful()) {
            return ParseResult::empty();
        }

        return ParseResult::ofFindings([
            new Finding(
                tool: $this->tool,
                severity: Severity::Warning,
                message: 'composer.json is not normalized.',
                file: self::ARTIFACT,
                fixable: true,
            ),
        ]);
    }
}
