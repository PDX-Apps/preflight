<?php

declare(strict_types=1);

namespace PdxApps\Preflight;

/**
 * Whether a run only inspects code (Check) or also mutates it to fix issues (Fix).
 */
enum Mode: string
{
    case Check = 'check';
    case Fix = 'fix';

    public function isFix(): bool
    {
        return $this === self::Fix;
    }
}
