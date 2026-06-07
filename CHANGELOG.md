# Changelog

All notable changes to `pdxapps/preflight` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/PDX-Apps/preflight/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/PDX-Apps/preflight/releases/tag/v0.1.0
