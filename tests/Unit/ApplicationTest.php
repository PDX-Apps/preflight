<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Console\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    public function test_it_registers_every_built_in_command(): void
    {
        $application = new Application();

        foreach (['run', 'doctor', 'steps', 'init', 'install'] as $command) {
            $this->assertTrue($application->isKnownCommand($command), $command . ' should be registered');
        }
    }

    public function test_an_unregistered_name_is_not_a_known_command(): void
    {
        $this->assertFalse(new Application()->isKnownCommand('app/Some/Path.php'));
    }
}
