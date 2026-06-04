<?php

declare(strict_types=1);

namespace PdxApps\Preflight;

/**
 * How a {@see Contracts\Step} consumes the resolved {@see Support\TargetSet}.
 *
 * - Files: the tool accepts individual file paths (pint, phpcs, phpstan, psalm, rector).
 * - Paths: the tool wants directories; a file-level set is widened to containing dirs (phpmd).
 * - Whole: the tool cannot scope to a subset and runs against its own configured scope (tests).
 */
enum Targeting: string
{
    case Files = 'files';
    case Paths = 'paths';
    case Whole = 'whole';

    /**
     * Whether this step can be handed an individual file list.
     */
    public function acceptsFiles(): bool
    {
        return $this === self::Files;
    }

    /**
     * Whether this step can be restricted to a narrowed subset at all.
     */
    public function canScope(): bool
    {
        return $this !== self::Whole;
    }
}
