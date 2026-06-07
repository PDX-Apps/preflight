<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

/**
 * A PHP code-coverage driver the test runner can use. Coverage needs one of these active;
 * without it PHPUnit/Pest cannot measure coverage at all.
 *
 * {@see detect()} reports which driver the current PHP process has available, preferring the
 * fastest (PCOV) and falling back to phpdbg, then Xdebug. Detection runs at the composition
 * root and is handed to steps via {@see \PdxApps\Preflight\Context::coverageDriver()}, so
 * steps stay pure and testable.
 */
enum CoverageDriver: string
{
    case Pcov = 'pcov';
    case Phpdbg = 'phpdbg';
    case Xdebug = 'xdebug';

    /**
     * The driver available to the current PHP process, or null if none — in which case
     * coverage cannot run. PCOV is preferred (fastest), then phpdbg, then Xdebug.
     */
    public static function detect(): ?self
    {
        if (\extension_loaded('pcov')) {
            return self::Pcov;
        }

        if (\PHP_SAPI === 'phpdbg') {
            return self::Phpdbg;
        }

        if (\extension_loaded('xdebug')) {
            return self::Xdebug;
        }

        return null;
    }

    /**
     * Extra environment for the test process. Xdebug only collects coverage when its mode
     * includes `coverage`, so set it — letting coverage work even if the user never
     * configured `xdebug.mode`. The other drivers need nothing.
     *
     * @return array<string, string>
     */
    public function env(): array
    {
        return $this === self::Xdebug ? ['XDEBUG_MODE' => 'coverage'] : [];
    }

    /**
     * Human label for diagnostics (e.g. `doctor`).
     */
    public function label(): string
    {
        return match ($this) {
            self::Pcov => 'PCOV',
            self::Phpdbg => 'phpdbg',
            self::Xdebug => 'Xdebug',
        };
    }
}
