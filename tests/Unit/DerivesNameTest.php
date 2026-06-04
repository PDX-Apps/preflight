<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Steps\Concerns\DerivesName;
use PdxApps\Preflight\Tests\Unit\Fixtures\ComposerAudit;
use PdxApps\Preflight\Tests\Unit\Fixtures\HTMLValidator;
use PdxApps\Preflight\Tests\Unit\Fixtures\Pint;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversTrait(DerivesName::class)]
final class DerivesNameTest extends TestCase
{
    public function test_single_word_class_becomes_a_lowercase_name(): void
    {
        $this->assertSame('pint', (new Pint())->name());
    }

    public function test_studly_class_name_becomes_kebab_case(): void
    {
        $this->assertSame('composer-audit', (new ComposerAudit())->name());
    }

    public function test_consecutive_capitals_are_treated_as_one_word(): void
    {
        $this->assertSame('html-validator', (new HTMLValidator())->name());
    }
}
