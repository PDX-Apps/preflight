<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Parsing\ComposerAuditParser;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerAuditParser::class)]
final class ComposerAuditParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/composer-audit/' . $name);
    }

    private function parser(): ComposerAuditParser
    {
        return new ComposerAuditParser();
    }

    public function test_a_clean_result_yields_nothing(): void
    {
        // Both "advisories" and "abandoned" are empty arrays ([]) when nothing is found.
        $result = $this->parser()->parse(new ProcessResult(0, $this->fixture('clean.json'), ''));

        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->changed);
    }

    public function test_each_advisory_becomes_an_error_finding(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-advisories.json'), ''))->findings;

        $advisories = array_values(array_filter($findings, static fn ($f) => $f->rule !== 'abandoned'));
        $this->assertCount(3, $advisories);
        foreach ($advisories as $finding) {
            $this->assertSame('composer-audit', $finding->tool);
            $this->assertSame(Severity::Error, $finding->severity);
            $this->assertSame('composer.lock', $finding->file);
            $this->assertFalse($finding->fixable);
        }
    }

    public function test_an_advisory_carries_the_package_title_and_cve(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-advisories.json'), ''))->findings;
        $kernel = $this->findByRule($findings, 'CVE-2026-45075');

        $this->assertNotNull($kernel);
        $this->assertStringContainsString('symfony/http-kernel', $kernel->message);
        $this->assertStringContainsString('HEAD Request Bypasses', $kernel->message);
    }

    public function test_a_null_severity_advisory_is_tolerated_and_still_an_error(): void
    {
        // symfony/http-foundation in the fixture has "severity": null — must not crash.
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-advisories.json'), ''))->findings;
        $foundation = $this->findByRule($findings, 'CVE-2026-48736');

        $this->assertNotNull($foundation);
        $this->assertSame(Severity::Error, $foundation->severity);
    }

    public function test_abandoned_packages_become_non_failing_warnings(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(0, $this->fixture('abandoned-only.json'), ''))->findings;

        $this->assertCount(2, $findings);
        foreach ($findings as $finding) {
            $this->assertSame(Severity::Warning, $finding->severity);
            $this->assertSame('abandoned', $finding->rule);
            $this->assertSame('composer.lock', $finding->file);
        }
    }

    public function test_an_abandoned_package_mentions_its_replacement_when_one_exists(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(0, $this->fixture('abandoned-only.json'), ''))->findings;

        $messages = array_map(static fn ($f) => $f->message, $findings);
        $withReplacement = implode("\n", $messages);

        $this->assertStringContainsString('swiftmailer/swiftmailer', $withReplacement);
        $this->assertStringContainsString('symfony/mailer', $withReplacement, 'the suggested replacement is shown');
        $this->assertStringContainsString('doctrine/annotations', $withReplacement);
    }

    public function test_advisories_and_abandoned_are_reported_together(): void
    {
        // The populated fixture has 3 advisories and 2 abandoned packages.
        $findings = $this->parser()->parse(new ProcessResult(1, $this->fixture('with-advisories.json'), ''))->findings;

        $this->assertCount(5, $findings);
    }

    public function test_a_failure_with_unparseable_output_falls_back_to_stderr(): void
    {
        $findings = $this->parser()->parse(new ProcessResult(1, 'not json', './composer.lock not found'))->findings;

        $this->assertCount(1, $findings);
        $this->assertSame(Severity::Error, $findings[0]->severity);
        $this->assertStringContainsString('composer.lock not found', $findings[0]->message);
    }

    public function test_the_tool_label_is_configurable(): void
    {
        $findings = (new ComposerAuditParser('audit'))
            ->parse(new ProcessResult(1, $this->fixture('with-advisories.json'), ''))->findings;

        $this->assertSame('audit', $findings[0]->tool);
    }

    /**
     * @param  list<\PdxApps\Preflight\Finding>  $findings
     */
    private function findByRule(array $findings, string $rule): ?\PdxApps\Preflight\Finding
    {
        foreach ($findings as $finding) {
            if ($finding->rule === $rule) {
                return $finding;
            }
        }

        return null;
    }
}
