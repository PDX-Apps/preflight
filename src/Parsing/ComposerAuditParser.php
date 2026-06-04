<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Parsing;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Process\ProcessResult;
use PdxApps\Preflight\Severity;

/**
 * Parses `composer audit --format=json` output.
 *
 * The document is `{"advisories": {...}, "abandoned": {...}}`, where each of the two keys is
 * an **object keyed by package** when populated but an empty **array** (`[]`) when nothing is
 * found — so both shapes must be tolerated.
 *
 * - Each advisory becomes an error {@see Finding}: any known CVE is a failure regardless of
 *   its CVSS `severity` (which can even be `null`). The package, title, and CVE are carried
 *   on the finding; the audited artifact (`composer.lock`) is used as its file.
 * - Each abandoned package becomes a *warning* finding (visible but non-failing, matching the
 *   step's `--abandoned=report`), naming the suggested replacement when Composer provides one.
 *
 * On a hard failure with no parseable JSON (e.g. a missing lock file, emitted to stderr), a
 * single finding is produced from stderr so the failure is never silently dropped.
 */
final readonly class ComposerAuditParser implements OutputParser
{
    private const string ARTIFACT = 'composer.lock';

    public function __construct(private string $tool = 'composer-audit')
    {
    }

    public function parse(ProcessResult $result): ParseResult
    {
        $decoded = json_decode($result->stdout, true);

        if (! is_array($decoded) || ! array_key_exists('advisories', $decoded) && ! array_key_exists('abandoned', $decoded)) {
            return $this->fallback($result);
        }

        $findings = [
            ...$this->advisoryFindings($decoded['advisories'] ?? null),
            ...$this->abandonedFindings($decoded['abandoned'] ?? null),
        ];

        return ParseResult::ofFindings($findings);
    }

    /**
     * @return list<Finding>
     */
    private function advisoryFindings(mixed $advisories): array
    {
        if (! is_array($advisories)) {
            return [];
        }

        $findings = [];
        foreach ($advisories as $perPackage) {
            if (! is_array($perPackage)) {
                continue;
            }

            foreach ($perPackage as $advisory) {
                if (is_array($advisory)) {
                    $findings[] = $this->advisoryFinding($advisory);
                }
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $advisory
     */
    private function advisoryFinding(array $advisory): Finding
    {
        $package = isset($advisory['packageName']) ? (string) $advisory['packageName'] : 'unknown package';
        $title = isset($advisory['title']) ? (string) $advisory['title'] : 'Known security advisory.';
        $cve = isset($advisory['cve']) && $advisory['cve'] !== '' ? (string) $advisory['cve'] : null;
        $rule = $cve ?? (isset($advisory['advisoryId']) ? (string) $advisory['advisoryId'] : null);

        return new Finding(
            tool: $this->tool,
            severity: Severity::Error,
            message: sprintf('%s: %s', $package, $title),
            file: self::ARTIFACT,
            rule: $rule,
        );
    }

    /**
     * @return list<Finding>
     */
    private function abandonedFindings(mixed $abandoned): array
    {
        if (! is_array($abandoned)) {
            return [];
        }

        $findings = [];
        foreach ($abandoned as $package => $replacement) {
            $findings[] = new Finding(
                tool: $this->tool,
                severity: Severity::Warning,
                message: $this->abandonedMessage((string) $package, $replacement),
                file: self::ARTIFACT,
                rule: 'abandoned',
            );
        }

        return $findings;
    }

    private function abandonedMessage(string $package, mixed $replacement): string
    {
        return is_string($replacement) && $replacement !== ''
            ? sprintf('%s is abandoned; use %s instead.', $package, $replacement)
            : sprintf('%s is abandoned with no replacement suggested.', $package);
    }

    private function fallback(ProcessResult $result): ParseResult
    {
        if ($result->failed()) {
            $message = trim($result->stderr) !== '' ? trim($result->stderr) : 'composer audit failed.';

            return ParseResult::ofFindings([new Finding($this->tool, Severity::Error, $message)]);
        }

        return ParseResult::empty();
    }
}
