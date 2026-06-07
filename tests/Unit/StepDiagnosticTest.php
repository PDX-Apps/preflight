<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Diagnostics\StepDiagnostic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StepDiagnostic::class)]
final class StepDiagnosticTest extends TestCase
{
    public function test_it_exposes_its_fields(): void
    {
        $diagnostic = new StepDiagnostic(
            name: 'phpstan',
            label: 'PHPStan',
            tool: 'phpstan',
            toolInstalled: true,
            requireHint: 'phpstan/phpstan',
            config: 'phpstan.neon',
            configFound: false,
            willRun: true,
        );

        $this->assertSame('phpstan', $diagnostic->name);
        $this->assertSame('PHPStan', $diagnostic->label);
        $this->assertTrue($diagnostic->toolInstalled);
        $this->assertTrue($diagnostic->willRun);
    }

    public function test_it_serialises_to_an_array_for_the_json_format(): void
    {
        $diagnostic = new StepDiagnostic(
            name: 'phpstan',
            label: 'PHPStan',
            tool: 'phpstan',
            toolInstalled: false,
            requireHint: 'phpstan/phpstan',
            config: 'phpstan.neon',
            configFound: false,
            willRun: false,
        );

        $this->assertSame([
            'name' => 'phpstan',
            'label' => 'PHPStan',
            'tool' => 'phpstan',
            'toolInstalled' => false,
            'requireHint' => 'phpstan/phpstan',
            'config' => 'phpstan.neon',
            'configFound' => false,
            'willRun' => false,
        ], $diagnostic->toArray());
    }
}
