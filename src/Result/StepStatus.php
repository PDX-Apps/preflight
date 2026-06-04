<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Result;

/**
 * The terminal state of a single step within a run.
 */
enum StepStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case MissingTool = 'missing-tool';

    /**
     * Whether this status should fail the overall run. Only an actual Failed does.
     */
    public function isFailure(): bool
    {
        return $this === self::Failed;
    }

    /**
     * Whether the underlying tool actually executed (vs. being skipped or absent).
     */
    public function didRun(): bool
    {
        return $this === self::Passed || $this === self::Failed;
    }
}
