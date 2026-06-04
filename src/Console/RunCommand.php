<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Console;

use PdxApps\Preflight\Config\ConfigLoader;
use PdxApps\Preflight\Config\Configuration;
use PdxApps\Preflight\Contracts\Clock;
use PdxApps\Preflight\Contracts\ProcessExecutor;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\OutputFormat;
use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Render\RendererRegistry;
use PdxApps\Preflight\Report\FreshnessCache;
use PdxApps\Preflight\Report\ReportInclude;
use PdxApps\Preflight\Report\RunReport;
use PdxApps\Preflight\Result\RunResult;
use PdxApps\Preflight\Runner\SymfonyProcessExecutor;
use PdxApps\Preflight\Scope\ScopeRequest;
use PdxApps\Preflight\Scope\ScopeResolver;
use PdxApps\Preflight\Support\GitFiles;
use PdxApps\Preflight\Support\InputHasher;
use PdxApps\Preflight\Support\ProjectRoot;
use PdxApps\Preflight\Support\SystemClock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The default command: load the project's config, run the resolved steps over the
 * requested scope, render the result, and exit 0/1 on pass/fail.
 *
 * Project root and process executor are injectable for testing; in normal use the root is
 * discovered from the working directory and processes run for real.
 */
#[AsCommand(name: 'run', description: 'Run code-quality checks (or fixes with --fix).')]
final class RunCommand extends Command
{
    public function __construct(
        private readonly ?string $projectRoot = null,
        private readonly ?ProcessExecutor $executor = null,
        private readonly ?Clock $clock = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Limit the run to these files or directories.')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Apply fixes instead of only checking.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Force check-only (overrides a fix-by-default config).')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: auto, human, json, agent, github, sarif, markdown.')
            ->addOption('fail-fast', null, InputOption::VALUE_NONE, 'Stop at the first failing step.')
            ->addOption('files', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of files to check.')
            ->addOption('dirty', null, InputOption::VALUE_NONE, 'Only check files changed in the working tree.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Check the whole project (overrides a dirty-by-default config).')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only check files changed since a git ref (e.g. main).')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, "Only check a module's app/tests directories (e.g. --module=Billing).")
            ->addOption('skip-if-fresh', null, InputOption::VALUE_NONE, 'Skip the run if inputs are unchanged since the last passing run.')
            ->addOption('report', null, InputOption::VALUE_REQUIRED, 'Write a run report to this file (in addition to console output).')
            ->addOption('report-format', null, InputOption::VALUE_REQUIRED, 'Report file format: json (default).')
            ->addOption(
                'report-include',
                null,
                InputOption::VALUE_REQUIRED,
                'Report sections (default findings,steps): findings,steps,passing,output,all.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->projectRoot ?? ProjectRoot::discoverFrom(getcwd() ?: '.');
        $executor = $this->executor ?? new SymfonyProcessExecutor();

        $configuration = new ConfigLoader()->load($root);
        if ($input->getOption('fail-fast')) {
            $configuration = $configuration->withFailFast(true);
        }

        $request = $this->scopeRequest($input, $configuration);
        $targets = new ScopeResolver(new GitFiles($executor))->resolve($request, $root, $configuration->modules);

        $cache = new FreshnessCache($root, $this->clock ?? new SystemClock());
        $hash = new InputHasher($root)->hash($targets->files(), $this->configFiles($root));

        if ($input->getOption('skip-if-fresh') && $cache->isFresh($hash)) {
            $output->writeln('Preflight: inputs unchanged since the last passing run — skipped (fresh).');

            return Command::SUCCESS;
        }

        $mode = $this->resolveMode($input, $configuration);
        $result = Preflight::make($configuration, projectRoot: $root, executor: $executor)->run($mode, $targets);

        $format = $this->resolveFormat($input, $configuration->defaultFormat);
        new RendererRegistry()->for($format, isTty: $output->isDecorated())->render($result, $output);

        $cache->store($hash, $result->isSuccess());
        $this->writeReport($input, $result, $mode);

        return $result->isSuccess() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * The config/lock files folded into the input hash, so a ruleset or tool-version change
     * busts the freshness cache. Only those that exist are included.
     *
     * @return list<string>
     */
    private function configFiles(string $root): array
    {
        $candidates = [
            'composer.lock',
            'pint.json',
            'phpcs.xml', 'phpcs.xml.dist',
            'phpstan.neon', 'phpstan.neon.dist',
            'psalm.xml', 'psalm.xml.dist',
            'phpmd.xml',
            'rector.php',
            'phpunit.xml', 'phpunit.xml.dist',
            'preflight.php',
        ];

        return array_values(array_filter(
            $candidates,
            static fn (string $file): bool => is_file(rtrim($root, '/') . '/' . $file),
        ));
    }

    /**
     * Write the run report artifact to `--report` (in addition to console output), if asked.
     */
    private function writeReport(InputInterface $input, RunResult $result, Mode $mode): void
    {
        $path = $input->getOption('report');
        if (! is_string($path) || $path === '') {
            return;
        }

        $report = new RunReport(
            result: $result,
            ranAt: ($this->clock ?? new SystemClock())->now(),
            version: $this->getApplication()?->getVersion() ?? 'dev',
            mode: $mode,
            include: $this->reportInclude($input),
        );

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($path, $report->toJson() . PHP_EOL);
    }

    /**
     * @return list<ReportInclude>
     */
    private function reportInclude(InputInterface $input): array
    {
        $value = $input->getOption('report-include');

        return is_string($value) && $value !== ''
            ? ReportInclude::parse($value)
            : [ReportInclude::Findings, ReportInclude::Steps];
    }

    /**
     * Check mode by default; Fix when fix-by-default config is on or `--fix` is given.
     * `--check` always wins (forces check-only over a fix-by-default config).
     */
    private function resolveMode(InputInterface $input, Configuration $configuration): Mode
    {
        if ($input->getOption('check')) {
            return Mode::Check;
        }

        return $input->getOption('fix') || $configuration->fixByDefault ? Mode::Fix : Mode::Check;
    }

    private function scopeRequest(InputInterface $input, Configuration $configuration): ScopeRequest
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');

        $filesOption = $input->getOption('files');
        $files = is_string($filesOption) ? array_values(array_filter(array_map(trim(...), explode(',', $filesOption)))) : [];

        $since = $input->getOption('since');
        $module = $input->getOption('module');

        return new ScopeRequest(
            files: $files,
            paths: $paths,
            dirty: $this->resolveDirty($input, $configuration, $files, $paths, $since, $module),
            since: is_string($since) ? $since : null,
            module: is_string($module) ? $module : null,
        );
    }

    /**
     * Whether to scope to working-tree changes: explicit `--dirty`, or a dirty-by-default
     * config when the run is neither already scoped explicitly nor widened with `--all`.
     *
     * @param  list<string>  $files
     * @param  list<string>  $paths
     */
    private function resolveDirty(InputInterface $input, Configuration $configuration, array $files, array $paths, mixed $since, mixed $module): bool
    {
        if ($input->getOption('dirty')) {
            return true;
        }

        $explicitScope = $files !== [] || $paths !== [] || is_string($since) || is_string($module);

        return ! $explicitScope && ! $input->getOption('all') && $configuration->dirtyByDefault;
    }

    private function resolveFormat(InputInterface $input, OutputFormat $default): OutputFormat
    {
        $value = $input->getOption('format');

        return is_string($value) ? OutputFormat::from($value) : $default;
    }
}
