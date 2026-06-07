<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\GitFiles;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PdxApps\Preflight\Tests\Support\TempProject;
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

    // --- patch-coverage line ranges ---

    public function test_since_ranges_parses_new_side_hunk_spans_per_file(): void
    {
        $diff = "diff --git a/src/Foo.php b/src/Foo.php\n"
            . "--- a/src/Foo.php\n"
            . "+++ b/src/Foo.php\n"
            . "@@ -10,0 +11,2 @@\n+added one\n+added two\n"
            . "@@ -20 +22 @@\n+changed line\n";
        $executor = (new FakeProcessExecutor())->queueSuccess($diff);

        $ranges = (new GitFiles($executor))->sinceRanges('/project', 'main');

        $this->assertSame(['src/Foo.php' => [[11, 12], [22, 22]]], $ranges);
        $this->assertContains('--unified=0', $executor->executed[0]->command);
    }

    public function test_since_ranges_treats_a_new_file_as_added_from_line_one(): void
    {
        $diff = "--- /dev/null\n+++ b/src/New.php\n@@ -0,0 +1,3 @@\n+a\n+b\n+c\n";
        $executor = (new FakeProcessExecutor())->queueSuccess($diff);

        $this->assertSame(['src/New.php' => [[1, 3]]], (new GitFiles($executor))->sinceRanges('/project', 'main'));
    }

    public function test_since_ranges_skips_pure_deletion_hunks(): void
    {
        $diff = "--- a/src/Foo.php\n+++ b/src/Foo.php\n@@ -5,2 +4,0 @@\n-gone one\n-gone two\n";
        $executor = (new FakeProcessExecutor())->queueSuccess($diff);

        $this->assertSame([], (new GitFiles($executor))->sinceRanges('/project', 'main'), 'a +N,0 hunk adds nothing');
    }

    public function test_since_ranges_returns_empty_on_git_failure(): void
    {
        $executor = (new FakeProcessExecutor())->queueFailure(128);

        $this->assertSame([], (new GitFiles($executor))->sinceRanges('/project', 'nope'));
    }

    public function test_dirty_ranges_combines_tracked_hunks_with_whole_untracked_files(): void
    {
        $project = new TempProject();
        $project->file('src/New.php', "<?php\n\$a = 1;\n\$b = 2;\n"); // 3 lines
        $tracked = "--- a/src/Foo.php\n+++ b/src/Foo.php\n@@ -3 +3,2 @@\n+one\n+two\n";
        $executor = (new FakeProcessExecutor())
            ->queueSuccess($tracked)         // git diff HEAD
            ->queueSuccess("src/New.php\n"); // git ls-files --others

        $ranges = (new GitFiles($executor))->dirtyRanges($project->root);

        $this->assertSame([[3, 4]], $ranges['src/Foo.php']);
        $this->assertSame([[1, 3]], $ranges['src/New.php'], 'untracked file counted whole');
    }

    public function test_dirty_ranges_ignores_an_unreadable_or_empty_untracked_file(): void
    {
        $project = new TempProject();
        $project->file('src/Empty.php', '');
        $executor = (new FakeProcessExecutor())
            ->queueSuccess('')                                  // git diff HEAD (no tracked changes)
            ->queueSuccess("src/Empty.php\nsrc/Missing.php\n"); // empty + nonexistent

        $this->assertSame([], (new GitFiles($executor))->dirtyRanges($project->root));
    }

    public function test_since_ranges_ignores_a_malformed_hunk_header(): void
    {
        // A `@@` line that isn't a valid hunk header contributes no range.
        $diff = "--- a/src/Foo.php\n+++ b/src/Foo.php\n@@ not a real header @@\n";
        $executor = (new FakeProcessExecutor())->queueSuccess($diff);

        $this->assertSame([], (new GitFiles($executor))->sinceRanges('/project', 'main'));
    }

    public function test_since_ranges_ignores_a_new_side_of_dev_null(): void
    {
        // `+++ /dev/null` is a deletion's new side — no current file, so it's skipped.
        $diff = "--- a/src/Foo.php\n+++ /dev/null\n@@ -1,2 +0,0 @@\n-a\n-b\n";
        $executor = (new FakeProcessExecutor())->queueSuccess($diff);

        $this->assertSame([], (new GitFiles($executor))->sinceRanges('/project', 'main'));
    }

    public function test_dirty_ranges_survives_git_diff_failure(): void
    {
        $project = new TempProject();
        $executor = (new FakeProcessExecutor())
            ->queueFailure(128) // git diff HEAD fails (e.g. no commits yet)
            ->queueSuccess('');  // git ls-files

        $this->assertSame([], (new GitFiles($executor))->dirtyRanges($project->root));
    }
}
