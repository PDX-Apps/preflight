<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Process\ProcessSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessSpec::class)]
final class ProcessSpecTest extends TestCase
{
    public function test_it_carries_a_command_with_sensible_defaults(): void
    {
        $spec = new ProcessSpec(['phpstan', 'analyse']);

        $this->assertSame(['phpstan', 'analyse'], $spec->command);
        $this->assertNull($spec->workingDirectory);
        $this->assertSame([], $spec->env);
        $this->assertNull($spec->timeout);
    }

    public function test_it_carries_working_directory_env_and_timeout(): void
    {
        $spec = new ProcessSpec(
            command: ['pint', '--test'],
            workingDirectory: '/project',
            env: ['APP_ENV' => 'testing'],
            timeout: 30.0,
        );

        $this->assertSame('/project', $spec->workingDirectory);
        $this->assertSame(['APP_ENV' => 'testing'], $spec->env);
        $this->assertSame(30.0, $spec->timeout);
    }
}
