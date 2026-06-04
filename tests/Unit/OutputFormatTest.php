<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\OutputFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutputFormat::class)]
final class OutputFormatTest extends TestCase
{
    public function test_it_is_backed_by_a_stable_string_value(): void
    {
        $this->assertSame('auto', OutputFormat::Auto->value);
        $this->assertSame('human', OutputFormat::Human->value);
        $this->assertSame('json', OutputFormat::Json->value);
        $this->assertSame('agent', OutputFormat::Agent->value);
        $this->assertSame('github', OutputFormat::Github->value);
        $this->assertSame('sarif', OutputFormat::Sarif->value);
    }

    public function test_auto_resolves_to_human_on_a_tty_and_agent_when_piped(): void
    {
        $this->assertSame(OutputFormat::Human, OutputFormat::Auto->resolve(isTty: true));
        $this->assertSame(OutputFormat::Agent, OutputFormat::Auto->resolve(isTty: false));
    }

    public function test_an_explicit_format_resolves_to_itself_regardless_of_tty(): void
    {
        $this->assertSame(OutputFormat::Json, OutputFormat::Json->resolve(isTty: true));
        $this->assertSame(OutputFormat::Json, OutputFormat::Json->resolve(isTty: false));
    }

    public function test_it_can_be_built_from_a_string_value(): void
    {
        $this->assertSame(OutputFormat::Github, OutputFormat::from('github'));
    }
}
