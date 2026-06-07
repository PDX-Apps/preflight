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
