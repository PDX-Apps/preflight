<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Config\ModuleConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleConfig::class)]
final class ModuleConfigTest extends TestCase
{
    public function test_the_default_uses_the_conventional_nwidart_layout(): void
    {
        $config = ModuleConfig::default();

        $this->assertSame('Modules', $config->dir);
        $this->assertSame('app', $config->app);
        $this->assertSame('tests', $config->tests);
    }

    public function test_it_carries_custom_directory_names(): void
    {
        $config = new ModuleConfig(dir: 'packages', app: 'src', tests: 'test');

        $this->assertSame('packages', $config->dir);
        $this->assertSame('src', $config->app);
        $this->assertSame('test', $config->tests);
    }
}
