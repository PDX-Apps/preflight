<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Support;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Parsing\ParseResult;
use PdxApps\Preflight\Process\ProcessResult;

/**
 * A parser that always reports nothing — used to verify that judgeByFindings() makes a
 * step pass on a non-zero exit when the parser found no problems.
 */
final class NoFindingsParser implements OutputParser
{
    public function parse(ProcessResult $result): ParseResult
    {
        return ParseResult::empty();
    }
}
