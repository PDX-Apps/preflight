<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\Target;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Target::class)]
final class TargetTest extends TestCase
{
    public function test_a_file_target_knows_it_is_a_file(): void
    {
        $target = Target::file('app/Foo.php');

        $this->assertSame('app/Foo.php', $target->path);
        $this->assertTrue($target->isFile);
        $this->assertFalse($target->isDirectory);
    }

    public function test_a_directory_target_knows_it_is_a_directory(): void
    {
        $target = Target::directory('app');

        $this->assertSame('app', $target->path);
        $this->assertFalse($target->isFile);
        $this->assertTrue($target->isDirectory);
    }

    public function test_a_files_containing_directory_is_its_dirname(): void
    {
        $this->assertSame('app/Http', Target::file('app/Http/Kernel.php')->containingDirectory());
    }

    public function test_a_directory_target_is_its_own_containing_directory(): void
    {
        $this->assertSame('app', Target::directory('app')->containingDirectory());
    }
}
