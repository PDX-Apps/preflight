<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Config\ConfigLoader;
use PdxApps\Preflight\Config\Configuration;
use PdxApps\Preflight\OutputFormat;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ConfigLoader::class)]
final class ConfigLoaderTest extends TestCase
{
    public function test_a_missing_config_file_yields_the_default_configuration(): void
    {
        $project = new TempProject();

        $config = (new ConfigLoader())->load($project->root);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertNull($config->steps, 'no config file means auto-detect');
    }

    public function test_it_loads_a_configuration_returned_by_the_config_file(): void
    {
        $project = new TempProject();
        $project->file('preflight.php', <<<'PHP'
            <?php
            use PdxApps\Preflight\Preflight;
            return Preflight::configure()->withPaths(['app', 'src'])->failFast();
            PHP);

        $config = (new ConfigLoader())->load($project->root);

        $this->assertSame(['app', 'src'], $config->paths);
        $this->assertTrue($config->failFast);
    }

    public function test_a_config_file_may_return_a_builder_or_a_built_configuration(): void
    {
        $project = new TempProject();
        $project->file('preflight.php', <<<'PHP'
            <?php
            use PdxApps\Preflight\Preflight;
            return Preflight::configure()->defaultFormat('json')->build();
            PHP);

        $config = (new ConfigLoader())->load($project->root);

        $this->assertSame(OutputFormat::Json, $config->defaultFormat);
    }

    public function test_it_reports_whether_a_config_file_is_present(): void
    {
        $project = new TempProject();
        $this->assertFalse((new ConfigLoader())->exists($project->root));

        $project->file('preflight.php', "<?php return PdxApps\\Preflight\\Preflight::configure();");
        $this->assertTrue((new ConfigLoader())->exists($project->root));
    }

    public function test_a_config_file_returning_the_wrong_type_is_rejected(): void
    {
        $project = new TempProject();
        $project->file('preflight.php', '<?php return 42;');

        $this->expectException(RuntimeException::class);
        (new ConfigLoader())->load($project->root);
    }
}
