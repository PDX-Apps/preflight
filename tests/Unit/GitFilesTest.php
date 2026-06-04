<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\GitFiles;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitFiles::class)]
final class GitFilesTest extends TestCase
{
    public function test_dirty_parses_staged_unstaged_and_untracked_paths_from_porcelain(): void
    {
        // git status --porcelain: 2 status chars, a space, then the path.
        $porcelain = " M app/Modified.php\nA  app/Staged.php\n?? app/Untracked.php\n";
        $executor = (new FakeProcessExecutor())->queueSuccess($porcelain);

        $files = (new GitFiles($executor))->dirty('/project');

        $this->assertSame(['app/Modified.php', 'app/Staged.php', 'app/Untracked.php'], $files);
    }

    public function test_dirty_excludes_deleted_files(): void
    {
        $porcelain = " D app/Gone.php\nD  app/AlsoGone.php\n M app/Here.php\n";
        $executor = (new FakeProcessExecutor())->queueSuccess($porcelain);

        $files = (new GitFiles($executor))->dirty('/project');

        $this->assertSame(['app/Here.php'], $files);
    }

    public function test_dirty_resolves_a_rename_to_its_new_path(): void
    {
        $porcelain = "R  app/Old.php -> app/New.php\n";
        $executor = (new FakeProcessExecutor())->queueSuccess($porcelain);

        $files = (new GitFiles($executor))->dirty('/project');

        $this->assertSame(['app/New.php'], $files);
    }

    public function test_dirty_runs_git_status_in_the_project_root(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess('');
        (new GitFiles($executor))->dirty('/project');

        $spec = $executor->executed[0];
        $this->assertSame('/project', $spec->workingDirectory);
        $this->assertContains('status', $spec->command);
        $this->assertContains('--porcelain', $spec->command);
    }

    public function test_dirty_returns_empty_when_git_is_unavailable(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(128, '', 'not a git repository');

        $this->assertSame([], (new GitFiles($executor))->dirty('/project'));
    }

    public function test_since_lists_files_changed_against_a_ref(): void
    {
        $executor = (new FakeProcessExecutor())->queueSuccess("app/A.php\napp/B.php\n");

        $files = (new GitFiles($executor))->since('/project', 'main');

        $this->assertSame(['app/A.php', 'app/B.php'], $files);
        $spec = $executor->executed[0];
        $this->assertContains('diff', $spec->command);
        $this->assertContains('main', $spec->command);
    }

    public function test_since_returns_empty_on_git_failure(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(128, '', "unknown revision 'nope'");

        $this->assertSame([], (new GitFiles($executor))->since('/project', 'nope'));
    }
}
