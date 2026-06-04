# Configuration

Preflight is zero-config: with no `preflight.php` it auto-detects and runs the installed
[default steps](steps.md). To customize, scaffold a config and edit it:

```bash
vendor/bin/preflight init
```

`preflight.php` returns a configured builder:

```php
<?php

use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Steps\{Pint, Phpcs, Phpstan, Rector, Psalm, Phpmd, ComposerAudit, Tests};

return Preflight::configure()
    ->withSteps([
        Pint::class,
        Phpstan::make()->level(9)->memoryLimit('1G'),
        Tests::make()->before(['php', 'artisan', 'config:clear']),
    ]);
```

## Choosing steps

| Method | Effect |
|---|---|
| `->withSteps([...])` | Set the exact steps **and order**. Items are `Foo::class` (defaults) or `Foo::make()->...` (configured). Omit entirely to auto-detect every installed default. |
| `->tune(Step)` | Keep the auto-detected default set but reconfigure one step. |
| `->without(Foo::class)` | Drop one step from the default set. |
| `->withPaths([...])` | Directories to scan (default: auto). |
| `->withModules(dir, app, tests)` | Enable `--module` scoping (see below). |
| `->failFast()` | Stop at the first failing step. |

`tune()`/`without()` adjust the **default** set; `withSteps()` replaces it. Use one approach
or the other.

```php
return Preflight::configure()
    ->tune(Psalm::make()->config('psalm.xml'))   // tweak one, keep the rest
    ->without(Phpmd::class)                        // drop one
    ->failFast();
```

## What takes precedence

For any single setting the order is always:

> **explicit setter (or `->args()`) → wins; else the tool's config file → wins; else the
> built-in default.**

So `Phpstan::make()->level(9)` runs at level 9 **even if `phpstan.neon` says `level: 5`** —
it's passed as `--level`, which overrides the file. Leave the setter off and the file wins;
with neither, the default applies.

The fluent API is therefore the complete list of what you can override from `preflight.php`:
if there's a method (or you reach for `->args([...])` to pass a raw flag) it overrides the
config file; if there isn't, the config file owns that setting. See each step's options in
the [steps reference](steps.md).

### No silent invasive mutations

Preflight never silently applies security-relevant or invasive changes to your project (e.g.
Composer `allow-plugins` grants or `minimum-stability: dev`). Those are left to you, or asked
for with explicit consent — see [`install`](install.md#decisions-it-surfaces-never-silent).

## Run defaults

By default a bare `preflight` checks the whole project, in check-only mode, in the auto
format. You can shift those defaults so the *common* invocation needs no flags — useful when
the same command runs in hooks, CI, and agents. All three are **off** unless set, and each
has a CLI escape hatch:

```php
return Preflight::configure()
    ->fixByDefault()          // bare run fixes what it can       (override: --check)
    ->dirtyByDefault()        // ...scoped to working-tree changes (override: --all)
    ->defaultFormat('agent'); // ...emitting the agent format      (override: --format=...)
```

### Agent preset

`forAgents()` bundles all three, so a bare `preflight` does the right thing when a coding
agent runs it (even one that forgets the flags):

```php
return Preflight::configure()->forAgents();
// ≡ ->dirtyByDefault()->fixByDefault()->defaultFormat('agent')
```

Pass `->forAgents(dirty: false)` to keep auto-fix + agent format but check the whole project.
Any piece can still be overridden afterwards or per-run via CLI flags.

## Modules

For projects laid out as `Modules/<Name>/app` (e.g. nwidart/laravel-modules):

```php
return Preflight::configure()->withModules(dir: 'Modules', app: 'app', tests: 'tests');
```

Then `vendor/bin/preflight --module=Billing` scopes a run to that module's `app`/`tests`.

## Custom steps

A step is any class implementing `Step` — extend `AbstractStep` for the fluent niceties
(`make()`, `config()`, `before()`, `args()`, name derivation):

```php
use PdxApps\Preflight\Steps\AbstractStep;
use PdxApps\Preflight\Process\StepPlan;
use PdxApps\Preflight\Support\Tool;
use PdxApps\Preflight\Targeting;
use PdxApps\Preflight\Mode;
use PdxApps\Preflight\Context;

final class ComposerValidate extends AbstractStep
{
    public function label(): string { return 'Composer Validate'; }
    public function tool(): Tool { return Tool::system('composer'); }
    public function modes(): array { return [Mode::Check]; }
    public function targeting(): Targeting { return Targeting::Whole; }
    public function defaultConfig(): ?string { return null; }

    public function plan(Context $context, Mode $mode): StepPlan
    {
        return StepPlan::exitCode($this->name(), ['composer', 'validate', '--strict']);
    }
}
```

Reference it like any built-in: `->withSteps([..., ComposerValidate::class])`. Its name
(`composer-validate`) is derived from the class automatically.

A `Tool` is one of three kinds:

- `Tool::vendorBin('phpstan', 'phpstan/phpstan')` — a `vendor/bin` binary (available when the
  file exists).
- `Tool::system('composer')` — a binary on `PATH` (always available).
- `Tool::composerPlugin('ergebnis/composer-normalize')` — a `composer <subcommand>` plugin
  (available when its package is installed).

For findings (rather than a pass/fail exit code), return a `StepPlan::command(...)
->parseWith(new YourParser(...))` whose parser turns the tool's output into `Finding`s. The
built-in parsers under `src/Parsing/` are worked examples.

## Programmatic API

The CLI is a thin shell over an in-process runner:

```php
use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Mode;

$config = require 'preflight.php';                 // a ConfigurationBuilder
$result = Preflight::make($config->build())->run(Mode::Check);

$result->isSuccess();   // bool
$result->findings();    // Finding[] (severity-sorted)
$result->toArray();     // the JSON shape
```
