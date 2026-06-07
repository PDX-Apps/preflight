<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit\Coverage;

use PdxApps\Preflight\Coverage\CloverReport;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloverReport::class)]
final class CloverReportTest extends TestCase
{
    private function clover(TempProject $project, string $body): string
    {
        return $project->file('build/coverage.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            . '<coverage><project>' . $body . '</project></coverage>');
    }

    public function test_it_reads_statement_lines_with_their_hit_counts(): void
    {
        $project = new TempProject();
        $path = $this->clover($project, sprintf(
            '<file name="%s/src/Foo.php">'
            . '<line num="10" type="stmt" count="3"/>'
            . '<line num="11" type="stmt" count="0"/>'
            . '</file>',
            $project->root,
        ));

        $report = CloverReport::fromFile($path, $project->root);

        $this->assertSame([10 => 3, 11 => 0], $report->linesFor('src/Foo.php'));
    }

    public function test_it_normalizes_absolute_filenames_to_project_relative(): void
    {
        $project = new TempProject();
        $path = $this->clover($project, sprintf(
            '<file name="%s/src/Bar.php"><line num="1" type="stmt" count="1"/></file>',
            $project->root,
        ));

        // Looked up by the relative path git would report, not the absolute one in the report.
        $this->assertSame([1 => 1], CloverReport::fromFile($path, $project->root)->linesFor('src/Bar.php'));
    }

    public function test_it_ignores_non_statement_lines(): void
    {
        $project = new TempProject();
        $path = $this->clover($project, sprintf(
            '<file name="%s/src/Foo.php">'
            . '<line num="5" type="method" count="1"/>'
            . '<line num="6" type="stmt" count="1"/>'
            . '</file>',
            $project->root,
        ));

        // Method lines aren't statements coverage measures per-line; only the stmt is kept.
        $this->assertSame([6 => 1], CloverReport::fromFile($path, $project->root)->linesFor('src/Foo.php'));
    }

    public function test_an_unknown_file_has_no_lines(): void
    {
        $project = new TempProject();
        $path = $this->clover($project, '');

        $this->assertSame([], CloverReport::fromFile($path, $project->root)->linesFor('src/Missing.php'));
    }

    public function test_a_missing_report_file_yields_an_empty_report(): void
    {
        $project = new TempProject();

        $report = CloverReport::fromFile($project->root . '/build/none.xml', $project->root);

        $this->assertSame([], $report->linesFor('src/Foo.php'));
    }

    public function test_an_unreadable_report_file_yields_an_empty_report(): void
    {
        if (\function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('root bypasses file permissions, so open() would not fail');
        }

        $project = new TempProject();
        $path = $this->clover($project, '<file name="x"><line num="1" type="stmt" count="1"/></file>');
        chmod($path, 0o000);

        try {
            $report = CloverReport::fromFile($path, $project->root);
            $this->assertSame([], $report->linesFor('x'));
        } finally {
            chmod($path, 0o644); // let TempProject clean up
        }
    }
}
