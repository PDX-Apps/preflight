<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps\Concerns;

/**
 * Derives a stable, kebab-case step name from the class basename so step authors never
 * hand-write one. A step may still override {@see name()} when the desired id differs
 * from the class (e.g. class `Tests` → name `test`).
 *
 * Examples: `Pint` → `pint`, `ComposerAudit` → `composer-audit`, `HTMLValidator` → `html-validator`.
 */
trait DerivesName
{
    public function name(): string
    {
        $basename = static::class;
        if (($pos = strrpos($basename, '\\')) !== false) {
            $basename = substr($basename, $pos + 1);
        }

        // Insert a boundary between a run of capitals and a following Capital+lower
        // (HTMLValidator → HTML-Validator), and between lower/digit and a capital
        // (composerAudit → composer-Audit).
        $kebab = preg_replace(
            ['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'],
            ['$1-$2', '$1-$2'],
            $basename,
        );

        return strtolower((string) $kebab);
    }
}
