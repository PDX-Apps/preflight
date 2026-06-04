<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Console;

use PdxApps\Preflight\Support\ProjectRoot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scaffolds a `preflight.php` config file in the project root from the bundled stub.
 *
 * Refuses to overwrite an existing file unless `--force` is given.
 */
#[AsCommand(name: 'init', description: 'Create a preflight.php config file.')]
final class InitCommand extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing preflight.php.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->projectRoot ?? ProjectRoot::discoverFrom(getcwd() ?: '.');
        $target = $root . '/preflight.php';

        if (is_file($target) && ! $input->getOption('force')) {
            $output->writeln('<fg=yellow>preflight.php already exists.</> Use --force to overwrite.');

            return Command::FAILURE;
        }

        file_put_contents($target, (string) file_get_contents($this->stubPath()));

        $output->writeln('<fg=green>Created preflight.php</> — edit it to customize your pipeline.');

        $this->ignoreCacheFile($root, $output);

        return Command::SUCCESS;
    }

    /**
     * Ensure the freshness cache is gitignored — it's an internal artifact, not source.
     */
    private function ignoreCacheFile(string $root, OutputInterface $output): void
    {
        $entry = '.preflight.cache.json';
        $gitignore = $root . '/.gitignore';
        $existing = is_file($gitignore) ? (string) file_get_contents($gitignore) : '';

        if (str_contains($existing, $entry)) {
            return;
        }

        $prefix = ($existing !== '' && ! str_ends_with($existing, "\n")) ? "\n" : '';
        file_put_contents($gitignore, $existing . $prefix . $entry . "\n");

        $output->writeln("<fg=gray>Added {$entry} to .gitignore.</>");
    }

    private function stubPath(): string
    {
        return dirname(__DIR__, 2) . '/stubs/preflight.php.stub';
    }
}
