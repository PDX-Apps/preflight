<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Console\RunCommand;
use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Tests\Support\FakeProcessExecutor;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(RunCommand::class)]
final class RunCommandTest extends TestCase
{
    private function tester(TempProject $project, ProcessExecutor $executor): CommandTester
    {
        return new CommandTester(new RunCommand($project->root, $executor));
    }

    private function projectWithPint(): TempProject
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('vendor/bin/pint', '#!/usr/bin/env php');

        return $project;
    }

    /**
     * A project whose config runs only the Tests step with the patch-coverage gate on, so a
     * scoped run exercises the changed-line-range computation.
     */
    private function projectWithPatchCoverage(): TempProject
    {
        $project = new TempProject();
        $project->file('composer.json', '{}');
        $project->file('vendor/bin/phpunit', '#!/usr/bin/env php');
        $project->file('preflight.php', "<?php return PdxApps\\Preflight\\Preflight::configure()->withSteps(["
            . "PdxApps\\Preflight\\Steps\\Tests::make()->coverage(['clover' => 'build/coverage.xml'])->minPatchCoverage(100),"
            . "]);");

        return $project;
    }

    /**
     * The patch-coverage gate only engages when a coverage driver is active (it's detected at
     * the composition root, inside RunCommand), so these end-to-end tests skip without one
     * rather than failing — the patch logic itself is covered driver-free elsewhere.
     */
    private function skipWithoutCoverageDriver(): void
    {
        if (! \PdxApps\Preflight\Support\CoverageDriver::detect() instanceof \PdxApps\Preflight\Support\CoverageDriver) {
            $this->markTestSkipped('patch coverage needs a coverage driver (PCOV/phpdbg/Xdebug)');
        }
    }

    public function test_since_computes_changed_line_ranges_for_patch_coverage(): void
    {
        $this->skipWithoutCoverageDriver();
        $project = $this->projectWithPatchCoverage();
        $diff = "--- a/src/Foo.php\n+++ b/src/Foo.php\n@@ -1 +1 @@\n+x\n";
        $executor = (new FakeProcessExecutor())
            ->queueSuccess("src/Foo.php\n") // ScopeResolver: git diff --name-only
            ->queueSuccess($diff)           // changedLines: git diff --unified=0
            ->queueSuccess('');             // the phpunit run (empty junit)

        $tester = $this->tester($project, $executor);
        $exit = $tester->execute(['--since' => 'main'], ['decorated' => false]);

        $this->assertContains('--unified=0', $executor->commands()[1], 'the diff ranges are read with --unified=0');
        $this->assertSame(0, $exit, 'no clover present means nothing measurable, so the gate passes');
    }

    public function test_a_whole_project_run_computes_no_changed_line_ranges(): void
    {
        $project = $this->projectWithPatchCoverage();
        // No --since/--dirty: the patch gate is inert, so no git diff is consulted for ranges.
        $executor = (new FakeProcessExecutor())->queueSuccess(''); // just the phpunit run

        $tester = $this->tester($project, $executor);
        $exit = $tester->execute([], ['decorated' => false]);

        $this->assertSame(0, $exit);
        foreach ($executor->commands() as $command) {
            $this->assertNotContains('--unified=0', $command, 'a whole-project run reads no diff ranges');
        }
    }

    public function test_dirty_computes_changed_line_ranges_from_the_working_tree(): void
    {
        $this->skipWithoutCoverageDriver();
        $project = $this->projectWithPatchCoverage();
        $diff = "--- a/src/Foo.php\n+++ b/src/Foo.php\n@@ -1 +1 @@\n+x\n";
        $executor = (new FakeProcessExecutor())
            ->queueSuccess(" M src/Foo.php\n") // ScopeResolver: git status --porcelain
            ->queueSuccess($diff)             // changedLines: git diff --unified=0 HEAD
            ->queueSuccess('')                // changedLines: git ls-files --others
            ->queueSuccess('');               // the phpunit run

        $tester = $this->tester($project, $executor);
        $tester->execute(['--dirty' => true], ['decorated' => false]);

        $this->assertContains('HEAD', $executor->commands()[1], 'dirty ranges diff against HEAD');
        $this->assertContains('ls-files', $executor->commands()[2]);
    }

    public function test_a_passing_run_exits_zero_and_reports_success(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $tester = $this->tester($project, $executor);
        $exit = $tester->execute([], ['decorated' => false]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('passed', $tester->getDisplay());
    }

    public function test_a_failing_run_exits_one(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueFailure(1, '{"result":"fail","files":[{"path":"app/A.php","fixers":["x"]}]}');

        $tester = $this->tester($project, $executor);
        $exit = $tester->execute([], ['decorated' => false]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('app/A.php', $tester->getDisplay());
    }

    public function test_json_format_emits_a_parseable_document(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $tester = $this->tester($project, $executor);
        $tester->execute(['--format' => 'json'], ['decorated' => false]);

        $decoded = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($decoded['success']);
    }

    public function test_fix_mode_omits_the_test_flag_from_the_pint_command(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $tester = $this->tester($project, $executor);
        $tester->execute(['--fix' => true], ['decorated' => false]);

        $command = $executor->commands()[0];
        $this->assertNotContains('--test', $command);
    }

    public function test_files_option_scopes_the_pint_command_to_those_paths(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $tester = $this->tester($project, $executor);
        $tester->execute(['--files' => 'app/A.php,app/B.php'], ['decorated' => false]);

        $command = $executor->commands()[0];
        $this->assertContains('app/A.php', $command);
        $this->assertContains('app/B.php', $command);
    }

    public function test_positional_paths_scope_the_run(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $tester = $this->tester($project, $executor);
        $tester->execute(['paths' => ['app/Only.php']], ['decorated' => false]);

        $this->assertContains('app/Only.php', $executor->commands()[0]);
    }

    public function test_module_option_scopes_the_run_to_the_modules_directories(): void
    {
        $project = $this->projectWithPint();
        $project->dir('Modules/Billing/app');
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $tester = $this->tester($project, $executor);
        $tester->execute(['--module' => 'Billing'], ['decorated' => false]);

        // Pint targets Files; a module's existing app dir is passed through (default layout).
        $this->assertContains('Modules/Billing/app', $executor->commands()[0]);
    }

    // --- config defaults: fixByDefault / dirtyByDefault, with CLI overrides ---

    public function test_fix_by_default_config_runs_in_fix_mode_without_the_fix_flag(): void
    {
        $project = $this->projectWithPint();
        $project->file('preflight.php', "<?php return PdxApps\\Preflight\\Preflight::configure()->fixByDefault();");
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"fixed","files":[]}');

        $this->tester($project, $executor)->execute([], ['decorated' => false]);

        // Fix mode omits Pint's --test flag.
        $this->assertNotContains('--test', $executor->commands()[0]);
    }

    public function test_check_flag_overrides_fix_by_default(): void
    {
        $project = $this->projectWithPint();
        $project->file('preflight.php', "<?php return PdxApps\\Preflight\\Preflight::configure()->fixByDefault();");
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $this->tester($project, $executor)->execute(['--check' => true], ['decorated' => false]);

        $this->assertContains('--test', $executor->commands()[0], '--check forces check mode');
    }

    public function test_dirty_by_default_config_scopes_to_working_tree_changes(): void
    {
        $project = $this->projectWithPint();
        $project->file('preflight.php', "<?php return PdxApps\\Preflight\\Preflight::configure()->dirtyByDefault();");
        // git status (dirty) is consulted first, then the pint run.
        $executor = (new FakeProcessExecutor())
            ->queueSuccess(" M app/Changed.php\n")
            ->queueSuccess('{"result":"pass"}');

        $this->tester($project, $executor)->execute([], ['decorated' => false]);

        // The pint command (2nd executed) is scoped to the changed file.
        $this->assertContains('app/Changed.php', $executor->commands()[1]);
    }

    public function test_all_flag_overrides_dirty_by_default(): void
    {
        $project = $this->projectWithPint();
        $project->file('preflight.php', "<?php return PdxApps\\Preflight\\Preflight::configure()->dirtyByDefault();");
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $this->tester($project, $executor)->execute(['--all' => true], ['decorated' => false]);

        // Whole-project run: git is never consulted, and no file paths are appended to pint.
        $this->assertSame([], array_filter($executor->commands()[0], static fn ($a) => str_ends_with((string) $a, '.php')));
    }

    public function test_explicit_scope_flags_override_dirty_by_default(): void
    {
        $project = $this->projectWithPint();
        $project->file('preflight.php', "<?php return PdxApps\\Preflight\\Preflight::configure()->dirtyByDefault();");
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        // --files is explicit scope; it should win over the dirty default (git not consulted).
        $this->tester($project, $executor)->execute(['--files' => 'app/Only.php'], ['decorated' => false]);

        $this->assertContains('app/Only.php', $executor->commands()[0]);
        $this->assertCount(1, $executor->executed, 'git status not run; only pint executed');
    }

    // --- report artifact ---

    public function test_report_writes_a_json_artifact_with_metadata_and_findings(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueFailure(1, '{"result":"fail","files":[{"path":"app/A.php","fixers":["x"]}]}');
        $reportPath = $project->root . '/build/preflight.json';

        $this->tester($project, $executor)->execute(['--report' => $reportPath], ['decorated' => false]);

        $this->assertFileExists($reportPath);
        $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($report['success']);
        $this->assertArrayHasKey('ranAt', $report);
        $this->assertArrayHasKey('findings', $report); // default include
        $this->assertArrayHasKey('steps', $report);    // default include
        $this->assertSame('app/A.php', $report['findings'][0]['file']);
    }

    public function test_report_include_controls_the_sections(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueFailure(1, '{"result":"fail","files":[{"path":"app/A.php","fixers":["x"]}]}');
        $reportPath = $project->root . '/r.json';

        $this->tester($project, $executor)->execute(
            ['--report' => $reportPath, '--report-include' => 'findings'],
            ['decorated' => false],
        );

        $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('findings', $report);
        $this->assertArrayNotHasKey('steps', $report, 'only findings requested');
    }

    public function test_no_report_is_written_without_the_option(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $this->tester($project, $executor)->execute([], ['decorated' => false]);

        $this->assertFileDoesNotExist($project->root . '/preflight.json');
    }

    // --- multi-output (--write) ---

    public function test_write_renders_extra_formats_to_files_from_a_single_run(): void
    {
        $project = $this->projectWithPint();
        // The auto-detected set here is Pint + ComposerAudit (composer is always present), so
        // a single run executes exactly two commands. Two --write targets must add none.
        $executor = (new FakeProcessExecutor())
            ->queueSuccess('{"result":"pass"}')
            ->queueSuccess('{"advisories":[]}');

        $sarif = $project->root . '/out/preflight.sarif';
        $summary = $project->root . '/out/summary.md';

        $tester = $this->tester($project, $executor);
        $exit = $tester->execute(
            ['--format' => 'github', '--write' => ["sarif:{$sarif}", "markdown:{$summary}"]],
            ['decorated' => false],
        );

        $this->assertSame(0, $exit);
        $this->assertCount(2, $executor->executed, 'the checks run once regardless of output count');

        $this->assertFileExists($sarif);
        $sarifDoc = json_decode((string) file_get_contents($sarif), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('runs', $sarifDoc);

        $this->assertFileExists($summary);
        $this->assertStringContainsString('Preflight', (string) file_get_contents($summary));
    }

    public function test_write_rejects_a_spec_without_a_path(): void
    {
        $project = $this->projectWithPint();
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $this->expectException(\InvalidArgumentException::class);
        $this->tester($project, $executor)->execute(['--write' => ['sarif']], ['decorated' => false]);
    }

    // --- skip-if-fresh ---

    public function test_a_run_writes_the_freshness_cache(): void
    {
        $project = $this->projectWithPint();
        $project->file('app/A.php', '<?php // a');
        $executor = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');

        $this->tester($project, $executor)->execute(['--files' => 'app/A.php'], ['decorated' => false]);

        $this->assertFileExists($project->root . '/.preflight.cache.json');
    }

    public function test_skip_if_fresh_skips_a_second_unchanged_run(): void
    {
        $project = $this->projectWithPint();
        $project->file('app/A.php', '<?php // a');

        // First run: passes, populates the cache.
        $first = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');
        $exit1 = $this->tester($project, $first)->execute(['--files' => 'app/A.php', '--skip-if-fresh' => true], ['decorated' => false]);
        $this->assertSame(0, $exit1);
        $this->assertCount(1, $first->executed, 'first run executes pint');

        // Second run, nothing changed: should skip without executing anything.
        $second = new FakeProcessExecutor();
        $tester = $this->tester($project, $second);
        $exit2 = $tester->execute(['--files' => 'app/A.php', '--skip-if-fresh' => true], ['decorated' => false]);

        $this->assertSame(0, $exit2);
        $this->assertSame([], $second->executed, 'fresh run skips the tools');
        $this->assertStringContainsString('fresh', strtolower($tester->getDisplay()));
    }

    public function test_skip_if_fresh_reruns_when_a_file_changed(): void
    {
        $project = $this->projectWithPint();
        $project->file('app/A.php', '<?php // a');

        $first = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');
        $this->tester($project, $first)->execute(['--files' => 'app/A.php', '--skip-if-fresh' => true], ['decorated' => false]);

        // The file changes -> hash differs -> must re-run.
        $project->file('app/A.php', '<?php // a CHANGED');
        $second = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');
        $this->tester($project, $second)->execute(['--files' => 'app/A.php', '--skip-if-fresh' => true], ['decorated' => false]);

        $this->assertCount(1, $second->executed, 'changed input re-runs the tools');
    }

    public function test_skip_if_fresh_reruns_after_a_failed_run(): void
    {
        $project = $this->projectWithPint();
        $project->file('app/A.php', '<?php // a');

        // First run fails.
        $first = (new FakeProcessExecutor())->queueFailure(1, '{"result":"fail","files":[{"path":"app/A.php","fixers":["x"]}]}');
        $this->tester($project, $first)->execute(['--files' => 'app/A.php', '--skip-if-fresh' => true], ['decorated' => false]);

        // Even with identical inputs, a prior failure must re-run (never skip a failure).
        $second = (new FakeProcessExecutor())->queueSuccess('{"result":"pass"}');
        $this->tester($project, $second)->execute(['--files' => 'app/A.php', '--skip-if-fresh' => true], ['decorated' => false]);

        $this->assertCount(1, $second->executed, 'a failed run is never treated as fresh');
    }
}
