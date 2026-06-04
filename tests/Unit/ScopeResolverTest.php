<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Config\ModuleConfig;
use PdxApps\Preflight\Scope\ScopeRequest;
use PdxApps\Preflight\Scope\ScopeResolver;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PdxApps\Preflight\Tests\Support\TempProject;
use PdxApps\Preflight\Support\GitFiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScopeResolver::class)]
#[CoversClass(ScopeRequest::class)]
final class ScopeResolverTest extends TestCase
{
    private function resolver(?FakeProcessExecutor $executor = null): ScopeResolver
    {
        return new ScopeResolver(new GitFiles($executor ?? new FakeProcessExecutor()));
    }

    public function test_an_empty_request_resolves_to_the_whole_project(): void
    {
        $set = $this->resolver()->resolve(new ScopeRequest(), '/project');

        $this->assertFalse($set->isNarrowed());
    }

    public function test_explicit_files_become_file_targets(): void
    {
        $set = $this->resolver()->resolve(new ScopeRequest(files: ['app/A.php', 'app/B.php']), '/project');

        $this->assertTrue($set->isNarrowed());
        $this->assertSame(['app/A.php', 'app/B.php'], $set->files());
    }

    public function test_positional_paths_are_classified_as_files_or_directories_against_the_root(): void
    {
        // Use this package's own tree as a real filesystem to classify against.
        $root = dirname(__DIR__, 2);
        $set = $this->resolver()->resolve(new ScopeRequest(paths: ['src', 'composer.json']), $root);

        $this->assertSame(['src'], $set->directories());
        $this->assertSame(['composer.json'], $set->files());
    }

    public function test_dirty_resolves_changed_files_via_git(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess(" M app/A.php\n?? app/B.php\n");
        $set = $this->resolver($executor)->resolve(new ScopeRequest(dirty: true), '/project');

        $this->assertTrue($set->isNarrowed());
        $this->assertSame(['app/A.php', 'app/B.php'], $set->files());
    }

    public function test_since_resolves_files_changed_against_a_ref(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess("app/A.php\n");
        $set = $this->resolver($executor)->resolve(new ScopeRequest(since: 'main'), '/project');

        $this->assertSame(['app/A.php'], $set->files());
    }

    public function test_explicit_files_take_precedence_over_dirty(): void
    {
        // --files wins outright; git is never consulted.
        $executor = new FakeProcessExecutor();
        $set = $this->resolver($executor)->resolve(new ScopeRequest(files: ['app/Only.php'], dirty: true), '/project');

        $this->assertSame(['app/Only.php'], $set->files());
        $this->assertSame([], $executor->executed, 'git must not run when --files is given');
    }

    public function test_dirty_with_no_changes_resolves_to_an_empty_narrowed_set(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess('');
        $set = $this->resolver($executor)->resolve(new ScopeRequest(dirty: true), '/project');

        $this->assertTrue($set->isNarrowed());
        $this->assertTrue($set->isEmpty());
    }

    public function test_a_module_resolves_to_its_existing_app_and_tests_directories(): void
    {
        $project = new TempProject();
        $project->dir('Modules/Billing/app');
        $project->dir('Modules/Billing/tests');

        $set = $this->resolver()->resolve(
            new ScopeRequest(module: 'Billing'),
            $project->root,
            new ModuleConfig('Modules', 'app', 'tests'),
        );

        $this->assertTrue($set->isNarrowed());
        $this->assertSame(['Modules/Billing/app', 'Modules/Billing/tests'], $set->directories());
    }

    public function test_a_module_honors_a_custom_layout(): void
    {
        $project = new TempProject();
        $project->dir('packages/Billing/src');
        $project->dir('packages/Billing/test');

        $set = $this->resolver()->resolve(
            new ScopeRequest(module: 'Billing'),
            $project->root,
            new ModuleConfig('packages', 'src', 'test'),
        );

        $this->assertSame(['packages/Billing/src', 'packages/Billing/test'], $set->directories());
    }

    public function test_a_module_includes_only_directories_that_exist(): void
    {
        $project = new TempProject();
        $project->dir('Modules/Billing/app'); // app exists, tests does not

        $set = $this->resolver()->resolve(
            new ScopeRequest(module: 'Billing'),
            $project->root,
            new ModuleConfig('Modules', 'app', 'tests'),
        );

        $this->assertSame(['Modules/Billing/app'], $set->directories());
    }

    public function test_a_missing_module_narrows_to_an_empty_set_so_tools_skip_cleanly(): void
    {
        $project = new TempProject(); // no Modules/Ghost at all

        $set = $this->resolver()->resolve(
            new ScopeRequest(module: 'Ghost'),
            $project->root,
            new ModuleConfig('Modules', 'app', 'tests'),
        );

        $this->assertTrue($set->isNarrowed());
        $this->assertTrue($set->isEmpty(), 'no garbage paths passed to tools');
    }

    public function test_a_module_without_module_config_resolves_to_nothing(): void
    {
        // No ModuleConfig (modules disabled) -> the module request can't be located.
        $set = $this->resolver()->resolve(new ScopeRequest(module: 'Billing'), (new TempProject())->root, null);

        $this->assertTrue($set->isNarrowed());
        $this->assertTrue($set->isEmpty());
    }
}
