<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Render\JsonRenderer;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Result\StepResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(JsonRenderer::class)]
final class JsonRendererTest extends TestCase
{
    private function decode(RunResult $result): array
    {
        $output = new BufferedOutput();
        (new JsonRenderer())->render($result, $output);

        return json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_it_emits_the_run_result_array_shape(): void
    {
        $finding = new Finding('phpstan', Severity::Error, 'boom', 'app/Foo.php', 12);
        $result = new RunResult([
            StepResult::failed('phpstan', 'PHPStan', findings: [$finding], durationSeconds: 1.0, exitCode: 1),
        ]);

        $decoded = $this->decode($result);

        $this->assertFalse($decoded['success']);
        $this->assertCount(1, $decoded['steps']);
        $this->assertSame('phpstan', $decoded['steps'][0]['name']);
        $this->assertSame('failed', $decoded['steps'][0]['status']);
        $this->assertSame('app/Foo.php', $decoded['findings'][0]['file']);
        $this->assertSame('error', $decoded['findings'][0]['severity']);
    }

    public function test_a_passing_run_reports_success_true_and_no_findings(): void
    {
        $result = new RunResult([StepResult::passed('pint', 'Pint', durationSeconds: 0.5)]);

        $decoded = $this->decode($result);

        $this->assertTrue($decoded['success']);
        $this->assertSame([], $decoded['findings']);
    }

    public function test_the_output_is_a_single_valid_json_document(): void
    {
        $output = new BufferedOutput();
        (new JsonRenderer())->render(new RunResult([]), $output);

        // Must parse as one document (no banner lines, no trailing noise).
        $this->assertIsArray(json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR));
    }
}
