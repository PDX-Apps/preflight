<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;
use SimpleXMLElement;

/**
 * Parses the JUnit XML emitted by PHPUnit, Paratest, and Pest (all three produce the same
 * `<testsuite>`/`<testcase>` shape). The runner writes `--log-junit` to a temp file and
 * places its contents in stdout for this parser.
 *
 * Each `<testcase>` containing a `<failure>` or `<error>` becomes an error {@see Finding}
 * with the test name, file, line, and the framework's message. A run with no failing cases
 * yields nothing. If the XML is missing or unparseable but the process failed (e.g. a fatal
 * before any test ran), a single finding is produced from stderr.
 */
final readonly class JUnitParser implements OutputParser
{
    public function __construct(
        private string $projectRoot,
        private string $tool = 'test',
    ) {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $xml = $this->load($result->stdout);
        if (!$xml instanceof \SimpleXMLElement) {
            return $this->fallback($result);
        }

        $findings = [];
        foreach ($this->testcases($xml) as $testcase) {
            $finding = $this->findingFor($testcase);
            if ($finding instanceof \PdxApps\Preflight\Finding) {
                $findings[] = $finding;
            }
        }

        return ParseResult::ofFindings($findings);
    }

    private function load(string $stdout): ?SimpleXMLElement
    {
        $stdout = trim($stdout);
        if ($stdout === '' || ! str_contains($stdout, '<testsuite')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($stdout);
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }

    /**
     * Every <testcase> in the document, at any nesting depth.
     *
     * @return list<SimpleXMLElement>
     */
    private function testcases(SimpleXMLElement $xml): array
    {
        $nodes = $xml->xpath('//testcase') ?: [];

        return array_values($nodes);
    }

    private function findingFor(SimpleXMLElement $testcase): ?Finding
    {
        $problem = $testcase->failure[0] ?? $testcase->error[0] ?? null;
        if ($problem === null) {
            return null;
        }

        $name = (string) $testcase['name'];
        $detail = trim((string) $problem);

        return new Finding(
            tool: $this->tool,
            severity: Severity::Error,
            message: $this->message($name, $detail),
            file: $this->file($testcase),
            line: isset($testcase['line']) ? (int) $testcase['line'] : null,
        );
    }

    private function message(string $name, string $detail): string
    {
        if ($detail === '') {
            return sprintf('%s failed.', $name);
        }

        // The detail often already starts with the test name; avoid duplicating it.
        return str_starts_with($detail, $name) ? $detail : sprintf('%s: %s', $name, $detail);
    }

    private function file(SimpleXMLElement $testcase): ?string
    {
        if (! isset($testcase['file'])) {
            return null;
        }

        // Pest uses "path::test description"; keep only the path part.
        $file = (string) $testcase['file'];
        $file = strtok($file, ':') ?: $file;

        return $this->relativize($file);
    }

    private function relativize(string $path): string
    {
        $root = rtrim($this->projectRoot, '/') . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    private function fallback(ProcessResult $result): ParseResult
    {
        if ($result->failed()) {
            $message = trim($result->stderr) !== '' ? trim($result->stderr) : 'Tests failed.';

            return ParseResult::ofFindings([new Finding($this->tool, Severity::Error, $message)]);
        }

        return ParseResult::empty();
    }
}
