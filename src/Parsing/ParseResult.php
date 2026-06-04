<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Finding;

/**
 * What an {@see \PdxApps\Preflight\Contracts\OutputParser} extracts from a tool's output:
 * the unresolved {@see Finding}s plus the list of files a fixer rewrote.
 *
 * Findings and changes are distinct outcomes: a finding is a problem still present (check
 * mode, or an unfixable issue), while a changed file is one the tool just fixed (fix mode).
 * Keeping them separate lets the renderer say "fixed 3 files" without treating a successful
 * fix as a warning.
 */
final readonly class ParseResult
{
    /**
     * @param list<Finding> $findings
     * @param list<string> $changed project-relative paths a fixer rewrote
     */
    public function __construct(
        public array $findings = [],
        public array $changed = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param list<Finding> $findings
     */
    public static function ofFindings(array $findings): self
    {
        return new self(findings: $findings);
    }

    /**
     * @param list<string> $changed
     */
    public static function ofChanged(array $changed): self
    {
        return new self(changed: $changed);
    }
}
