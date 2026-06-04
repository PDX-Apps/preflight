<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

/**
 * Declares the external executable a step needs, and how to locate it.
 *
 * A tool is one of three kinds: a project dependency under `vendor/bin` (the common case), a
 * system binary resolved on the PATH (`php`, `composer`), or a Composer plugin invoked as a
 * `composer <subcommand>` whose availability is the presence of its installed package. The
 * optional require hint powers the "install this" message surfaced by `doctor` and graceful
 * missing-tool skips.
 */
final readonly class Tool
{
    private function __construct(
        public string $binary,
        public bool $inVendorBin,
        public ?string $requireHint,
        public ?string $pluginPackage = null,
    ) {
    }

    public static function vendorBin(string $binary, ?string $requireHint = null): self
    {
        return new self($binary, true, $requireHint);
    }

    public static function system(string $binary, ?string $requireHint = null): self
    {
        return new self($binary, false, $requireHint);
    }

    /**
     * A Composer plugin invoked as `composer <subcommand>` (e.g. `composer normalize`). It
     * runs through the `composer` binary, but is only available when its package is installed,
     * so availability checks the package directory rather than a vendor/bin file.
     */
    public static function composerPlugin(string $package): self
    {
        return new self('composer', false, $package, $package);
    }

    /**
     * The executable path used to invoke this tool from the given project root.
     *
     * Vendor-bin tools resolve to `<root>/vendor/bin/<binary>`; system tools resolve to
     * their bare name so the OS PATH applies.
     */
    public function resolvePath(string $projectRoot): string
    {
        if (! $this->inVendorBin) {
            return $this->binary;
        }

        return rtrim($projectRoot, '/') . '/vendor/bin/' . $this->binary;
    }
}
