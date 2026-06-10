---
name: preflight
description: >-
  Check and auto-fix PHP code quality with Preflight. Use after changing any PHP
  files and before reporting work complete, to format, lint, statically analyse,
  and test the changes — and when configuring Preflight (preflight.php), choosing
  flags, or adjusting steps, coverage gates, or excluded paths.
allowed-tools: Bash(vendor/bin/preflight*), Read, Edit
---

# Preflight

Preflight runs a project's PHP code-quality tools — formatter, linters, static analysis,
mess detection, security audit, tests/coverage — through **one command** with one normalized
output. Every tool runs only if it's installed.

This skill covers two things:

1. **[The run loop](#1-the-run-loop)** — verify and fix your changes before reporting done.
2. **[Flags, commands, and configuration](#2-flags-commands-and-configuration)** — the full
   CLI surface and how `preflight.php` is set up, so you can scope a run, change formats, or
   adjust steps/coverage/excludes when asked.

---

## 1. The run loop

Verify your PHP changes before reporting done. Work in a loop until the checks pass.

1. **Auto-fix** what's fixable, scoped to what you changed:

   ```bash
   vendor/bin/preflight --fix --dirty --format=agent
   ```

2. **Re-check**, still scoped to your changes:

   ```bash
   vendor/bin/preflight --dirty --format=agent
   ```

3. If it prints any `file:line:col [tool] message` lines, **fix every one** (fix the
   underlying issue — don't suppress it), then repeat step 2. Do not report the task complete
   while any finding remains.

- The **exit code is authoritative**: `0` = clean, non-zero = findings remain. Branch on it.
- `--format=agent` prints errors only (no ANSI, no success noise) — read or grep the lines.
- `--dirty` limits the run to files you changed; drop it to check the whole project.
- One file: `vendor/bin/preflight --files=path/to/File.php --format=agent`.

---

## 2. Flags, commands, and configuration

### Commands

`preflight` (no subcommand) is the same as `preflight run`. The others are setup/inspection:

| Command | What it does |
|---|---|
| `preflight` / `run` | Run the checks (or fixes with `--fix`). The default. |
| `preflight doctor` | Report installed tools, discovered config files, the active coverage driver, and what would run. **Use this first** to understand a project's setup. |
| `preflight steps` | List the configured steps and whether each tool is available. |
| `preflight init` | Scaffold a `preflight.php` to customize from. |
| `preflight install` | Preview and `composer require --dev` the tools the steps need, scaffolding their config files. Prompts for decisions (or drive with `--runner=`, `--with-phpmd`, `--yes`). |

### `run` flags

**Mode**
- `--fix` — apply fixes instead of only checking (Pint, PHPCS via phpcbf, Rector, Composer Normalize).
- `--check` — force check-only, overriding a fix-by-default config.

**Scope** (what to check)
- *(none)* — the whole project.
- `--dirty` — only files changed in the working tree. `--all` forces whole-project over a dirty-by-default config.
- `--since=<ref>` — only files changed since a git ref (e.g. `--since=main`). This is what a PR/CI check uses, and it's what feeds **patch coverage**.
- `--files=a.php,b.php` — an explicit comma-separated list. Positional paths work too: `preflight app/ tests/Unit/Foo.php`.
- `--module=Billing` — scope to one module's `app`/`tests` (projects using `->withModules(...)`).

  Whole-only steps (composer-audit, composer-normalize, deptrac) **skip** under any narrowed scope — their concern isn't tied to which files changed.

**Which steps** (run a subset of the pipeline, by step name — the names `preflight steps` prints)
- `--only=phpstan,test` — run only these steps.
- `--skip=phpmd` — run every step except these.
- Mutually exclusive; an unknown name is a hard error listing the valid names.

**Format** (`--format=` / `-f`)
- `agent` — errors only, no ANSI; the format to parse in an agent loop.
- `human` — coloured per-step output, streamed live, with a summary (the interactive default).
- `json` — a machine-readable document (`success`, `steps`, findings).
- `github` — inline `::error` PR annotations.
- `sarif` — a SARIF report for code-scanning.
- `markdown` — a job-summary table.
- `auto` (default) — `human` on a terminal, `agent` when piped.

**Outputs / extras**
- `--write=FORMAT:PATH` (repeatable) — also render to a file; the checks run once. e.g. `--write=markdown:summary.md --write=sarif:preflight.sarif`.
- `--report=PATH` + `--report-include=findings,steps,passing,output,all` — write a JSON run report.
- `--fail-fast` — stop at the first failing step.
- `--skip-if-fresh` — skip the run entirely if inputs are unchanged since the last passing run.

### Configuration: `preflight.php`

Preflight is **zero-config** — with no `preflight.php` it auto-detects and runs every installed
default step (Pint, PHPCS, PHPStan, Rector, Psalm, PHPMD, Composer Audit, Tests). A
`preflight.php` at the project root customizes that. Scaffold one with `preflight init`. It
returns a fluent builder:

```php
<?php

use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Steps\{Pint, Phpstan, Tests};

return Preflight::configure()
    ->withSteps([
        Pint::class,
        Phpstan::make()->level(9)->memoryLimit('1G'),
        Tests::make()->before(['php', 'artisan', 'config:clear']),
    ]);
```

**Choosing the step set** (use *one* approach):

| Method | Effect |
|---|---|
| `->withSteps([...])` | Set the exact steps **and order**. Items are `Foo::class` or `Foo::make()->...`. |
| `->addSteps([...])` | Keep the auto-detected defaults and **append** (e.g. the opt-in `ComposerNormalize`/`Deptrac`). |
| `->tune(Foo::make()->...)` | Keep the defaults but reconfigure one step. |
| `->without(Foo::class)` | Drop one default step. |

**Run-wide options:**

| Method | Effect |
|---|---|
| `->withPaths([...])` | Directories to scan (default: auto). |
| `->exclude([...])` | Drop findings from these paths **across every tool** — for framework scaffolding the analysers misjudge (e.g. `app/Providers`, `app/Actions/Fortify`, `database`, globs like `app/Legacy/*.php`). A step whose findings were all excluded passes; a real crash stays failing. |
| `->withModules(dir, app, tests)` | Enable `--module=` scoping for `Modules/<Name>/app` layouts. |
| `->failFast()` | Stop at the first failing step. |
| `->fixByDefault()` | A bare run fixes (override: `--check`). |
| `->dirtyByDefault()` | A bare run scopes to working-tree changes (override: `--all`). |
| `->defaultFormat('agent')` | Default output format. |
| `->forAgents()` | Bundle the three above (`dirtyByDefault` + `fixByDefault` + `defaultFormat('agent')`) so a bare `preflight` does the right thing for a coding agent. |

**Per-step options** (the common ones — full list in `docs/steps.md`):

- `Phpstan::make()->level(0..9)->memoryLimit('1G')`
- `Phpcs::make()->standard('PSR12')->parallel(4)`
- `Psalm::make()->threads(4)->noCache()`
- `Phpmd::make()->rulesets(['cleancode','codesize'])->paths(['app','src'])`
- `ComposerAudit::make()->abandoned('report'|'ignore'|'fail')`
- `Tests::make()->runner('auto'|'pest'|'phpunit'|'paratest')->filter('Billing')->before([...])`
- Every step also has `->config(?string $path)`, `->before([...])`, and `->args([...])` (raw flags escape hatch).

**Config precedence**, for any single setting: an explicit setter (or `->args()`) wins → else
the tool's own config file (`phpstan.neon`, `pint.json`, …) → else the built-in default. So
`Phpstan::make()->level(9)` runs at level 9 even if `phpstan.neon` says level 5. **Never
silently apply** security-relevant Composer changes (`allow-plugins`, `minimum-stability`) —
those are the user's call.

For the exhaustive reference, read `docs/configuration.md` and `docs/steps.md` in the package.

### Coverage gates

Set on the Tests step:

```php
Tests::make()
    ->coverage(['clover' => 'build/coverage.xml'])  // needed for any gate
    ->minCoverage(80)         // whole-project line-coverage floor — never regress
    ->minPatchCoverage(90);   // 90% of the lines THIS change touched must be covered
```

Coverage needs a driver (PCOV/phpdbg/Xdebug) — `preflight doctor` shows it. Without one, tests
still run and the gate is skipped with a non-failing warning. `minPatchCoverage` also needs a
change-scoped run (`--since`/`--dirty`) and a `clover` report; on a whole-project run it's inert.

---

## Patch-coverage findings (`Uncovered changed lines`)

If a project gates patch coverage, a shortfall looks like:

```
src/Foo.php:42 [test] Uncovered changed lines: 42-45, 51
```

Those are lines **you changed** that no test exercises. To resolve, in order:

1. **Write a test that covers them.** Drive the public method that reaches the code — you
   don't (and can't) test private methods directly; covering the public path covers them.
2. **If a line is genuinely untestable** (an environment-dependent branch, a defensive
   `return` you can't reproduce), exclude it with a **bare** marker — the reason goes on its
   own line; trailing text on the marker line is silently ignored:

   ```php
   // Only reachable when the temp dir is unwritable — not reproducible in a test.
   // @codeCoverageIgnoreStart
   return Result::failure('cannot write');
   // @codeCoverageIgnoreEnd
   ```

3. **If you're unsure whether ignoring is legitimate, or you can't reach the threshold after a
   real attempt, STOP and ask the user.** Do not loop indefinitely adding contrived tests or
   blanket-ignoring code. If the user agrees the target is too strict for this project, lower
   it in `preflight.php` (`->minPatchCoverage(N)`) — a visible, reviewable decision — rather
   than working around the gate.
