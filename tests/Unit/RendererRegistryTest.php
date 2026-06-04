<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\OutputFormat;
use PdxApps\Preflight\Render\AgentRenderer;
use PdxApps\Preflight\Render\GithubRenderer;
use PdxApps\Preflight\Render\HumanRenderer;
use PdxApps\Preflight\Render\JsonRenderer;
use PdxApps\Preflight\Render\MarkdownRenderer;
use PdxApps\Preflight\Render\RendererRegistry;
use PdxApps\Preflight\Render\SarifRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RendererRegistry::class)]
final class RendererRegistryTest extends TestCase
{
    public function test_it_resolves_the_human_renderer(): void
    {
        $this->assertInstanceOf(HumanRenderer::class, (new RendererRegistry())->for(OutputFormat::Human));
    }

    public function test_it_resolves_the_json_renderer(): void
    {
        $this->assertInstanceOf(JsonRenderer::class, (new RendererRegistry())->for(OutputFormat::Json));
    }

    public function test_it_resolves_the_agent_renderer(): void
    {
        $this->assertInstanceOf(AgentRenderer::class, (new RendererRegistry())->for(OutputFormat::Agent));
    }

    public function test_it_resolves_the_github_renderer(): void
    {
        $this->assertInstanceOf(GithubRenderer::class, (new RendererRegistry())->for(OutputFormat::Github));
    }

    public function test_auto_is_resolved_against_the_tty_before_lookup(): void
    {
        $registry = new RendererRegistry();

        $this->assertInstanceOf(HumanRenderer::class, $registry->for(OutputFormat::Auto, isTty: true));
        // When piped, Auto resolves to Agent.
        $this->assertInstanceOf(AgentRenderer::class, $registry->for(OutputFormat::Auto, isTty: false));
    }

    public function test_sarif_has_a_dedicated_renderer(): void
    {
        $this->assertInstanceOf(SarifRenderer::class, new RendererRegistry()->for(OutputFormat::Sarif));
    }

    public function test_markdown_has_a_dedicated_renderer(): void
    {
        $this->assertInstanceOf(MarkdownRenderer::class, new RendererRegistry()->for(OutputFormat::Markdown));
    }
}
