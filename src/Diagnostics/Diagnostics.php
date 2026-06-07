<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Diagnostics;

use PdxApps\Preflight\Config\Configuration;
use PdxApps\Preflight\Config\ConfigLoader;
use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Steps\StepRegistry;
use PdxApps\Preflight\Support\CoverageDriver;
use PdxApps\Preflight\Support\TargetSet;

/**
 * Everything the `doctor` command reports: the project root, whether a `preflight.php`
 * exists, and a per-step view of tool/config availability and whether the step would run.
 *
 * Pure data gathered from the {@see Configuration} and the project on disk — no processes
 * run — so both the human and JSON renderers (and tests) consume the same snapshot.
 */
final readonly class Diagnostics
{
    /**
     * @param  list<StepDiagnostic>  $steps
     */
    public function __construct(
        public string $projectRoot,
        public bool $hasConfigFile,
        public array $steps,
        public ?CoverageDriver $coverageDriver = null,
    ) {
    }

    public static function gather(Configuration $configuration, string $projectRoot): self
    {
        $driver = CoverageDriver::detect();
        $context = new Context($projectRoot, TargetSet::wholeProject(), $driver);

        // Diagnose against every default step (instantiated), so steps whose tool is
        // missing are still reported — that is precisely what doctor exists to show.
        $defaults = array_map(static fn (string $class): Step => $class::make(), StepRegistry::defaults());
        $steps = $configuration->resolveSteps($defaults);

        return new self(
            projectRoot: $projectRoot,
            hasConfigFile: new ConfigLoader()->exists($projectRoot),
            steps: array_map(static fn (Step $step): StepDiagnostic => self::diagnose($step, $context), $steps),
            coverageDriver: $driver,
        );
    }

    private static function diagnose(Step $step, Context $context): StepDiagnostic
    {
        $tool = $step->tool();
        $toolInstalled = !$tool instanceof \PdxApps\Preflight\Support\Tool || $context->toolAvailable($tool);

        $config = $step->defaultConfig();
        $configFound = $config !== null && $context->configExists($config);

        return new StepDiagnostic(
            name: $step->name(),
            label: $step->label(),
            tool: $tool?->binary,
            toolInstalled: $toolInstalled,
            requireHint: $tool?->requireHint,
            config: $config,
            configFound: $configFound,
            willRun: $toolInstalled,
        );
    }

    /**
     * @return array{projectRoot: string, hasConfigFile: bool, coverageDriver: ?string, steps: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'projectRoot' => $this->projectRoot,
            'hasConfigFile' => $this->hasConfigFile,
            'coverageDriver' => $this->coverageDriver?->value,
            'steps' => array_map(static fn (StepDiagnostic $s): array => $s->toArray(), $this->steps),
        ];
    }
}
