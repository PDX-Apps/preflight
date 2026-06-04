<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Contracts;

use PdxApps\Preflight\Result\RunResult;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders a {@see RunResult} to an output stream in a particular format.
 *
 * Renderers are registered by name (human, json, agent, github, sarif); adding a new
 * output format means adding a Renderer, never touching the engine.
 */
interface Renderer
{
    public function render(RunResult $result, OutputInterface $output): void;
}
