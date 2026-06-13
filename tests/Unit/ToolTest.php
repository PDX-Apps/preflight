<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tool::class)]
final class ToolTest extends TestCase
{
    public function test_vendor_bin_tool_is_resolved_under_vendor_bin(): void
    {
        $tool = Tool::vendorBin('phpstan', 'phpstan/phpstan');

        $this->assertSame('phpstan', $tool->binary);
        $this->assertTrue($tool->inVendorBin);
        $this->assertSame('phpstan/phpstan', $tool->requireHint);
    }

    public function test_system_tool_is_resolved_on_the_path(): void
    {
        $tool = Tool::system('composer');

        $this->assertSame('composer', $tool->binary);
        $this->assertFalse($tool->inVendorBin);
        $this->assertNull($tool->requireHint);
    }

    public function test_it_resolves_an_executable_path_under_vendor_bin(): void
    {
        $tool = Tool::vendorBin('pint');

        $this->assertSame('/project/vendor/bin/pint', $tool->resolvePath('/project'));
    }

    public function test_a_system_tool_resolves_to_its_bare_binary_name(): void
    {
        $tool = Tool::system('php');

        $this->assertSame('php', $tool->resolvePath('/project'));
    }
}
