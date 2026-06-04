<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Contracts\Renderer;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\RunResult;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders a {@see RunResult} for an AI agent: compact, ANSI-free, and built around the
 * one thing an agent acts on — the list of problems to fix.
 *
 * Every finding is a single `file:line:col [tool] message (rule)` line (severity-sorted,
 * most-severe first) so an agent can read or grep them directly. Files a fixer rewrote are
 * listed as `fixed <file>`. A clean run is a single PASS line. Output is always plain text,
 * even when the stream is decorated, so escape codes never leak into an agent's context.
 */
final class AgentRenderer implements Renderer
{
    public function render(RunResult $result, OutputInterface $output): void
    {
        $lines = [];

        foreach ($result->findings() as $finding) {
            $lines[] = $this->finding($finding);
        }

        foreach ($result->steps as $step) {
            foreach ($step->changed as $file) {
                $lines[] = 'fixed ' . $file;
            }
        }

        if ($result->isSuccess() && $lines === []) {
            $lines[] = 'PASS — all checks passed.';
        } elseif (! $result->isSuccess()) {
            array_unshift($lines, sprintf('FAIL — %d finding(s).', count($result->findings())));
        }

        // With -v, also enumerate every step's outcome (default stays errors-only).
        if ($output->isVerbose()) {
            foreach ($result->steps as $step) {
                $lines[] = sprintf('[%s] %s', $step->status->value, $step->name);
            }
        }

        // writeln with OUTPUT_RAW so Symfony does not interpret <tags>, and the renderer
        // emits no escape codes of its own.
        $output->writeln($lines, OutputInterface::OUTPUT_RAW);
    }

    private function finding(Finding $finding): string
    {
        $location = $finding->file ?? '-';
        if ($finding->file !== null && $finding->line !== null) {
            $location .= ':' . $finding->line;
            if ($finding->column !== null) {
                $location .= ':' . $finding->column;
            }
        }

        $rule = $finding->rule !== null ? sprintf(' (%s)', $finding->rule) : '';

        return sprintf('%s [%s] %s%s', $location, $finding->tool, $finding->message, $rule);
    }
}
