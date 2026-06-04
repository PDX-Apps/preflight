<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Finding::class)]
final class FindingTest extends TestCase
{
    public function test_it_exposes_its_data_as_readonly_properties(): void
    {
        $finding = new Finding(
            tool: 'phpstan',
            severity: Severity::Error,
            message: 'Undefined variable $x',
            file: 'app/Foo.php',
            line: 12,
            column: 5,
            rule: 'variable.undefined',
            fixable: false,
        );

        $this->assertSame('phpstan', $finding->tool);
        $this->assertSame(Severity::Error, $finding->severity);
        $this->assertSame('Undefined variable $x', $finding->message);
        $this->assertSame('app/Foo.php', $finding->file);
        $this->assertSame(12, $finding->line);
        $this->assertSame(5, $finding->column);
        $this->assertSame('variable.undefined', $finding->rule);
        $this->assertFalse($finding->fixable);
    }

    public function test_location_fields_are_optional(): void
    {
        $finding = new Finding(
            tool: 'composer-audit',
            severity: Severity::Warning,
            message: 'CVE-2025-0001 in acme/widget',
        );

        $this->assertNull($finding->file);
        $this->assertNull($finding->line);
        $this->assertNull($finding->column);
        $this->assertNull($finding->rule);
        $this->assertFalse($finding->fixable);
    }

    public function test_it_serializes_to_a_stable_array_shape(): void
    {
        $finding = new Finding(
            tool: 'pint',
            severity: Severity::Warning,
            message: 'Style issue',
            file: 'app/Bar.php',
            line: 3,
            fixable: true,
        );

        $this->assertSame([
            'tool' => 'pint',
            'severity' => 'warning',
            'message' => 'Style issue',
            'file' => 'app/Bar.php',
            'line' => 3,
            'column' => null,
            'rule' => null,
            'fixable' => true,
        ], $finding->toArray());
    }
}
