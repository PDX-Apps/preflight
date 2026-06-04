<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Parsing\ParseResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParseResult::class)]
final class ParseResultTest extends TestCase
{
    public function test_empty_has_no_findings_and_no_changed_files(): void
    {
        $result = ParseResult::empty();

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_of_findings_carries_findings_and_no_changes(): void
    {
        $finding = new Finding('pint', Severity::Warning, 'x');
        $result = ParseResult::ofFindings([$finding]);

        $this->assertSame([$finding], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_of_changed_carries_changed_files_and_no_findings(): void
    {
        $result = ParseResult::ofChanged(['app/A.php', 'app/B.php']);

        $this->assertSame(['app/A.php', 'app/B.php'], $result->changed);
        $this->assertSame([], $result->findings);
    }

    public function test_it_can_carry_both(): void
    {
        $finding = new Finding('pint', Severity::Warning, 'x');
        $result = new ParseResult(findings: [$finding], changed: ['app/A.php']);

        $this->assertSame([$finding], $result->findings);
        $this->assertSame(['app/A.php'], $result->changed);
    }
}
