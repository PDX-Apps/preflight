# Example CLAUDE.md snippet

Paste the section below into your project's `CLAUDE.md` so Claude Code self-checks with
Preflight after editing. (For a richer setup, see the `skills/` and `agents/` examples in
this directory — drop them into your project's `.claude/skills/` and `.claude/agents/`.)

---

## Code quality (Preflight)

After changing any PHP file, verify your work with Preflight before reporting done:

1. Auto-fix your changes: `vendor/bin/preflight --fix --dirty --format=agent`
2. Re-check: `vendor/bin/preflight --dirty --format=agent`
3. Fix every `file:line:col [tool] message` it reports, then repeat step 2 until it
   exits `0`. Do not report the task complete while any finding remains.

The exit code is authoritative (`0` = clean, non-zero = findings). `--format=agent` prints
errors only — no ANSI, no success noise. `--dirty` limits the run to files you changed.

**Useful flags:** `--fix` (auto-fix) / `--check` (check only) · scope with `--dirty`,
`--since=main` (changed since a ref — what CI/patch-coverage use), `--files=a.php,b.php`, or
`--all` (whole project) · pick steps with `--only=phpstan,test` or `--skip=phpmd` ·
`--format=agent|human|json|github|sarif|markdown` · `--fail-fast`. Other commands: `preflight doctor` (what's installed + the coverage driver), `preflight steps`
(the configured steps), `preflight init` (scaffold config), `preflight install` (add the tools).

**Configuration** lives in `preflight.php` (zero-config without it — every installed tool runs).
It returns `Preflight::configure()->...`: `withSteps([...])` / `addSteps([...])` / `tune(...)` /
`without(...)` to choose steps; `exclude([...])` to drop findings from paths across every tool
(framework scaffolding); per-step options like `Phpstan::make()->level(9)`,
`Tests::make()->minCoverage(80)->minPatchCoverage(90)`; `forAgents()` to default a bare run to
dirty + fix + agent format. See `docs/configuration.md` and `docs/steps.md` for the full set.

If it reports `Uncovered changed lines: …`, those are lines you changed that no test covers —
add a test driving the public path that reaches them. For a genuinely untestable line, exclude
it with a bare `// @codeCoverageIgnoreStart` / `// @codeCoverageIgnoreEnd` (reason on its own
line). If unsure or you can't reach the threshold, stop and ask rather than working around it.
