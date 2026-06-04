<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Console;

use PdxApps\Preflight\Config\ConfigLoader;
use PdxApps\Preflight\Diagnostics\Diagnostics;
use PdxApps\Preflight\Diagnostics\StepDiagnostic;
use PdxApps\Preflight\Support\ProjectRoot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports the environment: project root, whether a `preflight.php` is present, and per
 * step whether its tool is installed, its config was found, and whether it would run.
 *
 * A read-only report — it always exits 0. Supports `--format=json` so an agent or CI can
 * introspect what would run.
 */
#[AsCommand(name: 'doctor', description: 'Show installed tools, found configs, and what would run.')]
final class DoctorCommand extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: human or json.', 'human');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->projectRoot ?? ProjectRoot::discoverFrom(getcwd() ?: '.');
        $diagnostics = Diagnostics::gather(new ConfigLoader()->load($root), $root);

        if ($input->getOption('format') === 'json') {
            $output->writeln((string) json_encode($diagnostics->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $this->renderHuman($diagnostics, $output);

        return Command::SUCCESS;
    }

    private function renderHuman(Diagnostics $diagnostics, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<options=bold>Preflight</> <fg=gray>%s</>', $diagnostics->projectRoot));
        $output->writeln(sprintf(
            'Config file: %s',
            $diagnostics->hasConfigFile ? '<fg=green>preflight.php</>' : '<fg=gray>none (using defaults)</>',
        ));
        $output->writeln('');

        foreach ($diagnostics->steps as $step) {
            $this->renderStep($step, $output);
        }

        $missing = array_filter($diagnostics->steps, static fn (StepDiagnostic $s): bool => ! $s->toolInstalled);
        if ($missing !== []) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<fg=yellow>%d step(s) missing a tool.</> Run <options=bold>preflight install</> to add them and scaffold config.',
                count($missing),
            ));
        }

        $output->writeln('');
    }

    private function renderStep(StepDiagnostic $step, OutputInterface $output): void
    {
        $badge = $step->willRun ? '<fg=green>✓</>' : '<fg=yellow>○</>';
        $output->writeln(sprintf('%s <options=bold>%s</> <fg=gray>(%s)</>', $badge, $step->label, $step->name));

        if (! $step->toolInstalled) {
            $hint = $step->requireHint !== null
                ? sprintf('not installed — composer require --dev %s', $step->requireHint)
                : 'not installed';
            $output->writeln(sprintf('    tool %s: <fg=yellow>%s</>', (string) $step->tool, $hint));

            return;
        }

        $output->writeln(sprintf('    tool %s: <fg=green>installed</>', (string) $step->tool));

        if ($step->config !== null) {
            $output->writeln($step->configFound
                ? sprintf('    config %s: <fg=green>found</>', $step->config)
                : sprintf('    config %s: <fg=gray>not found (tool default)</>', $step->config));
        }
    }
}
