<?php

declare(strict_types=1);

namespace PdxApps\Preflight;

/**
 * The severity of a single {@see Finding}, ordered Error > Warning > Info.
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    /**
     * Relative rank, highest first. Used to sort findings most-severe-first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Error => 30,
            self::Warning => 20,
            self::Info => 10,
        };
    }

    /**
     * Whether a finding of this severity should, on its own, fail a run.
     */
    public function isFailure(): bool
    {
        return $this === self::Error;
    }
}
