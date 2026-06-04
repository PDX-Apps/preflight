<?php

declare(strict_types=1);

namespace PdxApps\Preflight;

/**
 * How a run's results are rendered. {@see Auto} adapts to the stream: human-readable on
 * an interactive terminal, agent-friendly (compact, ANSI-free) when piped.
 */
enum OutputFormat: string
{
    case Auto = 'auto';
    case Human = 'human';
    case Json = 'json';
    case Agent = 'agent';
    case Github = 'github';
    case Sarif = 'sarif';
    case Markdown = 'markdown';

    /**
     * Resolve {@see Auto} against the output stream; any explicit format is unchanged.
     */
    public function resolve(bool $isTty): self
    {
        if ($this !== self::Auto) {
            return $this;
        }

        return $isTty ? self::Human : self::Agent;
    }
}
