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
| `->coverage(array)` | Emit coverage reports as `format => path` (otherwise off). See below. |
| `->minCoverage(float)` | Fail the run under this whole-project line-coverage %. Implies coverage on. |
| `->minPatchCoverage(float)` | Fail the run under this **patch** (changed-line) coverage %. See below. |
| `->before(array)` | Common for `['php', 'artisan', 'config:clear']` before tests. |

```php
Tests::make()->runner('pest')->filter('Billing')->before(['php', 'artisan', 'config:clear'])
```

#### Coverage

Coverage is **off by default** (`--no-coverage`) — it's slow and needs a driver. Opt in with
`->coverage([...])`, a `format => path` map. Supported formats: `clover`, `cobertura`, `xml`,
`html`, `php`, `text` (`text` may use a `null` path to print a summary to stdout). All run
from one execution:

```php
Tests::make()->coverage([
    'clover' => 'build/coverage.xml',  // for Codecov/Coveralls
    'html'   => 'build/coverage',      // browsable report
])
```

`->minCoverage(90)` turns coverage into a gate: Pest enforces it natively (`--min`); for
PHPUnit/Paratest the step reads the percentage from `--coverage-text` and fails when it falls
short.

**Driver safety.** Coverage needs PCOV, phpdbg, or Xdebug. When a driver is present the step
adds the coverage flags (and sets `XDEBUG_MODE=coverage` for you if Xdebug is the driver).
When **no** driver is active, the tests still run and gate as usual, and a non-failing warning
is attached instead — so a local run without Xdebug isn't blocked, and `minCoverage` is
skipped rather than failing. Run `preflight doctor` to see the detected driver. With `auto`,
coverage runs under PHPUnit (serial) for reliability; pick `paratest`/`pest` explicitly for
parallel coverage.

#### Patch coverage (changed lines only)

Where `minCoverage` gates the **whole project**, `minPatchCoverage` gates only the lines the
current change touched — "did you test what you just wrote?". It's the signal a CI patch check
(or an AI agent) wants, and it composes with the whole-project floor:

```php
Tests::make()
    ->coverage(['clover' => 'build/coverage.xml'])
    ->minCoverage(80)         // whole-project floor — never regress
    ->minPatchCoverage(90);   // 90% of changed lines must be covered
```

How it works:

- It needs a **`clover`** report (it reads per-line hit data) and a **change-scoped run**
  (`--since=<ref>` or `--dirty`). On a whole-project run there's no diff, so the gate is inert.
- The whole suite still runs (so coverage of the diff is measured against *all* tests); only
  the **gate** is scoped to the changed lines.
- The denominator is the changed lines coverage can measure: comments, braces, and lines in
  files the suite never loads don't count. Private methods are covered transitively — drive the
  public method that calls them.
- On a shortfall it names the exact uncovered lines per file, e.g.
  `src/Foo.php — Uncovered changed lines: 42-45, 51`, so the fix is unambiguous.
- On a **passing** run it still reports the number — `patch coverage 100.00% (191/191 changed
  lines)` — in the human output, the Markdown job summary, and the JSON report
  (`steps[].metrics`). `minCoverage` reports its whole-project `line coverage %` the same way.
- Without a clover report or a driver, it attaches a non-failing warning instead of failing.

**On choosing the threshold.** 100% patch coverage is far more attainable than 100%
whole-project (it only judges the diff), but a genuinely untestable changed line — an
environment-dependent branch, a defensive `return` — still happens. For those, exclude the line
with a **bare** marker (the reason goes on its own line; trailing text on the marker line is
silently ignored by php-code-coverage):

```php
// Only reachable when the temp dir is unwritable — not reproducible in a test.
// @codeCoverageIgnoreStart
return Result::failure('cannot write');
// @codeCoverageIgnoreEnd
```

Ignored lines leave both the numerator and denominator, so the gate stays honest. Prefer a
per-line ignore (visible in review) over lowering the threshold for the whole project; pick a
threshold like 80–90 if your team doesn't want to annotate, and reserve 100 for codebases
willing to keep that discipline.

---

## Optional built-in steps

These ship with Preflight but are **not** in the default set — add them in `preflight.php`
with `->addSteps([TheStep::class])` (keeps the auto-detected defaults) or by listing them in
`->withSteps([...])`. Each skips with an install hint if its tool isn't installed.

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
| **Whole** | composer-audit, deptrac | Can't scope to a subset, so a narrowed run **skips** it (its concern isn't tied to which files changed). |

On a whole-project run (no scope flags), every step uses its own configured scope — which is
why a scaffolded `phpstan.neon`/`phpcs.xml`/`rector.php` needs its own paths.

## Writing your own

Any class implementing `Step` works — see
[Configuration § custom steps](configuration.md#custom-steps).
