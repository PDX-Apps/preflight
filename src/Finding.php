<?php

declare(strict_types=1);

namespace PdxApps\Preflight;

/**
 * A single normalized issue produced by a tool, regardless of which tool reported it.
 *
 * This is the common currency every {@see Contracts\OutputParser} produces and every
 * {@see Contracts\Renderer} consumes, so that human, JSON, agent, GitHub and SARIF
 * output all derive from one shape.
 */
final readonly class Finding
{
    public function __construct(
        public string $tool,
        public Severity $severity,
        public string $message,
        public ?string $file = null,
        public ?int $line = null,
        public ?int $column = null,
        public ?string $rule = null,
        public bool $fixable = false,
    ) {
    }

    /**
     * @return array{
     *     tool: string,
     *     severity: string,
     *     message: string,
     *     file: ?string,
     *     line: ?int,
     *     column: ?int,
     *     rule: ?string,
     *     fixable: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'rule' => $this->rule,
            'fixable' => $this->fixable,
        ];
    }
}
