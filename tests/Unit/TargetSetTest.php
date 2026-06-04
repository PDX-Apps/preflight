<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\Target;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Targeting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TargetSet::class)]
final class TargetSetTest extends TestCase
{
    public function test_the_whole_project_set_is_not_narrowed_and_yields_no_path_args(): void
    {
        $set = TargetSet::wholeProject();

        $this->assertFalse($set->isNarrowed());
        $this->assertTrue($set->isEmpty());
        $this->assertSame([], $set->pathsFor(Targeting::Files));
        $this->assertSame([], $set->pathsFor(Targeting::Paths));
        $this->assertSame([], $set->pathsFor(Targeting::Whole));
    }

    public function test_a_narrowed_set_reports_files_and_directories_separately(): void
    {
        $set = TargetSet::narrowed([
            Target::file('app/Foo.php'),
            Target::directory('app/Http'),
            Target::file('app/Bar.php'),
        ]);

        $this->assertTrue($set->isNarrowed());
        $this->assertFalse($set->isEmpty());
        $this->assertSame(['app/Foo.php', 'app/Bar.php'], $set->files());
        $this->assertSame(['app/Http'], $set->directories());
    }

    public function test_files_targeting_passes_every_path_through_as_is(): void
    {
        $set = TargetSet::narrowed([
            Target::file('app/Foo.php'),
            Target::directory('app/Http'),
        ]);

        $this->assertSame(['app/Foo.php', 'app/Http'], $set->pathsFor(Targeting::Files));
    }

    public function test_paths_targeting_widens_files_to_containing_dirs_and_dedupes(): void
    {
        $set = TargetSet::narrowed([
            Target::file('app/Http/Kernel.php'),
            Target::file('app/Http/Middleware.php'),
            Target::file('app/Foo.php'),
            Target::directory('database'),
        ]);

        $this->assertSame(['app/Http', 'app', 'database'], $set->pathsFor(Targeting::Paths));
    }

    public function test_whole_targeting_never_yields_path_args_even_when_narrowed(): void
    {
        $set = TargetSet::narrowed([Target::file('app/Foo.php')]);

        $this->assertSame([], $set->pathsFor(Targeting::Whole));
    }

    public function test_a_whole_step_must_skip_only_when_the_set_is_narrowed(): void
    {
        $this->assertTrue(TargetSet::narrowed([Target::file('a.php')])->forcesSkip(Targeting::Whole));
        $this->assertFalse(TargetSet::wholeProject()->forcesSkip(Targeting::Whole));
        $this->assertFalse(TargetSet::narrowed([Target::file('a.php')])->forcesSkip(Targeting::Files));
    }
}
