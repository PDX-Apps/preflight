<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * The built-in install recipes: the Composer packages, version constraints, caveats, and
 * config-seeding strategy Preflight recommends for each default step.
 *
 * This is the single place those constraints live, so keeping them current is one file. A
 * step with no entry (e.g. `composer-audit`, which ships with Composer) returns null and is
 * simply not something `install` adds.
 */
final class InstallCatalog
{
    public static function recipeFor(string $stepName): ?InstallRecipe
    {
        return self::recipes()[$stepName] ?? null;
    }

    /**
     * The recipe for a named test runner, or null for `none`/unknown. Kept separate from the
     * step recipes because the `test` step has no single tool — the user picks the runner.
     */
    public static function runnerRecipe(string $runner): ?InstallRecipe
    {
        return match ($runner) {
            'phpunit' => new InstallRecipe('phpunit/phpunit', '^11'),
            'pest' => new InstallRecipe('pestphp/pest', '^3'),
            default => null,
        };
    }

    /**
     * @return array<string, InstallRecipe>
     */
    private static function recipes(): array
    {
        return [
            'pint' => new InstallRecipe('laravel/pint', '^1', configFile: 'pint.json'),
            'phpcs' => new InstallRecipe('squizlabs/php_codesniffer', '^4', configFile: 'phpcs.xml'),
            'phpstan' => new InstallRecipe('phpstan/phpstan', '^2', configFile: 'phpstan.neon'),
            'rector' => new InstallRecipe('rector/rector', '^2', configFile: 'rector.php'),
            'psalm' => new InstallRecipe('vimeo/psalm', '^6', configFile: 'psalm.xml'),
            'phpmd' => new InstallRecipe(
                'phpmd/phpmd',
                '^3@dev',
                caveat: new InstallCaveat(
                    note: 'PHPMD has no stable 3.x release yet, and 2.x cannot parse PHP 8.4+. '
                        . 'The 3.x dev branch requires "minimum-stability: dev" in composer.json.',
                    link: 'https://github.com/phpmd/phpmd/releases',
                    setsMinimumStabilityDev: true,
                ),
                configFile: 'phpmd.xml',
            ),
        ];
    }
}
