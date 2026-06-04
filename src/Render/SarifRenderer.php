<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Contracts\Renderer;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Severity;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders findings as a SARIF 2.1.0 document — the format GitHub code scanning (and other
 * tools) ingest, e.g. uploaded via `github/codeql-action/upload-sarif`.
 *
 * Because Preflight aggregates several analysers, findings are grouped by their reporting
 * tool into one SARIF `run` per tool (each `run.tool.driver.name` is the analyser), which
 * keeps results attributed to the tool that produced them. A clean run emits an empty `runs`
 * array — still a valid SARIF document.
 */
final class SarifRenderer implements Renderer
{
    private const string SCHEMA = 'https://json.schemastore.org/sarif-2.1.0.json';

    private const string INFORMATION_URI = 'https://github.com/PDX-Apps/preflight';

    public function render(RunResult $result, OutputInterface $output): void
    {
        $output->writeln((string) json_encode(
            [
                '$schema' => self::SCHEMA,
                'version' => '2.1.0',
                'runs' => $this->runs($result),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runs(RunResult $result): array
    {
        $byTool = [];
        foreach ($result->findings() as $finding) {
            $byTool[$finding->tool][] = $finding;
        }

        $runs = [];
        foreach ($byTool as $tool => $findings) {
            $runs[] = [
                'tool' => ['driver' => ['name' => $tool, 'informationUri' => self::INFORMATION_URI]],
                'results' => array_map($this->result(...), $findings),
            ];
        }

        return $runs;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Finding $finding): array
    {
        return [
            'ruleId' => $finding->rule ?? $finding->tool,
            'level' => $this->level($finding->severity),
            'message' => ['text' => $finding->message],
            'locations' => $this->locations($finding),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function locations(Finding $finding): array
    {
        if ($finding->file === null) {
            return [];
        }

        $physicalLocation = ['artifactLocation' => ['uri' => $finding->file]];

        $region = $this->region($finding);
        if ($region !== []) {
            $physicalLocation['region'] = $region;
        }

        return [['physicalLocation' => $physicalLocation]];
    }

    /**
     * @return array<string, int>
     */
    private function region(Finding $finding): array
    {
        $region = [];
        if ($finding->line !== null) {
            $region['startLine'] = $finding->line;
        }
        if ($finding->column !== null) {
            $region['startColumn'] = $finding->column;
        }

        return $region;
    }

    private function level(Severity $severity): string
    {
        return match ($severity) {
            Severity::Error => 'error',
            Severity::Warning => 'warning',
            Severity::Info => 'note',
        };
    }
}
