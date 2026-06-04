<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * A caveat attached to an {@see InstallRecipe}: something non-obvious or potentially invasive
 * about installing this tool that the user should decide on explicitly rather than have done
 * silently — e.g. a package that only ships a dev branch and therefore needs
 * `minimum-stability: dev` set on the project.
 *
 * The install command surfaces the {@see note} and {@see link}, then lets the user opt in
 * per package (install / skip / details) instead of baking a global policy.
 */
final readonly class InstallCaveat
{
    public function __construct(
        public string $note,
        public string $link,
        public bool $setsMinimumStabilityDev = false,
    ) {
    }
}
