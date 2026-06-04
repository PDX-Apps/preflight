---
name: preflight
description: >-
  Check and auto-fix PHP code quality with Preflight. Use after changing any PHP
  files and before reporting work complete, to format, lint, statically analyse,
  and test the changes.
allowed-tools: Bash(vendor/bin/preflight*)
---

# Preflight

Verify your PHP changes with Preflight before reporting done. Work in a loop until the
checks pass.

1. **Auto-fix** what's fixable, scoped to what you changed:

   ```bash
   vendor/bin/preflight --fix --dirty --format=agent
   ```

2. **Re-check**, still scoped to your changes:

   ```bash
   vendor/bin/preflight --dirty --format=agent
   ```

3. If it prints any `file:line:col [tool] message` lines, **fix every one**, then repeat
   step 2. Do not report the task complete while any finding remains.

Notes:

- The **exit code is authoritative**: `0` = clean, non-zero = findings remain.
- `--format=agent` prints errors only (no ANSI, no success noise) — read or grep the lines.
- `--dirty` limits the run to files you changed; drop it to check the whole project.
- One file: `vendor/bin/preflight --files=path/to/File.php --format=agent`.
