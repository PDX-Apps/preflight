<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Support\Tool;

/**
 * Turns the configured steps + what's already installed + the user's {@see InstallOptions}
 * into an {@see InstallPlan}. Pure: it inspects the project (via {@see Context}) but runs no
 * processes and writes nothing.
 *
 * Rules: a step whose tool is already present is skipped; a step with no recipe (e.g.
 * `composer-audit`, built into Composer) is ignored; a caveated tool (e.g. PHPMD's dev
 * branch) is skipped unless the user opted in; the `test` step installs the chosen runner,
 * but only when no runner is present yet (PHPUnit underlies all three).
 */
final readonly class InstallPlanner
{
    public function __construct(private Context $context)
    {
    }

    /**
     * @param  list<Step>  $steps
     */
    public function plan(array $steps, InstallOptions $options): InstallPlan
    {
        $installs = [];
        $skipped = [];

        foreach ($steps as $step) {
            $name = $step->name();

            if ($name === 'test') {
                [$installs, $skipped] = $this->planRunner($step, $options, $installs, $skipped);

                continue;
            }

            $recipe = InstallCatalog::recipeFor($name);
            if (! $recipe instanceof InstallRecipe) {
                continue; // nothing to install (e.g. composer-audit)
            }

            if ($this->installed($step)) {
                $skipped[] = new SkippedInstall($name, $recipe->package, 'already installed');

                continue;
            }

            if ($recipe->hasCaveat() && ! $options->caveatApproved($name)) {
                $skipped[] = new SkippedInstall($name, $recipe->package, 'skipped — needs opt-in (see caveat)');

                continue;
            }

            $installs[] = new PlannedInstall($name, $recipe);
        }

        return new InstallPlan($installs, $skipped);
    }

    /**
     * @param  list<PlannedInstall>  $installs
     * @param  list<SkippedInstall>  $skipped
     * @return array{0: list<PlannedInstall>, 1: list<SkippedInstall>}
     */
    private function planRunner(Step $step, InstallOptions $options, array $installs, array $skipped): array
    {
        if ($this->installed($step)) {
            $skipped[] = new SkippedInstall('test', 'phpunit/phpunit', 'already installed');

            return [$installs, $skipped];
        }

        $recipe = $options->runner !== null ? InstallCatalog::runnerRecipe($options->runner) : null;
        if (! $recipe instanceof InstallRecipe) {
            $skipped[] = new SkippedInstall('test', 'phpunit/phpunit', 'no test runner selected');

            return [$installs, $skipped];
        }

        $installs[] = new PlannedInstall('test', $recipe);

        return [$installs, $skipped];
    }

    private function installed(Step $step): bool
    {
        $tool = $step->tool();

        return ! $tool instanceof Tool || $this->context->toolAvailable($tool);
    }
}
