<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Process;

use PdxApps\Preflight\Contracts\OutputParser;
use PdxApps\Preflight\Contracts\Runner;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Finding;
use PdxApps\Preflight\Parsing\ExitCodeParser;

/**
 * The data a step returns from {@see Step::plan()}: the
 * command to run, optional pre-commands, the parser for its output, and execution hints.
 *
 * A plan describes work but never performs it — the {@see Runner}
 * executes it. Build via the {@see command()} / {@see exitCode()} factories; the `with*`
 * methods return modified copies so a plan is safe to share and tweak.
 */
final readonly class StepPlan
{
    /**
     * Placeholder a command can include (e.g. `--log-junit={REPORT_FILE}`). When the plan
     * {@see readingReportFile()}, the runner replaces this with a real temp-file path,
     * executes, then reads that file into the result's stdout for the parser, and deletes it.
     */
    public const string REPORT_FILE = '{REPORT_FILE}';

    /**
     * @param  list<string>  $command  Argv for the main command.
     * @param  list<list<string>>  $before  Argv commands run before the main one, in order.
     * @param  array<string, string>  $env  Extra environment variables.
     * @param  list<Finding>  $notes  Advisory findings the runner appends to the result.
     */
    private function __construct(
        public array $command,
        public OutputParser $parser,
        public array $before = [],
        public array $env = [],
        public bool $filtersDeprecations = false,
        public bool $judgesByFindings = false,
        public bool $readsReportFile = false,
        public array $notes = [],
    ) {
    }

    /**
     * A plan whose output is interpreted by a parser. Defaults to {@see ExitCodeParser}
     * (pass/fail) until {@see parseWith()} supplies a tool-specific one.
     *
     * @param  list<string>  $command
     */
    public static function command(string $tool, array $command): self
    {
        return new self($command, new ExitCodeParser($tool));
    }

    /**
     * A plan with no machine-readable report: success/failure is the exit code alone.
     *
     * @param  list<string>  $command
     */
    public static function exitCode(string $tool, array $command): self
    {
        return new self($command, new ExitCodeParser($tool));
    }

    public function parseWith(OutputParser $parser): self
    {
        return $this->with(parser: $parser);
    }

    /**
     * Append a command to run before the main one. Calls accumulate in order.
     *
     * @param  list<string>  $command
     */
    public function before(array $command): self
    {
        return $this->with(before: [...$this->before, $command]);
    }

    /**
     * @param  array<string, string>  $env
     */
    public function withEnv(array $env): self
    {
        return $this->with(env: $env);
    }

    /**
     * Strip PHP deprecation lines from the tool's output before parsing/display
     * (PHPMD emits these to the same stream as its report).
     */
    public function filteringDeprecations(): self
    {
        return $this->with(filtersDeprecations: true);
    }

    /**
     * Judge pass/fail by whether the parser found anything, not the exit code. For tools
     * whose exit code is unreliable — e.g. PHPMD exits non-zero merely because it emitted
     * PHP deprecation notices, even when it found no violations.
     */
    public function judgeByFindings(): self
    {
        return $this->with(judgesByFindings: true);
    }

    /**
     * The command writes a report to {@see REPORT_FILE}; the runner substitutes a temp path,
     * runs, reads the file into the result's stdout for the parser, then deletes it.
     */
    public function readingReportFile(): self
    {
        return $this->with(readsReportFile: true);
    }

    /**
     * Attach an advisory finding the runner appends to this step's result, regardless of
     * pass/fail. Unlike parser findings it never decides the outcome (a Warning/Info note
     * leaves a passing step passing) — use it to surface things like "coverage was requested
     * but no driver is active". Calls accumulate.
     */
    public function note(Finding $finding): self
    {
        return $this->with(notes: [...$this->notes, $finding]);
    }

    /**
     * @param  list<list<string>>|null  $before
     * @param  array<string, string>|null  $env
     * @param  list<Finding>|null  $notes
     */
    private function with(
        ?OutputParser $parser = null,
        ?array $before = null,
        ?array $env = null,
        ?bool $filtersDeprecations = null,
        ?bool $judgesByFindings = null,
        ?bool $readsReportFile = null,
        ?array $notes = null,
    ): self {
        return new self(
            command: $this->command,
            parser: $parser ?? $this->parser,
            before: $before ?? $this->before,
            env: $env ?? $this->env,
            filtersDeprecations: $filtersDeprecations ?? $this->filtersDeprecations,
            judgesByFindings: $judgesByFindings ?? $this->judgesByFindings,
            readsReportFile: $readsReportFile ?? $this->readsReportFile,
            notes: $notes ?? $this->notes,
        );
    }
}
