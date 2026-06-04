<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Console;

use PdxApps\Preflight\Config\ConfigLoader;
use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Install\InstallCatalog;
use PdxApps\Preflight\Install\InstallCaveat;
use PdxApps\Preflight\Install\InstallOptions;
use PdxApps\Preflight\Install\InstallPlan;
use PdxApps\Preflight\Install\InstallPlanner;
use PdxApps\Preflight\Install\Installer;
use PdxApps\Preflight\Runner\SymfonyProcessExecutor;
use PdxApps\Preflight\Steps\StepRegistry;
use PdxApps\Preflight\Support\ProjectRoot;
use PdxApps\Preflight\Support\TargetSet;
use PdxApps\Preflight\Support\Tool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Installs the tools missing for the configured steps and scaffolds their config files.
 *
 * It previews exactly what it will `composer require --dev` and which config files it will
 * create, then acts only after confirmation (or `--yes` for CI/agents). Tools that need an
 * explicit decision — PHPMD's dev branch, or which test runner to add — are prompted for
 * (interactive) or driven by flags (`--with-phpmd`, `--runner=`), never decided silently.
 *
 * Project root and process executor are injectable for testing.
 */
#[AsCommand(name: 'install', description: 'Install missing tools for your steps and scaffold their config.')]
final class InstallCommand extends Command
{
    public function __construct(
        private readonly ?string $projectRoot = null,
        private readonly ?ProcessExecutor $executor = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('runner', null, InputOption::VALUE_REQUIRED, 'Test runner to install when none exists: phpunit, pest, or none.')
            ->addOption('with-phpmd', null, InputOption::VALUE_NONE, 'Include PHPMD (its 3.x dev branch; sets minimum-stability dev).')
            ->addOption('no-configs', null, InputOption::VALUE_NONE, 'Do not scaffold config files.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing config files when scaffolding.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be installed without doing it.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Proceed without the confirmation prompt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->projectRoot ?? ProjectRoot::discoverFrom(getcwd() ?: '.');
        $executor = $this->executor ?? new SymfonyProcessExecutor();
        $context = new Context($root, TargetSet::wholeProject());

        $configuration = new ConfigLoader()->load($root);
        $steps = $configuration->resolveSteps(array_map(static fn (string $c): Step => $c::make(), StepRegistry::defaults()));
        $stepsByName = $this->index($steps);

        $options = $this->gatherOptions($input, $output, $context, $stepsByName);
        $plan = new InstallPlanner($context)->plan($steps, $options);

        $this->preview($plan, $output);

        if ($plan->isEmpty()) {
            $output->writeln('<fg=green>Everything for your steps is already installed.</>');

            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $output->writeln('<fg=gray>Dry run — nothing was changed.</>');

            return Command::SUCCESS;
        }

        if (! $this->confirmed($input, $output)) {
            $output->writeln('Aborted.');

            return Command::SUCCESS;
        }

        $outcome = new Installer($root, $executor)->apply(
            $plan,
            $stepsByName,
            writeConfigs: ! $input->getOption('no-configs'),
            force: (bool) $input->getOption('force'),
        );

        foreach ($outcome->messages as $message) {
            $output->writeln($message);
        }

        return $outcome->success ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param  array<string, Step>  $stepsByName
     */
    private function gatherOptions(InputInterface $input, OutputInterface $output, Context $context, array $stepsByName): InstallOptions
    {
        $runner = 'phpunit';
        $testStep = $stepsByName['test'] ?? null;
        if ($testStep instanceof Step && ! $this->installed($testStep, $context)) {
            $runner = $this->resolveRunner($input, $output);
        }

        $approved = [];
        $phpmd = $stepsByName['phpmd'] ?? null;
        if ($phpmd instanceof Step && ! $this->installed($phpmd, $context) && $this->approvePhpmd($input, $output)) {
            $approved[] = 'phpmd';
        }

        return new InstallOptions(
            runner: $runner === 'none' ? null : $runner,
            approvedCaveats: $approved,
            writeConfigs: ! $input->getOption('no-configs'),
        );
    }

    private function resolveRunner(InputInterface $input, OutputInterface $output): string
    {
        $flag = $input->getOption('runner');
        if (is_string($flag) && $flag !== '') {
            return $flag;
        }

        if ($input->isInteractive()) {
            $question = new ChoiceQuestion('Which test runner should I install?', ['phpunit', 'pest', 'none'], 'phpunit');

            return (string) $this->questionHelper()->ask($input, $output, $question);
        }

        return 'phpunit';
    }

    private function approvePhpmd(InputInterface $input, OutputInterface $output): bool
    {
        if ($input->getOption('with-phpmd')) {
            return true;
        }

        $recipe = InstallCatalog::recipeFor('phpmd');
        if (! $input->isInteractive() || ! $recipe?->caveat instanceof InstallCaveat) {
            return false;
        }

        $output->writeln('');
        $output->writeln('<fg=yellow>PHPMD</> — ' . $recipe->caveat->note);
        $output->writeln('  ' . $recipe->caveat->link);

        return (bool) $this->questionHelper()->ask(
            $input,
            $output,
            new ConfirmationQuestion('Install PHPMD (dev branch, sets minimum-stability dev)? [y/N] ', false),
        );
    }

    private function confirmed(InputInterface $input, OutputInterface $output): bool
    {
        if ($input->getOption('yes')) {
            return true;
        }

        if (! $input->isInteractive()) {
            $output->writeln('<fg=yellow>Pass --yes to apply, or --dry-run to preview only.</>');

            return false;
        }

        return (bool) $this->questionHelper()->ask(
            $input,
            $output,
            new ConfirmationQuestion('Proceed? [y/N] ', false),
        );
    }

    private function preview(InstallPlan $plan, OutputInterface $output): void
    {
        $output->writeln('');
        foreach ($plan->installs as $install) {
            $output->writeln(sprintf('  <fg=green>+</> %s', $install->recipe->requirement()));
        }

        foreach ($plan->skipped as $skip) {
            $output->writeln(sprintf('  <fg=gray>·</> %s — %s</>', $skip->package, $skip->reason));
        }

        $output->writeln('');
    }

    /**
     * @param  list<Step>  $steps
     * @return array<string, Step>
     */
    private function index(array $steps): array
    {
        $byName = [];
        foreach ($steps as $step) {
            $byName[$step->name()] = $step;
        }

        return $byName;
    }

    private function installed(Step $step, Context $context): bool
    {
        $tool = $step->tool();

        return ! $tool instanceof Tool || $context->toolAvailable($tool);
    }

    private function questionHelper(): QuestionHelper
    {
        $helper = $this->getHelper('question');
        if (! $helper instanceof QuestionHelper) {
            throw new \RuntimeException('The question helper is unavailable.');
        }

        return $helper;
    }
}
