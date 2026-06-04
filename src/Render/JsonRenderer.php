<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Contracts\Renderer;
use PdxApps\Preflight\Result\RunResult;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders a {@see RunResult} as a single pretty-printed JSON document — the machine-
 * readable format for scripts, CI, and AI agents. The output is exactly
 * {@see RunResult::toArray()} encoded, with no banners or extra lines, so it parses as
 * one document.
 */
final class JsonRenderer implements Renderer
{
    public function render(RunResult $result, OutputInterface $output): void
    {
        $output->writeln((string) json_encode(
            $result->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
