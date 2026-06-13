<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Install\ConfigScaffolder;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigScaffolder::class)]
final class ConfigScaffolderTest extends TestCase
{
    private function scaffolder(TempProject $project): ConfigScaffolder
    {
        return new ConfigScaffolder($project->root);
    }

    public function test_it_detects_existing_source_directories(): void
    {
        $project = new TempProject();
        $project->file('app/Foo.php', '<?php');
        $project->file('tests/FooTest.php', '<?php');

        $this->assertSame(['app', 'tests'], $this->scaffolder($project)->sourcePaths());
    }

    public function test_it_falls_back_to_src_when_no_known_dirs_exist(): void
    {
        $this->assertSame(['src'], $this->scaffolder(new TempProject())->sourcePaths());
    }

    public function test_phpstan_neon_includes_the_detected_paths(): void
    {
        $project = new TempProject();
        $project->file('src/Foo.php', '<?php');

        $content = $this->scaffolder($project)->contentsFor('phpstan.neon');

        $this->assertNotNull($content);
        $this->assertStringContainsString('level:', $content);
        $this->assertStringContainsString('- src', $content);
        $this->assertStringNotContainsString('- app', $content, 'app does not exist, so it is not scaffolded');
    }

    public function test_phpcs_xml_is_valid_xml_with_detected_paths(): void
    {
        $project = new TempProject();
        $project->file('app/Foo.php', '<?php');

        $content = $this->scaffolder($project)->contentsFor('phpcs.xml');

        $this->assertNotNull($content);
        $this->assertNotFalse(simplexml_load_string($content), 'scaffolded phpcs.xml must be valid XML');
        $this->assertStringContainsString('<file>app</file>', $content);
        $this->assertStringContainsString('PSR12', $content);
    }

    public function test_rector_php_is_valid_php_with_detected_paths(): void
    {
        $project = new TempProject();
        $project->file('src/Foo.php', '<?php');

        $content = $this->scaffolder($project)->contentsFor('rector.php');

        $this->assertNotNull($content);
        $this->assertStringContainsString('RectorConfig', $content);
        $this->assertStringContainsString("/src", $content);
        // The PHP 8.5 property-override rule emits #[\Override] on properties, which Psalm
        // rejects — so the default must skip it to keep the tool set self-consistent.
        $this->assertStringContainsString('AddOverrideAttributeToOverriddenPropertiesRector', $content);
        $this->assertStringContainsString('->withSkip([', $content);
    }

    public function test_pint_and_phpmd_have_static_content(): void
    {
        $scaffolder = $this->scaffolder(new TempProject());

        $pint = $scaffolder->contentsFor('pint.json');
        $this->assertNotNull($pint);
        $this->assertNotFalse(json_decode((string) $pint), 'pint.json must be valid JSON');

        $phpmd = $scaffolder->contentsFor('phpmd.xml');
        $this->assertNotNull($phpmd);
        $this->assertNotFalse(simplexml_load_string((string) $phpmd));
    }

    public function test_psalm_xml_is_valid_xml_with_dead_code_analysis_off(): void
    {
        $project = new TempProject();
        $project->file('app/Foo.php', '<?php');

        $content = $this->scaffolder($project)->contentsFor('psalm.xml');

        $this->assertNotNull($content);
        $xml = simplexml_load_string((string) $content);
        $this->assertNotFalse($xml, 'psalm.xml must be valid XML');
        // Dead-code analysis is off because it misreads Laravel's runtime-wired entry points.
        $this->assertStringContainsString('findUnusedCode="false"', (string) $content);
        $this->assertStringContainsString('findUnusedBaselineEntry="false"', (string) $content);
        $this->assertStringContainsString('<directory name="app" />', (string) $content);
    }

    public function test_an_unknown_file_has_no_content(): void
    {
        $this->assertNull($this->scaffolder(new TempProject())->contentsFor('unknown.conf'));
    }
}
