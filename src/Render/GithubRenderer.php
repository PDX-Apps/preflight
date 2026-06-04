<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Contracts\Renderer;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Severity;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders findings as GitHub Actions workflow commands, so each one appears as an inline
 * annotation on the pull-request diff:
 *
 *   ::error file=app/Foo.php,line=12,col=5::[phpstan] Undefined variable $x (variable.undefined)
 *
 * Errors use `::error`, everything else `::warning`. Message text and property values are
 * escaped per GitHub's workflow-command spec. A clean run emits nothing (so no annotations).
 */
final class GithubRenderer implements Renderer
{
    public function render(RunResult $result, OutputInterface $output): void
    {
        foreach ($result->findings() as $finding) {
            $output->writeln($this->command($finding), OutputInterface::OUTPUT_RAW);
        }
    }

    private function command(Finding $finding): string
    {
        $type = $finding->severity === Severity::Error ? 'error' : 'warning';
        $properties = $this->properties($finding);

        return sprintf(
            '::%s%s::%s',
            $type,
            $properties === '' ? '' : ' ' . $properties,
            $this->escapeData($this->text($finding)),
        );
    }

    private function properties(Finding $finding): string
    {
        $parts = [];
        if ($finding->file !== null) {
            $parts[] = 'file=' . $this->escapeProperty($finding->file);
        }
        if ($finding->line !== null) {
            $parts[] = 'line=' . $finding->line;
        }
        if ($finding->column !== null) {
            $parts[] = 'col=' . $finding->column;
        }

        return implode(',', $parts);
    }

    private function text(Finding $finding): string
    {
        $rule = $finding->rule !== null ? sprintf(' (%s)', $finding->rule) : '';

        return sprintf('[%s] %s%s', $finding->tool, $finding->message, $rule);
    }

    /**
     * Escape a workflow-command message (the part after `::`).
     */
    private function escapeData(string $value): string
    {
        return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
    }

    /**
     * Escape a workflow-command property value (additionally escapes `:` and `,`).
     */
    private function escapeProperty(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n", ':', ','],
            ['%25', '%0D', '%0A', '%3A', '%2C'],
            $value,
        );
    }
}
