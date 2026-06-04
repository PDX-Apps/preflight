<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\Step;

/**
 * Knows the built-in steps and which of them are usable in a given project.
 *
 * Used for zero-config runs: when a {@see \PdxApps\Preflight\Config\Configuration} lists
 * no explicit steps, the engine runs {@see installed()} — every default step whose tool is
 * present in the project — in canonical (fast-to-slow) order. As more built-ins land they
 * are added to {@see defaults()}.
 */
final class StepRegistry
{
    /**
     * The built-in step classes, in canonical run order (fast to slow).
     *
     * @return list<class-string<AbstractStep>>
     */
    public static function defaults(): array
    {
        return [
            Pint::class,
            Phpcs::class,
            Phpstan::class,
            Rector::class,
            Psalm::class,
            Phpmd::class,
            ComposerAudit::class,
            Tests::class,
        ];
    }

    /**
     * The default steps whose tool is available in the given project, as ready instances.
     *
     * @return list<Step>
     */
    public function installed(Context $context): array
    {
        $steps = [];
        foreach (self::defaults() as $class) {
            $step = $class::make();
            $tool = $step->tool();
            if ($tool === null || $context->toolAvailable($tool)) {
                $steps[] = $step;
            }
        }

        return $steps;
    }
}
