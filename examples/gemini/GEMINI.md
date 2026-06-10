# GEMINI.md

`GEMINI.md` is Gemini CLI's context file. Drop this in your project root (or `~/.gemini/`
for all projects), or merge the section below into an existing `GEMINI.md`.

## Code quality (Preflight)

This project runs its PHP code-quality tools (formatter, linters, static analysis, tests)
through one command: Preflight. After changing any PHP file, before reporting done:

1. Auto-fix your changes: `vendor/bin/preflight --fix --dirty --format=agent`
2. Re-check: `vendor/bin/preflight --dirty --format=agent`
3. Fix every reported `file:line:col [tool] message`, then repeat step 2 until it exits `0`.

Notes:

- The exit code is authoritative: `0` = clean, non-zero = findings remain.
- `--format=agent` prints errors only (no ANSI, no success noise).
- `--dirty` limits the run to files you changed; drop it to check the whole project.
- One file: `vendor/bin/preflight --files=path/to/File.php --format=agent`.
- More scope/mode: `--fix`/`--check`, `--since=main` (changed since a ref — CI/patch-coverage),
  `--all` (whole project), `--module=<Name>`, `--only=phpstan,test` / `--skip=phpmd` (pick steps
  by name), `--fail-fast`, `--format=json`. Other commands:
  `preflight doctor` (installed tools + coverage driver), `preflight steps`, `preflight init`,
  `preflight install`.
- **Config** is `preflight.php` (zero-config without it). It returns `Preflight::configure()->...`:
  `withSteps`/`addSteps`/`tune`/`without` to pick steps; `exclude([...])` to drop findings from
  paths across every tool; per-step options (`Phpstan::make()->level(9)`,
  `Tests::make()->minCoverage(80)->minPatchCoverage(90)`); `forAgents()` for agent-friendly
  defaults. Full reference: `docs/configuration.md`, `docs/steps.md`.
- `Uncovered changed lines: …` means lines you changed lack a test — cover them via the public
  path that reaches the code. For a genuinely untestable line, exclude it with a bare
  `// @codeCoverageIgnoreStart` / `// @codeCoverageIgnoreEnd` (reason on its own line). If
  unsure or stuck, stop and ask rather than writing contrived tests or blanket-ignoring code.
