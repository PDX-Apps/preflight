<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Runner\NullProgressReporter;
use PdxApps\Preflight\Tests\Support\FakeStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullProgressReporter::class)]
final class NullProgressReporterTest extends TestCase
{
    public function test_it_accepts_events_and_does_nothing(): void
    {
        $reporter = new NullProgressReporter();

        $reporter->stepStarted(new FakeStep('pint', StepPlan::exitCode('pint', ['true'])));
        $reporter->stepFinished(StepResult::passed('pint', 'Pint', durationSeconds: 0.1));

        $this->expectNotToPerformAssertions();
    }
}
