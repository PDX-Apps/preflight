<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * A tool the install run will NOT add, with why — already installed, or a caveat the user
 * hasn't opted into. Surfaced in the preview so nothing is silently omitted.
 */
final readonly class SkippedInstall
{
    public function __construct(
        public string $stepName,
        public string $package,
        public string $reason,
    ) {
    }
}
