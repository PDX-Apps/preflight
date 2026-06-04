<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessResult::class)]
final class ProcessResultTest extends TestCase
{
    public function test_a_zero_exit_code_is_successful(): void
    {
        $result = new ProcessResult(exitCode: 0, stdout: 'ok', stderr: '');

        $this->assertTrue($result->successful());
        $this->assertFalse($result->failed());
    }

    public function test_a_nonzero_exit_code_is_a_failure(): void
    {
        $result = new ProcessResult(exitCode: 2, stdout: '', stderr: 'boom');

        $this->assertFalse($result->successful());
        $this->assertTrue($result->failed());
    }

    public function test_combined_output_concatenates_stdout_then_stderr(): void
    {
        $result = new ProcessResult(exitCode: 1, stdout: "out\n", stderr: "err\n");

        $this->assertSame("out\nerr\n", $result->combinedOutput());
    }
}
