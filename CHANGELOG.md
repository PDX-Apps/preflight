# Changelog

All notable changes to `pdxapps/preflight` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-06-13

### Removed

- **Dropped the `composer-normalize` step (breaking).** The `ComposerNormalize` step and its
  parser are gone, along with the `Tool::composerPlugin()` factory and the Composer-plugin
  availability path that only it used. `ergebnis/composer-normalize` is a Composer **plugin**,
  so using it means allow-listing code to run on every `composer install`/`update` and pulling
  a chain of `ergebnis/*` packages into the tree — a supply-chain surface that isn't worth it
  for sorting `composer.json`. Deptrac (a plain binary, no install-time code execution) remains
  the one opt-in step. If you relied on it, drop `ComposerNormalize::class` from your
  `preflight.php` and run `composer normalize` directly, or pin to `^0.1`.

## [0.1.6] - 2026-06-13

### Fixed

- **Excluded findings no longer flash a phantom failure during a live run.** `exclude()` was
  applied once after the whole run, but the live per-step progress lines are printed *during*
  it — so a step whose only findings sat under an excluded path (e.g. `config/` on a Laravel
  app) streamed a red `FAIL` with those findings, then the final summary, having dropped them,
  said "all checks passed". The two contradicted each other. The exclude now runs per-step
  inside the runner, before each result is reported, so the streamed line shows the same
  verdict the summary will — and a failure that's entirely excluded never appears as a failure.
  This also means `--fail-fast` won't abort on a step whose findings are all excluded.

## [0.1.5] - 2026-06-12

### Added

- **Live step progress in machine formats too — on stderr.** Previously only the `human` format
  streamed each step's `PASS`/`FAIL` as it finished; the other formats (agent, json, sarif,
  github, markdown) printed nothing until the run ended, which looks hung on a slow run. Those
  formats now narrate the same per-step lines on **stderr**, leaving the rendered document on
  stdout untouched — so a human watching an agent-format run sees it work, while anything
  parsing stdout still gets a clean, atomic payload. To avoid leaking progress into captured
  output (where a CI log is indistinguishable from an agent reading combined streams), it's
  active only when stderr is an interactive terminal.

### Changed

- **Psalm now gets a scaffolded `psalm.xml` instead of `psalm --init`.** `install` previously
  delegated Psalm to its own `--init`, whose template hard-codes `findUnusedCode="true"`. That
  dead-code pass is whole-program reachability and misreads Laravel, which wires its entry
  points at runtime — controllers resolved by the router, service providers autoloaded from a
  module's `composer.json`, seeders and test methods invoked reflectively all get reported as
  `UnusedClass` / `PossiblyUnusedMethod`. Preflight now scaffolds its own `psalm.xml`
  (`errorLevel` 4, project dirs from the detected source paths) with `findUnusedCode` and
  `findUnusedBaselineEntry` off and a comment explaining why. This also folds Psalm into the
  same stub mechanism as every other tool.

## [0.1.4] - 2026-06-12

### Added

- **`--only` / `--skip` step selection.** Run a subset of the pipeline by step name without
  editing `preflight.php`: `preflight --only=phpstan,test` runs just those, `preflight
  --skip=phpmd` runs everything else. The two are mutually exclusive, and an unknown name is a
  hard error that lists the valid step names (so a typo fails loudly instead of silently
  running everything). Also available in config as `->only([...])` / `->withSkip([...])` — the
  latter was previously inert and is now wired in.
- **Live step-by-step progress in the human format.** A `human` run now prints each step's
  result the moment it finishes — watch `PASS`/`FAIL` land one by one instead of waiting for
  the whole run — with the summary after. On an interactive terminal a transient "running …"
  line shows the step in flight, then is replaced by its result; piped/CI output gets the same
  lines incrementally (no control codes). Machine formats (json, sarif, github, markdown) are
  unchanged — they still render once from the finished result. Internally this is a
  `ProgressReporter` the runner calls per step, so a future watcher or parallel runner can hook
  in without touching any step.

### Changed

- **Thorough agent docs.** The Claude skill (and the `CLAUDE.md`/`AGENTS.md`/`GEMINI.md`/Cursor
  snippets and sub-agent) now teach the full CLI surface — every scope/mode/format flag and the
  `doctor`/`steps`/`init`/`install` commands — and how `preflight.php` is configured (step
  selection, `exclude()`, per-step options, coverage gates, `forAgents()`), not just the
  fix/check loop. The skill points at `docs/configuration.md` and `docs/steps.md` for the
  exhaustive reference.
- **Config scaffolds moved to stub files.** `install`'s starter configs now live as real,
  lintable templates under `stubs/configs/` instead of inline PHP heredocs. Static configs
  (`pint.json`, `phpmd.xml`) are copied verbatim; the ones that must list source dirs
  (`phpstan.neon`, `phpcs.xml`, `rector.php`) carry a `{{ paths }}`/`{{ files }}` token filled
  from the detected dirs. Generated output is unchanged.

### Fixed

- **Default `rector.php` no longer fights Psalm.** The scaffolded Rector config now skips
  `AddOverrideAttributeToOverriddenPropertiesRector` — the PHP 8.5 rule that stamps
  `#[\Override]` onto overridden *properties*, which is invalid (the attribute is method-only)
  and which Psalm rejects as `InvalidAttribute`. `#[\Override]` on overridden *methods* stays,
  since Psalm requires it. A fresh install's default tool set is now self-consistent.

