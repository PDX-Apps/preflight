<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Console;

use PdxApps\Preflight\Config\ConfigLoader;
use PdxApps\Preflight\Diagnostics\Diagnostics;
use PdxApps\Preflight\Support\ProjectRoot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists the steps in the pipeline and whether each would run — a terse counterpart to
 * `doctor`, handy for discovering names to pass to `--only` / `--skip` (when those land).
 *
 * Named `steps` to avoid clashing with Symfony Console's built-in `list` command.
 */
#[AsCommand(name: 'steps', description: 'List the configured steps and their availability.')]
final class ListStepsCommand extends Command
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
            $steps = array_map(static fn ($s): array => $s->toArray(), $diagnostics->steps);
            $output->writeln((string) json_encode($steps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        foreach ($diagnostics->steps as $step) {
            $badge = $step->willRun ? '<fg=green>✓</>' : '<fg=yellow>○</>';
            $state = $step->willRun ? '' : ' <fg=yellow>(tool not installed)</>';
            $output->writeln(sprintf('%s <options=bold>%s</> <fg=gray>%s</>%s', $badge, $step->name, $step->label, $state));
        }

        return Command::SUCCESS;
    }
}
