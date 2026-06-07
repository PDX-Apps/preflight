# Preflight

[![CI](https://github.com/PDX-Apps/preflight/actions/workflows/ci.yml/badge.svg)](https://github.com/PDX-Apps/preflight/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/PDX-Apps/preflight/graph/badge.svg)](https://codecov.io/gh/PDX-Apps/preflight)
[![Packagist Version](https://img.shields.io/packagist/v/pdxapps/preflight)](https://packagist.org/packages/pdxapps/preflight)
[![Packagist Downloads](https://img.shields.io/packagist/dt/pdxapps/preflight)](https://packagist.org/packages/pdxapps/preflight)
[![PHP Version](https://img.shields.io/packagist/php-v/pdxapps/preflight?logo=php&logoColor=white)](composer.json)
[![License: MIT](https://img.shields.io/github/license/PDX-Apps/preflight)](LICENSE)

A framework-agnostic, AI/CI-native runner for PHP code-quality tools. One command runs
your formatter, linters, static analysis, refactoring checks, and tests — in **check** or
**fix** mode — and reports the results for humans, CI, or AI agents.

```
 PASS   Pint (0.57s)
 PASS   PHPCS (0.78s)
 PASS   PHPStan (1.40s)
 PASS   Rector (0.71s)
 PASS   Psalm (2.77s)
 PASS   PHPMD (0.51s)
 PASS   Composer Audit (1.14s)
 PASS   Tests (1.44s)

8 passed, 0 failed, 0 skipped  (9.32s)
✓ All checks passed.
```

## Why

Every PHP project ends up with a pile of quality tools, each with its own CLI, output
format, and exit-code quirks. Preflight gives them **one interface**: a single command, a
unified result schema, consistent exit codes, and output formats tuned for who's reading —
a human at a terminal, a CI annotation on a PR, or an AI agent fixing its own work.

## Documentation

This README is the overview. For depth, see the guides in [`docs/`](docs/):

- **[Installing tools (`preflight install`)](docs/install.md)** — set up tools and scaffold
  config in one confirm-first command.
- **[Steps reference](docs/steps.md)** — every built-in step, its options, and how scope
  flags affect it.
- **[Configuration](docs/configuration.md)** — `preflight.php`, precedence, run defaults, the
  agent preset, modules, custom steps, and the programmatic API.

## Requirements

- PHP 8.4+
- The tools you want to run, installed in your project (`composer require --dev`). Preflight
  runs whatever it finds and skips the rest.

## Install

```bash
composer require --dev pdxapps/preflight
```

## Setup

Already have your tools installed? Skip this — Preflight auto-detects them. Starting fresh,
`preflight install` adds the missing ones and scaffolds their config:

```bash
vendor/bin/preflight install            # interactive: previews, then asks before changing anything
vendor/bin/preflight install --dry-run  # just show what it would do
vendor/bin/preflight install --yes      # non-interactive (CI / agents)
```

It previews the exact `composer require --dev …` and the config files it will create, then
acts only on confirmation. It never mutates silently: tools needing an explicit decision are
surfaced, not assumed —

- **PHPMD** has no stable 3.x yet (and 2.x can't parse PHP 8.4), so it's **opt-in**: pass
  `--with-phpmd` (or accept the prompt) to install the dev branch, which also sets
  `minimum-stability: dev` in your `composer.json`.
- **Test runner**: choose with `--runner=phpunit|pest|none` (or at the prompt).
- Other flags: `--no-configs` (don't scaffold), `--force` (overwrite existing configs).

Scaffolded configs point at your real source dirs (detected from `app`/`src`/`tests`); Psalm
is set up via its own `psalm --init`. `preflight doctor` tells you what's still missing.

## Usage

```bash
vendor/bin/preflight              # run all installed checks
vendor/bin/preflight --fix        # apply fixes where the tool supports it
vendor/bin/preflight app src      # only these paths
vendor/bin/preflight --dirty      # only files changed in your working tree
vendor/bin/preflight doctor       # what's installed and what would run
vendor/bin/preflight init         # scaffold a preflight.php config
```

It works **zero-config**: with no `preflight.php`, Preflight runs the [default
steps](#default-steps) — auto-detecting which apply from the tools you have installed — using
your existing root config (`phpstan.neon`, `pint.json`, `phpcs.xml`, …), the standard
locations the tools already use.

### Commands

| Command | Description |
|---|---|
| `run` (default) | Run the checks (or fixes with `--fix`). |
| `install` | Install missing tools for your steps and scaffold their config (see [Setup](#setup)). |
| `doctor` | Show project root, config, and per-tool installed / config-found / would-run. |
| `steps` | List the configured steps and whether each will run. |
| `init` | Create a `preflight.php` config (`--force` to overwrite). |

### Options (for `run`)

| Option | Description |
|---|---|
| `--fix` | Apply fixes instead of only checking (fixable steps). |
| `--check` | Force check-only — overrides a `fixByDefault()` config. |
| `--format=<fmt>` | Console output: `auto` (default), `human`, `json`, `agent`, `github`, `sarif`, `markdown`. |
| `--write=<fmt>:<file>` | **Also** render to a file (repeatable). Run once, emit many — e.g. `--write=sarif:preflight.sarif --write=markdown:summary.md`. |
| `--fail-fast` | Stop at the first failing step. |
| `--files=a.php,b.php` | Check only these files. |
| `--dirty` | Check only working-tree changes (staged + unstaged + untracked). |
| `--all` | Check the whole project — overrides a `dirtyByDefault()` config. |
| `--since=<ref>` | Check only files changed since a git ref (e.g. `main`). |
| `-m, --module=<name>` | Check only a module's `app`/`tests` dirs (see modules below). |
| `--skip-if-fresh` | Skip the run entirely if inputs are unchanged since the last passing run (see [Freshness cache](#freshness-cache)). |
| `--report=<file>` | Also write a durable run report to this file (see [Reports](#reports)). |
| `--report-format=<fmt>` | Report file format: `json` (default). |
| `--report-include=<list>` | Report sections: `findings,steps,passing,output` (or `all`). Default `findings,steps`. |
| `[paths...]` | Positional paths to scope the run. |

`--format=auto` (the default) prints the **human** table on an interactive terminal and the
**agent** format when piped — so `preflight` reads nicely by hand and pipes cleanly into
scripts. Exit code is `0` on pass and non-zero on failure in every format.

`--fix`/`--check` and `--dirty`/`--all` are the explicit overrides for the matching config
defaults (`fixByDefault()` / `dirtyByDefault()`); a CLI flag always wins over config.

## Default steps

These are the steps Preflight runs out of the box, in this order (fast to slow). With no
`preflight.php`, a run **auto-detects** which of them apply: a step runs only when its tool
is installed in the project, and the rest are silently skipped. `composer-audit` is the
exception — `composer` ships its `audit` command, so it always runs.

| # | Step | Tool | Category | Check | Fix | Runs when |
|---|---|---|---|---|---|---|
| 1 | `pint` | Laravel Pint | Formatting | ✓ | ✓ | `laravel/pint` installed |
| 2 | `phpcs` | PHP_CodeSniffer | Coding standard | ✓ | ✓ (phpcbf) | `squizlabs/php_codesniffer` installed |
| 3 | `phpstan` | PHPStan | Static analysis | ✓ | | `phpstan/phpstan` installed |
| 4 | `rector` | Rector | Refactoring | ✓ | ✓ | `rector/rector` installed |
| 5 | `psalm` | Psalm | Static analysis | ✓ | | `vimeo/psalm` installed |
| 6 | `phpmd` | PHPMD (3.x) | Mess detection | ✓ | | `phpmd/phpmd` installed |
| 7 | `composer-audit` | Composer Audit | Dependency security | ✓ | | **always** (built into Composer) |
| 8 | `test` | PHPUnit / Paratest / Pest | Tests | ✓ | | a supported runner installed |

`vendor/bin/preflight doctor` shows, for your project, exactly which of these are installed
and would run; `vendor/bin/preflight steps` lists the steps the current config resolves to.
To change the set, see [Configuration](#configuration) (`withSteps()`, `tune()`, `without()`).

### Optional built-in steps

These ship with Preflight but aren't in the default set — add them in `preflight.php`:

| Step | Tool | Category | Check | Fix | Add with |
|---|---|---|---|---|---|
| `composer-normalize` | Composer Normalize | `composer.json` hygiene | ✓ | ✓ | `->withSteps([..., ComposerNormalize::class])` |
| `deptrac` | Deptrac | Architecture boundaries | ✓ | | `->withSteps([..., Deptrac::class])` |

`composer-normalize` runs the `ergebnis/composer-normalize` Composer plugin to keep
`composer.json` sorted and consistently formatted. It's opt-in because it needs that plugin
installed (`composer require --dev ergebnis/composer-normalize`).

`deptrac` enforces architectural layer boundaries from a `deptrac.yaml` depfile; each
violation is an error. It's opt-in because it only does something once you've defined an
architecture (`composer require --dev deptrac/deptrac` + a depfile).

Either skips with an install hint if its tool isn't installed, like any missing tool.

The `test` step auto-detects the runner: Paratest if installed, then Pest, then PHPUnit.
All three are driven through the same JUnit output, so findings carry file, line, and the
failing test's message. Coverage is off by default; opt in with
`Tests::make()->coverage(['clover' => 'build/coverage.xml'])` and gate on it with
`->minCoverage(90)` (whole project) or `->minPatchCoverage(90)` (only the lines a
`--since`/`--dirty` run changed — it names the exact uncovered lines, ideal for PR checks and
AI agents). Without a coverage driver (PCOV/Xdebug) the tests still run and a non-failing
warning is attached — see [the steps reference](docs/steps.md#coverage).

The `composer-audit` step needs **no install** — `composer` ships the `audit` command — so
it runs by default in every project, scanning `composer.lock` for known CVEs. A known
advisory fails the run; abandoned packages are surfaced as non-failing warnings by default.
Tune it with `ComposerAudit::make()->abandoned('ignore'|'report'|'fail')` and
`->locked(false)` (to audit installed packages instead of the lock file). Like the other
whole-project steps, a narrowed run (`--dirty`, `--files`) skips it.

## Configuration

Zero-config is fine. To customise, run `vendor/bin/preflight init` and edit `preflight.php`.
Steps are immutable, fluent, and referenced **by class**:

```php
<?php

use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Steps\{Pint, Phpcs, Phpstan, Rector, Psalm, Phpmd, Tests};

return Preflight::configure()
    // Explicit set + order (omit to auto-detect every installed tool):
    ->withSteps([
        Pint::class,
        Phpstan::make()->level(9)->memoryLimit('1G'),
        Tests::make()->before(['php', 'artisan', 'config:clear']),
    ])

    // Or keep the auto-detected default set and adjust it:
    ->tune(Psalm::make()->config('psalm.xml'))   // tweak one, keep the rest
    ->without(Phpmd::class)                       // drop one

    ->withPaths(['app', 'src'])                   // what to scan (default: auto)
    ->failFast();
```

Every step exposes the same base settings — `config()`, `before()`, `args()` — plus its own
(e.g. `Phpstan::make()->level()->memoryLimit()`, `Psalm::make()->threads()`,
`Tests::make()->runner('pest')->filter('...')`). Tool config files are read from the project
root by default; override per step with `->config('path/to/config')`.

#### What takes precedence

For any single setting the order is always:

> **explicit setter (or `args()`) → wins; else the tool's config file → wins; else the
> built-in default.**

So `Phpstan::make()->level(9)` runs at level 9 **even if `phpstan.neon` says `level: 5`** —
the setter is passed as `--level` and overrides the file. Leave the setter off and the file
wins; with neither, the default applies. The fluent API is therefore the full list of what
you can override from `preflight.php`: if there's a method (or you reach for `->args([...])`
to pass a raw flag), it overrides the config file; if there isn't, the config file owns it.

### Modules

For projects laid out as `Modules/<Name>/app` (e.g. nwidart/laravel-modules):

```php
return Preflight::configure()->withModules(dir: 'Modules', app: 'app', tests: 'tests');
```

Then `vendor/bin/preflight --module=Billing` scopes a run to that module.

### Run defaults

By default a bare `preflight` checks the whole project in check-only mode and prints the
auto format. You can shift those defaults so the *common* invocation needs no flags — handy
when the same command runs in many places (hooks, CI, agents):

```php
return Preflight::configure()
    ->fixByDefault()        // a bare `preflight` fixes what it can (override: --check)
    ->dirtyByDefault()      // ...scoped to working-tree changes (override: --all)
    ->defaultFormat('agent'); // ...and emits the agent format (override: --format=...)
```

Each default has a CLI escape hatch, so nothing is locked in. All three are **off** by
default — opt in only if you want them.

#### Agent preset

`forAgents()` is the one-call bundle of all three — scope to changes, auto-fix, agent
format — so a bare `preflight` does the right thing when a coding agent runs it (even one
that forgets the flags):

```php
return Preflight::configure()->forAgents();
// ≡ ->dirtyByDefault()->fixByDefault()->defaultFormat('agent')
```

Any piece can still be overridden afterwards or per-run via CLI flags. To keep the auto-fix
and agent format but check the whole project rather than just changes, pass
`->forAgents(dirty: false)`.

### Custom steps

A step is just a class implementing `Step` (extend `AbstractStep` for the fluent niceties):

```php
use PdxApps\Preflight\Steps\AbstractStep;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Context;

final class ComposerValidate extends AbstractStep
{
    public function label(): string { return 'Composer Validate'; }
    public function tool(): Tool { return Tool::system('composer'); }
    public function modes(): array { return [Mode::Check]; }
    public function targeting(): Targeting { return Targeting::Whole; }
    public function defaultConfig(): ?string { return null; }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        return StepPlan::exitCode($this->name(), ['composer', 'validate', '--strict']);
    }
}
```

Reference it like any built-in: `->withSteps([..., ComposerValidate::class])`. Its name
(`composer-validate`) is derived from the class automatically.

> A `composer-audit` security step ships built-in — see [Default steps](#default-steps).

## Output formats

- **human** — per-step `PASS`/`FAIL`/`SKIP` table with findings and a summary. Default on a TTY.
- **agent** — failure-only, ANSI-free, one `file:line:col [tool] message` per finding. Default
  when piped. Built for AI agents (see the [agent integrations](#ci--agents) below).
- **json** — `{success, steps[], findings[]}`; the machine/CI format.
- **github** — `::error`/`::warning` workflow commands; findings appear inline on the PR diff.
- **sarif** — SARIF 2.1.0 JSON, grouped into one run per tool. For GitHub code scanning
  (upload via `github/codeql-action/upload-sarif`) and other SARIF consumers.
- **markdown** — a per-step summary table plus a findings list. Ideal for a GitHub Actions
  job summary (`--write=markdown:"$GITHUB_STEP_SUMMARY"`).

### One run, many outputs

The checks are the slow part, so don't run them once per format. `--format` controls the
console; `--write=<fmt>:<file>` renders the **same result** to a file and is repeatable. A
single CI step can annotate the PR, write the job summary, emit SARIF, and keep a JSON
report — all from one execution:

```bash
preflight \
  --format=github \
  --write=markdown:"$GITHUB_STEP_SUMMARY" \
  --write=sarif:preflight.sarif \
  --report=preflight-report.json
```

See [`examples/github-actions.yml`](examples/github-actions.yml) for the full workflow.

## Reports

`--report=<file>` writes a durable JSON artifact alongside the normal console output —
something to upload from CI, diff between runs, or hand to another tool. It always carries
run metadata (preflight version, ISO-8601 timestamp, mode, success, duration, summary
counts) plus whichever sections you ask for:

```bash
vendor/bin/preflight --report=build/preflight.json
vendor/bin/preflight --report=build/preflight.json --report-include=all
```

`--report-include` is additive (default `findings,steps`):

| Section | Adds |
|---|---|
| `findings` | Every finding (file, line, col, tool, severity, message). |
| `steps` | Per-step status and timing. |
| `passing` | Passing steps too (otherwise only failed steps are listed). |
| `output` | Each step's raw tool output (verbose — for debugging). |
| `all` | All of the above. |

The console output is unaffected — the report is written *in addition* to it. Missing parent
directories are created.

## Freshness cache

`--skip-if-fresh` lets repeated runs short-circuit when nothing relevant has changed — built
for tight edit loops and multi-agent setups where the same check fires over and over:

```bash
vendor/bin/preflight --skip-if-fresh
# → "inputs unchanged since the last passing run — skipped (fresh)."  (no tools run)
```

It content-hashes the scoped source files **plus** the tool config files (`pint.json`,
`phpstan.neon`, …) and `composer.lock`, so a ruleset edit or a tool upgrade busts it just
like a source edit. A run is "fresh" — safe to skip — only when the hash matches the last
run **and** that run passed; a failure always re-runs.

The hash and outcome live in `.preflight.cache.json` in the project root (kept out of
`vendor/` so it survives `composer install`). `preflight init` adds it to `.gitignore`; if
you don't run `init`, gitignore it yourself.

## CI & agents

Ready-to-copy configs are in [`examples/`](examples/).

**CI:**
- [`github-actions.yml`](examples/github-actions.yml) — full run + a changed-files-only PR job.
- [`gitlab-ci.yml`](examples/gitlab-ci.yml)
- [`pre-commit`](examples/pre-commit) — fast hook that checks only what you're committing.

**Coding agents** — drop these into your assistant so it self-checks and self-fixes after
editing (each runs `preflight --fix --dirty --format=agent` in a loop until clean):
- [`claude/`](examples/claude/) — a [`SKILL.md`](examples/claude/skills/preflight/SKILL.md),
  a [subagent](examples/claude/agents/preflight.md), and a
  [`CLAUDE.md`](examples/claude/CLAUDE.md) snippet for Claude Code.
- [`codex/AGENTS.md`](examples/codex/AGENTS.md) — for OpenAI Codex (and the cross-tool
  `AGENTS.md` standard).
- [`gemini/GEMINI.md`](examples/gemini/GEMINI.md) — for Gemini CLI.
- [`cursor/`](examples/cursor/) — a `.cursor/rules/preflight.mdc` project rule for Cursor.

Preflight's `agent` output format (failure-only, ANSI-free, one `file:line:col [tool]
message` per finding, exit code as source of truth) is what makes this loop reliable.

## Programmatic API

The CLI is a thin shell over an in-process runner:

```php
use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Mode;

$config = require 'preflight.php';                 // a ConfigurationBuilder or Configuration
$result = Preflight::make($config->build())->run(Mode::Check);

$result->isSuccess();      // bool
$result->findings();       // Finding[] (severity-sorted)
$result->toArray();        // the JSON shape
```

## How it works

```
preflight.php ──► Configuration ──► Context (root, scope, config/tool resolution)
                                       │
        Steps ──plan()──► StepPlan ──► Runner ──► RunResult ──► Renderer
     (one per tool)     (cmd + parser)         (Finding[])    (human/json/agent/github/sarif)
```

A `Step` only *describes* work (it returns a `StepPlan`); the `Runner` executes it, each
tool's `OutputParser` normalises the output into one `Finding` schema, and a `Renderer`
formats the result. Adding a tool is a new `Step` + `OutputParser`; adding a format is a new
`Renderer`. The engine doesn't change.

## License

MIT
