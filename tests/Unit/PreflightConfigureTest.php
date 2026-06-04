<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Config\ConfigurationBuilder;
use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Steps\Pint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preflight::class)]
final class PreflightConfigureTest extends TestCase
{
    public function test_configure_returns_a_fresh_builder(): void
    {
        $this->assertInstanceOf(ConfigurationBuilder::class, Preflight::configure());
    }

    public function test_configure_supports_the_full_fluent_chain_used_in_a_config_file(): void
    {
        $config = Preflight::configure()
            ->withPaths(['app'])
            ->withSteps([Pint::class])
            ->failFast()
            ->build();

        $this->assertSame(['app'], $config->paths);
        $this->assertTrue($config->failFast);
        $this->assertCount(1, $config->steps);
        $this->assertInstanceOf(Pint::class, $config->steps[0]);
    }
}
