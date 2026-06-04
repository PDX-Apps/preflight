<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Process\ProcessSpec;
use PdxApps\Preflight\Runner\SymfonyProcessExecutor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymfonyProcessExecutor::class)]
final class SymfonyProcessExecutorTest extends TestCase
{
    public function test_it_captures_stdout_and_a_zero_exit_for_a_successful_command(): void
    {
        $executor = new SymfonyProcessExecutor();

        $result = $executor->execute(new ProcessSpec([PHP_BINARY, '-r', 'echo "hello";']));

        $this->assertSame(0, $result->exitCode);
        $this->assertSame('hello', $result->stdout);
        $this->assertSame('', $result->stderr);
    }

    public function test_it_captures_the_exit_code_of_a_failing_command(): void
    {
        $executor = new SymfonyProcessExecutor();

        $result = $executor->execute(new ProcessSpec([PHP_BINARY, '-r', 'exit(3);']));

        $this->assertSame(3, $result->exitCode);
        $this->assertTrue($result->failed());
    }

    public function test_it_captures_stderr_separately(): void
    {
        $executor = new SymfonyProcessExecutor();

        $result = $executor->execute(new ProcessSpec([PHP_BINARY, '-r', 'fwrite(STDERR, "oops");']));

        $this->assertSame('oops', $result->stderr);
        $this->assertSame('', $result->stdout);
    }

    public function test_it_runs_in_the_given_working_directory(): void
    {
        $executor = new SymfonyProcessExecutor();

        $result = $executor->execute(new ProcessSpec([PHP_BINARY, '-r', 'echo getcwd();'], workingDirectory: sys_get_temp_dir()));

        $this->assertSame(realpath(sys_get_temp_dir()), realpath(trim($result->stdout)));
    }

    public function test_it_passes_environment_variables_through(): void
    {
        $executor = new SymfonyProcessExecutor();

        $result = $executor->execute(new ProcessSpec([PHP_BINARY, '-r', 'echo getenv("PREFLIGHT_TEST");'], env: ['PREFLIGHT_TEST' => 'on']));

        $this->assertSame('on', $result->stdout);
    }
}
