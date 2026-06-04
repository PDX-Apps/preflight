<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\ConfigResolver;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigResolver::class)]
final class ConfigResolverTest extends TestCase
{
    public function test_a_bare_filename_resolves_against_the_project_root(): void
    {
        $project = new TempProject();
        $project->file('phpstan.neon', '');
        $resolver = new ConfigResolver($project->root);

        $this->assertSame($project->root . '/phpstan.neon', $resolver->resolve('phpstan.neon'));
    }

    public function test_a_relative_path_resolves_against_the_project_root(): void
    {
        $project = new TempProject();
        $resolver = new ConfigResolver($project->root);

        $this->assertSame($project->root . '/config/pint.json', $resolver->resolve('config/pint.json'));
    }

    public function test_an_absolute_path_is_returned_unchanged(): void
    {
        $project = new TempProject();
        $resolver = new ConfigResolver($project->root);

        $this->assertSame('/etc/preflight/psalm.xml', $resolver->resolve('/etc/preflight/psalm.xml'));
    }

    public function test_null_resolves_to_null_so_a_tool_can_find_its_own_config(): void
    {
        $resolver = new ConfigResolver((new TempProject())->root);

        $this->assertNull($resolver->resolve(null));
    }

    public function test_it_reports_whether_a_resolved_config_exists(): void
    {
        $project = new TempProject();
        $project->file('pint.json', '{}');
        $resolver = new ConfigResolver($project->root);

        $this->assertTrue($resolver->exists('pint.json'));
        $this->assertFalse($resolver->exists('missing.json'));
        $this->assertFalse($resolver->exists(null));
    }
}
