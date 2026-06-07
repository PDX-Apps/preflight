<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit\Coverage;

use PdxApps\Preflight\Coverage\CloverReport;
use PdxApps\Preflight\Coverage\PatchCoverage;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PatchCoverage::class)]
final class PatchCoverageTest extends TestCase
{
    /**
     * @param array<string, array<int, int>> $files file => [line => hits]
     */
    private function report(array $files): CloverReport
    {
        $project = new TempProject();
        $body = '';
        foreach ($files as $name => $lines) {
            $body .= sprintf('<file name="%s/%s">', $project->root, $name);
            foreach ($lines as $line => $hits) {
                $body .= sprintf('<line num="%d" type="stmt" count="%d"/>', $line, $hits);
            }
            $body .= '</file>';
        }

        $path = $project->file('clover.xml', '<coverage><project>' . $body . '</project></coverage>');

        // Hold the project alive for the report read, then return the parsed report.
        $report = CloverReport::fromFile($path, $project->root);
        unset($project);

        return $report;
    }

    public function test_full_coverage_of_the_changed_lines_meets_any_threshold(): void
    {
        $report = $this->report(['src/Foo.php' => [10 => 1, 11 => 2, 12 => 1]]);
        $patch = PatchCoverage::compute(['src/Foo.php' => [[10, 12]]], $report);

        $this->assertSame(3, $patch->covered);
        $this->assertSame(3, $patch->total);
        $this->assertSame(100.0, $patch->percent());
        $this->assertSame([], $patch->uncovered);
        $this->assertTrue($patch->meets(100));
    }

    public function test_it_reports_uncovered_changed_lines_and_a_partial_percentage(): void
    {
        $report = $this->report(['src/Foo.php' => [10 => 1, 11 => 0, 12 => 0]]);
        $patch = PatchCoverage::compute(['src/Foo.php' => [[10, 12]]], $report);

        $this->assertSame(1, $patch->covered);
        $this->assertSame(3, $patch->total);
        $this->assertEqualsWithDelta(33.33, (float) $patch->percent(), 0.01);
        $this->assertSame(['src/Foo.php' => [11, 12]], $patch->uncovered);
        $this->assertFalse($patch->meets(100));
        $this->assertTrue($patch->meets(30));
    }

    public function test_changed_lines_that_are_not_executable_are_ignored(): void
    {
        // Lines 11 and 12 aren't in the report (braces/comments) — they don't count either way.
        $report = $this->report(['src/Foo.php' => [10 => 1]]);
        $patch = PatchCoverage::compute(['src/Foo.php' => [[10, 12]]], $report);

        $this->assertSame(1, $patch->covered);
        $this->assertSame(1, $patch->total);
        $this->assertSame(100.0, $patch->percent());
    }

    public function test_a_changed_file_absent_from_coverage_contributes_nothing(): void
    {
        $report = $this->report(['src/Tracked.php' => [1 => 1]]);
        $patch = PatchCoverage::compute(['config/app.php' => [[1, 50]]], $report);

        $this->assertSame(0, $patch->total);
        $this->assertNull($patch->percent(), 'nothing measurable means no percentage to judge');
        $this->assertTrue($patch->meets(100), 'an unmeasurable change cannot fail the gate');
    }

    public function test_overlapping_and_repeated_ranges_count_each_line_once(): void
    {
        $report = $this->report(['src/Foo.php' => [10 => 1, 11 => 0]]);
        $patch = PatchCoverage::compute(['src/Foo.php' => [[10, 11], [10, 10], [11, 11]]], $report);

        $this->assertSame(2, $patch->total, 'line 10 and 11 each counted once despite overlap');
        $this->assertSame(['src/Foo.php' => [11]], $patch->uncovered);
    }
}
