# Steps reference

A **step** wraps one tool. Each runs the tool, normalizes its output into Preflight's single
`Finding` schema, and reports pass/fail. This page documents every built-in step and its
options.

- [Common options](#common-options) — shared by every step
- [Default steps](#default-steps) — auto-run when their tool is installed
- [Optional built-in steps](#optional-built-in-steps) — ship with Preflight, opt-in
- [Targeting](#targeting) — how steps respond to scope flags

## Common options

Every step is an immutable, fluent builder created with `::make()`. These methods exist on
all of them and return a configured clone:

| Method | Purpose |
|---|---|
| `->config(?string $path)` | Override the tool's config file (or `null` to let the tool find its own). |
| `->before(array $command)` | Run a command before this step (e.g. `['php', 'artisan', 'config:clear']`). Accumulates. |
| `->args(array $args)` | Append raw CLI flags to the tool invocation. Always passed (an escape hatch for anything without a dedicated method). |

[Precedence](configuration.md#what-takes-precedence) for any setting: an explicit setter (or
`->args()`) wins, else the tool's config file, else the built-in default.

---

## Default steps

Run in this order (fast → slow), each only when its tool is present (except `composer-audit`,
which ships with Composer).

### `pint` — Laravel Pint

Opinionated code-style fixer. **Check + fix.** Reads `pint.json` if present, else Pint's
default preset.

```php
Pint::make()->config('pint.json')
```

### `phpcs` — PHP_CodeSniffer

Coding-standard checks that complement Pint (forbidden functions, line length, sniffs Pint
has no fixer for). **Check + fix** (fix runs `phpcbf`). Uses `phpcs.xml`/`phpcs.xml.dist` if
present, else the standard below.

| Option | Purpose |
|---|---|
| `->standard(string)` | Coding standard when no `phpcs.xml` (default `PSR12`). |
| `->parallel(int)` | Run N processes. |

```php
Phpcs::make()->standard('PSR12')->parallel(4)
```

### `phpstan` — PHPStan

Static analysis. **Check only.** Uses `phpstan.neon`/`phpstan.neon.dist` if present.

| Option | Purpose |
|---|---|
| `->level(int)` | Analysis level 0–9. Overrides a `phpstan.neon` level when set. |
| `->memoryLimit(string)` | e.g. `'1G'`. |

```php
Phpstan::make()->level(9)->memoryLimit('1G')
```

### `rector` — Rector

Automated refactoring. **Check + fix** (check uses `--dry-run`). Requires a `rector.php`
(Rector needs it to know its rule set).

```php
Rector::make()->config('rector.php')
```

### `psalm` — Psalm

Static analysis. **Check only.** Uses `psalm.xml`/`psalm.xml.dist` if present.

| Option | Purpose |
|---|---|
| `->threads(int)` | Multi-threaded analysis. |
| `->noCache()` | Disable Psalm's cache. |

```php
Psalm::make()->threads(4)->noCache()
```

### `phpmd` — PHPMD

Mess detector (complexity, unused code, naming). **Check only.** Uses `phpmd.xml` if present,
else the rule sets below. Needs PHPMD 3.x for PHP 8.4+.

| Option | Purpose |
|---|---|
| `->rulesets(array)` | Built-in rule sets to use, e.g. `['cleancode', 'codesize']`. Overrides `phpmd.xml` when set. |
| `->paths(array)` | Directories to scan on a whole-project run (default `['src']`). |

```php
Phpmd::make()->rulesets(['cleancode', 'codesize'])->paths(['app', 'src'])
```

### `composer-audit` — Composer Audit

Scans `composer.lock` for known security advisories (CVEs). **Check only.** Needs **no
install** — `composer` ships the command — so it always runs. A known advisory fails the
run; abandoned packages are surfaced as non-failing warnings by default.

| Option | Purpose |
|---|---|
| `->abandoned(string)` | `report` (warn, don't fail — default), `ignore`, or `fail`. |
| `->locked(bool)` | Audit the committed lock file (default) or, with `false`, installed packages. |

```php
ComposerAudit::make()->abandoned('ignore')->locked()
```

### `test` — PHPUnit / Paratest / Pest

Runs your test suite. **Check only.** Auto-detects the runner: Paratest if installed, then
Pest, then PHPUnit — all driven through the same JUnit output, so a failing test carries its
file, line, and message. Uses `phpunit.xml`/`phpunit.xml.dist` if present.

| Option | Purpose |
|---|---|
| `->runner(string)` | Force a runner: `auto` (default), `paratest`, `pest`, `phpunit`. |
| `->filter(string)` | Run only matching tests (`--filter`). |
| `->before(array)` | Common for `['php', 'artisan', 'config:clear']` before tests. |

```php
Tests::make()->runner('pest')->filter('Billing')->before(['php', 'artisan', 'config:clear'])
```

---

## Optional built-in steps

These ship with Preflight but are **not** in the default set — add them in `preflight.php`
with `->addSteps([TheStep::class])` (keeps the auto-detected defaults) or by listing them in
`->withSteps([...])`. Each skips with an install hint if its tool isn't installed.

### `composer-normalize` — Composer Normalize

Keeps `composer.json` sorted and consistently formatted. **Check + fix.** Runs the
`ergebnis/composer-normalize` Composer plugin; `--no-update-lock` keeps it off
`composer.lock`.

```bash
composer require --dev ergebnis/composer-normalize
```
```php
->addSteps([ComposerNormalize::class])
```

It's opt-in because it's a Composer **plugin** — installing it means allow-listing it to run
code during composer operations, a decision you make deliberately (see
[install §opt-in tools](install.md#opt-in-tools-arent-auto-installed)).

### `deptrac` — Deptrac

Enforces architectural layer boundaries from a `deptrac.yaml` depfile. **Check only.** Each
violation is an error finding with file + line.

```bash
composer require --dev deptrac/deptrac
```
```php
->addSteps([Deptrac::class])
```

It's opt-in because it only does something once you've defined an architecture (the depfile).

---

## Targeting

How a step responds to scope flags (`--files`, `--dirty`, `--since`, `--module`, positional
paths):

| Targeting | Steps | Behaviour under a narrowed run |
|---|---|---|
| **Files** | pint, phpcs, phpstan, rector, psalm, test | Receives the exact changed files. |
| **Paths** | phpmd | Changed files widened to their directories. |
| **Whole** | composer-audit, composer-normalize, deptrac | Can't scope to a subset, so a narrowed run **skips** it (its concern isn't tied to which files changed). |

On a whole-project run (no scope flags), every step uses its own configured scope — which is
why a scaffolded `phpstan.neon`/`phpcs.xml`/`rector.php` needs its own paths.

## Writing your own

Any class implementing `Step` works — see
[Configuration § custom steps](configuration.md#custom-steps).
