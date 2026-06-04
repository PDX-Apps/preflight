<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Contracts;

use PdxApps\Preflight\Parsing\ParseResult;
use PdxApps\Preflight\Process\ProcessResult;

/**
 * Turns a tool's raw {@see ProcessResult} into a {@see ParseResult}: normalized findings
 * plus any files a fixer rewrote.
 *
 * Parsers are pure and stateless: same input, same output. This is what lets every tool —
 * whatever its native report format — feed the single Finding schema.
 */
interface OutputParser
{
    public function parse(ProcessResult $result): ParseResult;
}