## [0.1.3] - 2026-06-07

### Added

- **`->exclude([...])`** drops findings whose file matches a path, across every tool at once —
  for framework-scaffolded code the analysers misjudge (service providers, Fortify actions, …).
  Patterns match a finding's file by equality, parent directory, or glob. Works uniformly
  because Preflight normalizes every tool's output, including ones with no CLI exclude (PHPStan,
  Psalm, Rector, Pint). A step whose findings were all excluded passes; a crash with no findings
  is left failing.

### Changed

- **Richer, copy-friendly `preflight.php` scaffold.** The `init`/`install` stub now shows
  paste-ready example statements (real `//` comment lines instead of a `|`-banner you can't copy
  cleanly) and demonstrates coverage, the opt-in steps, and `exclude()`.

## [0.1.2] - 2026-06-07

### Fixed

- **PHPMD no longer errors on Laravel-style projects.** Its default scan paths are now
  `app`/`src` filtered to those that exist (was a hard-coded `src`), so a project that uses
  `app` doesn't make PHPMD fail with `"src" does not exist`.
- **PHPStan no longer crashes at 128M out of the box.** The step now passes `--memory-limit=-1`
  by default (override with `Phpstan::make()->memoryLimit('1G')`), so a real app's analysis
  isn't killed by the subprocess's php.ini memory limit.

## [0.1.1] - 2026-06-07

### Fixed

- **Allow Symfony 8.** `symfony/console` and `symfony/process` now accept `^7.4 || ^8.0`
  (were `^7.4`), so Preflight installs alongside apps on Symfony 8 — e.g. Laravel 13 — without
  a dependency conflict, while still supporting Symfony 7.4 (Laravel 11/12).
- **Lower the PHP floor to 8.3** (was 8.4), so it installs on Laravel 12 apps running PHP 8.3.
  The few PHP 8.4-only expressions (`new Foo()->bar()`) were rewritten as `(new Foo())->bar()`.
  A CI matrix now tests PHP 8.3/8.4 × Symfony 7.4/8.0.

### Added

- **Coverage % in the results.** A passing run now reports the measured coverage as an
  informational metric — patch coverage when `minPatchCoverage` is set, whole-project line
  coverage when `minCoverage` is set — shown in the human output, the Markdown job summary
  (a "Coverage" section), and the JSON report (`steps[].metrics`). Runs without a coverage
  gate show nothing extra.
- **Line-level patch (diff) coverage.** `Tests::make()->minPatchCoverage(N)` gates the run on
  the coverage of only the lines the current change touched, on a `--since`/`--dirty` run. It
  reads the `clover` report, runs the whole suite (so the diff is measured against every test),
  and on a shortfall names the exact uncovered changed lines per file — the signal a PR check or
  an AI agent needs. Inert on a whole-project run; warns (without failing) when no clover report
  or coverage driver is available. Composes with the whole-project `minCoverage` floor.

## [0.1.0] - 2026-06-07

Initial release — a framework-agnostic, AI/CI-native code-quality runner for PHP.

### Added

- **Auto-detecting pipeline.** Eight built-in steps run only when their tool is
  present: Pint, PHPCS, PHPStan, Psalm, Rector, PHPMD, Composer Audit, and Tests
  (PHPUnit / Pest / Paratest). Two opt-in steps — Composer Normalize and Deptrac —
  round out the set.
- **Immutable, fluent configuration.** `Preflight::configure()` with `withSteps`
  (replace the set), `addSteps` (append to the defaults), `tune` (reconfigure a step
  by class), and `without` (drop a step), plus `defaultFormat`, `fixByDefault`,
  `dirtyByDefault`, `forAgents`, and `failFast`.
- **Config precedence.** Each step resolves its arguments from an explicit setter
  (or `->args()`), then the tool's own config file, then a built-in default.
- **Multiple output formats** — `human`, `json`, `agent`, `github`, `sarif`, and
  `markdown` — selectable with `--format` for stdout.
- **One run, many outputs.** `--write=FORMAT:PATH` (repeatable) renders the same
  result to files without re-running the checks, and `--report=PATH` keeps a JSON
  artifact. A single invocation can emit inline PR annotations, a job-summary table,
  a SARIF report, and a JSON report at once.
- **Code coverage.** `Tests::make()->coverage([...])` emits clover / cobertura /
  xml / html / php / text reports, and `->minCoverage(N)` gates the run on a line-
  coverage floor. The driver (PCOV ▸ phpdbg ▸ Xdebug) is detected at the composition
  root; when none is available the tests still run with a non-failing warning.
- **`install` command** — previews and `composer require --dev`s the tools your
  steps need and scaffolds their config files, prompting (or driven by `--runner=`,
  `--with-phpmd`, `--yes`) for decisions it won't make silently.
- **`doctor` command** — reports installed tools, discovered config files, the
  active coverage driver, and what would run.
- **`init` / `steps` commands** — scaffold a `preflight.php` and list the configured
  steps with their availability.

[Unreleased]: https://github.com/PDX-Apps/preflight/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/PDX-Apps/preflight/compare/v0.1.6...v0.2.0
[0.1.6]: https://github.com/PDX-Apps/preflight/compare/v0.1.5...v0.1.6
[0.1.5]: https://github.com/PDX-Apps/preflight/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/PDX-Apps/preflight/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/PDX-Apps/preflight/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/PDX-Apps/preflight/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/PDX-Apps/preflight/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/PDX-Apps/preflight/releases/tag/v0.1.0
