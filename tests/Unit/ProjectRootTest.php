<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\ProjectRoot;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ProjectRoot::class)]
final class ProjectRootTest extends TestCase
{
    public function test_it_returns_a_directory_that_directly_contains_composer_json(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');

        $this->assertSame(realpath($project->root), ProjectRoot::discoverFrom($project->root));
    }

    public function test_it_walks_up_from_a_nested_directory_to_the_nearest_composer_json(): void
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $nested = $project->dir('src/Deep/Nested');

        $this->assertSame(realpath($project->root), ProjectRoot::discoverFrom($nested));
    }

    public function test_it_throws_when_no_composer_json_exists_up_the_tree(): void
    {
        $project = new TempProject(); // no composer.json

        $this->expectException(RuntimeException::class);
        ProjectRoot::discoverFrom($project->root);
    }
}
