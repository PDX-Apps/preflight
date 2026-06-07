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
- `Uncovered changed lines: …` means lines you changed lack a test — cover them via the public
  path that reaches the code. For a genuinely untestable line, exclude it with a bare
  `// @codeCoverageIgnoreStart` / `// @codeCoverageIgnoreEnd` (reason on its own line). If
  unsure or stuck, stop and ask rather than writing contrived tests or blanket-ignoring code.
