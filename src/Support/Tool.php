<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Support;

/**
 * Declares the external executable a step needs, and how to locate it.
 *
 * A tool is one of two kinds: a project dependency under `vendor/bin` (the common case) or a
 * system binary resolved on the PATH (`php`, `composer`). The optional require hint powers the
 * "install this" message surfaced by `doctor` and graceful missing-tool skips.
 */
final readonly class Tool
{
    private function __construct(
        public string $binary,
        public bool $inVendorBin,
        public ?string $requireHint,
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
