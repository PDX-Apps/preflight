<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * The user's choices for an install run: which test runner to add (or null for none), which
 * caveated tools they've opted into, and whether to scaffold config files.
 *
 * Gathered from CLI flags or interactive prompts and handed to the {@see InstallPlanner}.
 */
final readonly class InstallOptions
{
    /**
     * @param  string|null  $runner  'phpunit', 'pest', or null to install no test runner
     * @param  list<string>  $approvedCaveats  step names whose install caveat the user accepted
     */
    public function __construct(
        public ?string $runner = 'phpunit',
        public array $approvedCaveats = [],
        public bool $writeConfigs = true,
    ) {
    }

    public function caveatApproved(string $stepName): bool
    {
        return in_array($stepName, $this->approvedCaveats, true);
    }
}
