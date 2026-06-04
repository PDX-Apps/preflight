<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Install\InstallCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallCatalog::class)]
final class InstallCatalogTest extends TestCase
{
    public function test_it_has_a_recipe_for_each_installable_default_step(): void
    {
        foreach (['pint', 'phpcs', 'phpstan', 'rector', 'psalm', 'phpmd'] as $name) {
            $this->assertNotNull(InstallCatalog::recipeFor($name), "missing recipe for {$name}");
        }
    }

    public function test_composer_audit_has_no_recipe_because_it_needs_no_install(): void
    {
        $this->assertNull(InstallCatalog::recipeFor('composer-audit'));
    }

    public function test_an_unknown_step_has_no_recipe(): void
    {
        $this->assertNull(InstallCatalog::recipeFor('nope'));
    }

    public function test_a_plain_recipe_carries_package_constraint_and_config_stub(): void
    {
        $phpstan = InstallCatalog::recipeFor('phpstan');

        $this->assertSame('phpstan/phpstan', $phpstan->package);
        $this->assertSame('^2', $phpstan->constraint);
        $this->assertSame('phpstan/phpstan:^2', $phpstan->requirement());
        $this->assertFalse($phpstan->hasCaveat());
        $this->assertSame('phpstan.neon', $phpstan->configFile);
    }

    public function test_phpmd_carries_a_dev_branch_caveat(): void
    {
        $phpmd = InstallCatalog::recipeFor('phpmd');

        $this->assertTrue($phpmd->hasCaveat());
        $this->assertTrue($phpmd->caveat->setsMinimumStabilityDev);
        $this->assertNotSame('', $phpmd->caveat->note);
        $this->assertStringContainsString('http', $phpmd->caveat->link);
    }

    public function test_psalm_delegates_config_creation_to_its_own_init(): void
    {
        $psalm = InstallCatalog::recipeFor('psalm');

        $this->assertSame(['--init'], $psalm->initArgs);
        $this->assertNull($psalm->configFile, 'psalm --init writes its own config; nothing to scaffold');
    }

    public function test_the_test_runner_recipe_is_chosen_by_name(): void
    {
        $this->assertSame('phpunit/phpunit', InstallCatalog::runnerRecipe('phpunit')?->package);
        $this->assertSame('pestphp/pest', InstallCatalog::runnerRecipe('pest')?->package);
        $this->assertNull(InstallCatalog::runnerRecipe('none'));
    }
}
