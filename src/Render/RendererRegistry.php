<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Render;

use PdxApps\Preflight\Contracts\Renderer;
use PdxApps\Preflight\OutputFormat;

/**
 * Maps an {@see OutputFormat} to the {@see Renderer} that produces it.
 *
 * Adding a format means registering a renderer here — the engine and CLI never change.
 * Any format still without a dedicated renderer falls back to JSON, which is always valid
 * for a machine consumer. {@see OutputFormat::Auto} is resolved against the TTY first.
 */
final class RendererRegistry
{
    /** @var array<string, Renderer> */
    private array $renderers;

    public function __construct()
    {
        $this->renderers = [
            OutputFormat::Human->value => new HumanRenderer(),
            OutputFormat::Json->value => new JsonRenderer(),
            OutputFormat::Agent->value => new AgentRenderer(),
            OutputFormat::Github->value => new GithubRenderer(),
            OutputFormat::Sarif->value => new SarifRenderer(),
        ];
    }

    public function for(OutputFormat $format, bool $isTty = true): Renderer
    {
        $resolved = $format->resolve($isTty);

        return $this->renderers[$resolved->value] ?? $this->renderers[OutputFormat::Json->value];
    }
}
