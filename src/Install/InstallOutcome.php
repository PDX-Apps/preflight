<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * The result of {@see Installer::apply()}: whether it succeeded and the progress lines to
 * show. Keeping output as returned data (rather than writing directly) keeps the installer
 * free of console types and easy to test.
 */
final readonly class InstallOutcome
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public bool $success,
        public array $messages,
    ) {
    }
}
