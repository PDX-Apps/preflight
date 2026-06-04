# AGENTS.md

`AGENTS.md` is the open, cross-tool instructions file (OpenAI Codex and other agents read
it). Drop this in your project root, or merge the section below into an existing `AGENTS.md`.

## Code quality (Preflight)

This project runs its PHP code-quality tools (formatter, linters, static analysis, tests)
through one command: Preflight. After changing any PHP file, before opening a pull request
or reporting done:

1. Auto-fix your changes: `vendor/bin/preflight --fix --dirty --format=agent`
2. Re-check: `vendor/bin/preflight --dirty --format=agent`
3. Fix every reported `file:line:col [tool] message`, then repeat step 2 until it exits `0`.

Notes:

- The exit code is authoritative: `0` = clean, non-zero = findings remain. Branch on it.
- `--format=agent` prints errors only (no ANSI, no success noise) — grep these lines.
- `--dirty` limits the run to files you changed; drop it to check the whole project.
- One file: `vendor/bin/preflight --files=path/to/File.php --format=agent`.
- Machine-readable detail: `vendor/bin/preflight --format=json`.
