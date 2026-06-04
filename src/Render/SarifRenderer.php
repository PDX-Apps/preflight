<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Contracts\Renderer;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Severity;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders a SARIF 2.1.0 document — the format GitHub code scanning (and other tools) ingest,
 * e.g. uploaded via `github/codeql-action/upload-sarif`.
 *
 * Each step that actually ran becomes one SARIF `run` (`run.tool.driver.name` is the tool),
 * with its findings as the run's `results`. A step that passed cleanly still emits a run with
 * an empty `results` array — so a clean project still produces a non-empty `runs` list, which
 * GitHub's SARIF upload requires (it rejects a document with zero runs). Skipped or
 * not-installed steps produce no run.
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
        $runs = [];
        foreach ($result->steps as $step) {
            if (! $step->status->didRun()) {
                continue;
            }

            $runs[] = [
                'tool' => ['driver' => ['name' => $step->name, 'informationUri' => self::INFORMATION_URI]],
                'results' => array_map($this->result(...), $step->findings),
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
