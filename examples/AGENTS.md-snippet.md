# Preflight + AI agents

Preflight is built to be driven by coding agents. Paste the block below into your
project's `AGENTS.md` (or whatever instructions file your agent reads) so it self-checks and
self-fixes after editing.

---

## Code quality (Preflight)

After changing any PHP file, verify your work with Preflight before reporting done:

1. **Fix what's auto-fixable**, scoped to what you touched:
   ```bash
   vendor/bin/preflight --fix --dirty --format=agent
   ```
2. **Check the result**, again scoped to your changes:
   ```bash
   vendor/bin/preflight --dirty --format=agent
   ```
3. If it reports findings, **fix every one** and repeat step 2 until it prints
   `PASS`. Do not report the task complete while any finding remains.

Notes for the agent:
- `--format=agent` prints one `file:line:col [tool] message` per finding (errors only,
  no ANSI, no success noise) — read or grep those lines directly.
- `--dirty` limits the run to files you changed (staged, unstaged, untracked), so it's
  fast and focused. Drop it to check the whole project.
- The exit code is authoritative: `0` = clean, non-zero = findings. You can branch on it.
- For a single file: `vendor/bin/preflight --files=path/to/File.php --format=agent`.
- Machine-readable detail: `--format=json` returns `{success, steps[], findings[]}`.
- `--skip-if-fresh` makes a re-run a no-op when nothing changed since the last passing
  run — cheap to call defensively in a loop, or to coordinate across multiple agents.

---

## Even simpler: make it the default

If the project owns its `preflight.php`, set the agent preset once so a bare
`vendor/bin/preflight` already scopes to changes, auto-fixes, and emits the agent format —
no flags for the agent to remember:

```php
// preflight.php
return PdxApps\Preflight\Preflight::configure()->forAgents();
```

With that in place the loop above collapses to: run `vendor/bin/preflight`, fix any
`file:line:col [tool] message` lines, repeat until it exits `0`.

---

Why this works well: agents triage best when they see only what's broken. The `agent`
format is deliberately failure-only and token-frugal, and `--dirty` keeps each loop
narrow so the agent fixes exactly what it just wrote.
