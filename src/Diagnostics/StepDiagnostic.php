<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Diagnostics;

/**
 * The diagnostic view of one step: whether its tool is installed, whether its config file
 * was found, and whether it would run. Produced by {@see Diagnostics::gather()} and shown
 * by the `doctor` command.
 */
final readonly class StepDiagnostic
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $tool,
        public bool $toolInstalled,
        public ?string $requireHint,
        public ?string $config,
        public bool $configFound,
        public bool $willRun,
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     label: string,
     *     tool: ?string,
     *     toolInstalled: bool,
     *     requireHint: ?string,
     *     config: ?string,
     *     configFound: bool,
     *     willRun: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'tool' => $this->tool,
            'toolInstalled' => $this->toolInstalled,
            'requireHint' => $this->requireHint,
            'config' => $this->config,
            'configFound' => $this->configFound,
            'willRun' => $this->willRun,
        ];
    }
}
