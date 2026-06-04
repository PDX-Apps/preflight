<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * One tool the install run will add: the step it belongs to and the {@see InstallRecipe}
 * (package, constraint, caveat, config strategy) resolved for it.
 */
final readonly class PlannedInstall
{
    public function __construct(
        public string $stepName,
        public InstallRecipe $recipe,
    ) {
    }
}
