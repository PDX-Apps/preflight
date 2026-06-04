<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * The computed result of {@see InstallPlanner::plan()}: what will be installed and what was
 * skipped (with reasons). Pure data — the command previews it and then acts on it.
 */
final readonly class InstallPlan
{
    /**
     * @param  list<PlannedInstall>  $installs
     * @param  list<SkippedInstall>  $skipped
     */
    public function __construct(
        public array $installs = [],
        public array $skipped = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->installs === [];
    }

    /**
     * Composer requirement strings for every planned install, e.g. `phpstan/phpstan:^2`.
     *
     * @return list<string>
     */
    public function requirements(): array
    {
        return array_map(static fn (PlannedInstall $i): string => $i->recipe->requirement(), $this->installs);
    }

    /**
     * Whether any planned install needs `minimum-stability: dev` set on the project (a
     * dev-branch package like PHPMD 3.x).
     */
    public function setsMinimumStabilityDev(): bool
    {
        return array_any(
            $this->installs,
            static fn (PlannedInstall $install): bool => $install->recipe->caveat?->setsMinimumStabilityDev === true,
        );
    }
}
