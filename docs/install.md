# `preflight install`

Sets up the tools your steps need and scaffolds their config files — so a fresh project
goes from "nothing installed" to "a working pipeline" in one command. It's **confirm-first**
and never mutates your project silently.

> Already have your tools? You don't need this. Preflight auto-detects installed tools, so
> `install` is only for filling the gaps.

## What it does

1. Resolves your steps (the [default set](steps.md), plus anything your `preflight.php` adds).
2. Figures out which of their tools are **missing** (already-installed tools are left alone).
3. **Previews** exactly what it will do — the `composer require --dev …` line and the config
   files it will create.
4. Asks for confirmation (interactive) or proceeds with `--yes`.
5. Runs `composer require --dev` for the approved packages.
6. Scaffolds a starter config for each newly installed tool (unless `--no-configs`).

```bash
vendor/bin/preflight install            # interactive — previews, then asks
vendor/bin/preflight install --dry-run  # show the plan, change nothing
vendor/bin/preflight install --yes      # non-interactive (CI / agents)
```

A preview looks like:

```
  + laravel/pint:^1
  + squizlabs/php_codesniffer:^4
  + phpstan/phpstan:^2
  + rector/rector:^2
  + vimeo/psalm:^6
  + phpunit/phpunit:^11
  · phpmd/phpmd — skipped — needs opt-in (see caveat)
```

`composer-audit` never appears — it ships with Composer, so there's nothing to install.

## The packages and versions it installs

These are the constraints Preflight recommends (one per default step):

| Step | Package | Constraint |
|---|---|---|
| `pint` | `laravel/pint` | `^1` |
| `phpcs` | `squizlabs/php_codesniffer` | `^4` |
| `phpstan` | `phpstan/phpstan` | `^2` |
| `rector` | `rector/rector` | `^2` |
| `psalm` | `vimeo/psalm` | `^6` |
| `phpmd` | `phpmd/phpmd` | `^3@dev` (opt-in — see below) |
| `test` | `phpunit/phpunit` `^11` **or** `pestphp/pest` `^3` | your choice |

## Decisions it surfaces (never silent)

Anything that needs a real choice is shown and asked, not assumed:

### PHPMD — opt-in

PHPMD has no stable 3.x release yet, and 2.x can't parse PHP 8.4+. The 3.x dev branch
requires setting `minimum-stability: dev` in your `composer.json` — an invasive change — so
PHPMD is **never installed unless you opt in**:

```bash
vendor/bin/preflight install --with-phpmd   # installs phpmd/phpmd:^3@dev + sets min-stability dev
```

Interactively, you'll see the caveat (with a link) and a yes/no prompt. Without opting in,
PHPMD is skipped and everything else still installs.

### Test runner

The `test` step works with PHPUnit, Pest, or Paratest. When none is installed, choose one:

```bash
vendor/bin/preflight install --runner=phpunit   # default
vendor/bin/preflight install --runner=pest
vendor/bin/preflight install --runner=none      # install no runner
```

Interactively you're prompted to pick. (Paratest is auto-used at run time if present — you
don't install it through here.)

## Config scaffolding

For each tool it installs, `install` writes a sensible starter config (skip with
`--no-configs`; overwrite existing files with `--force`):

| Tool | Scaffolds | Notes |
|---|---|---|
| Pint | `pint.json` | `laravel` preset |
| PHP_CodeSniffer | `phpcs.xml` | PSR-12 + your source dirs |
| PHPStan | `phpstan.neon` | level 5 + your source dirs |
| Rector | `rector.php` | `withPhpSets()` over your source dirs, skipping the PHP 8.5 property-`#[\Override]` rule (Psalm rejects it) |
| PHPMD | `phpmd.xml` | a starter ruleset |
| Psalm | — | delegated to `psalm --init` (it scans and picks a baseline) |

Source dirs are detected from `app`, `src`, and `tests` (whichever exist), so the scaffolded
configs point at real paths rather than guesses. Existing config files are never overwritten
unless you pass `--force`.

## Flags

| Flag | Effect |
|---|---|
| `--dry-run` | Show the plan; change nothing. |
| `--yes`, `-y` | Skip the confirmation prompt (CI / agents). |
| `--runner=<r>` | Test runner to install: `phpunit`, `pest`, or `none`. |
| `--with-phpmd` | Opt into PHPMD's dev branch (sets `minimum-stability: dev`). |
| `--no-configs` | Install packages only; scaffold no config files. |
| `--force` | Overwrite existing config files when scaffolding. |

In non-interactive use without `--yes`, `install` prints the plan and a hint, but makes no
changes — so it's safe to run blind.

## After installing

`vendor/bin/preflight doctor` shows what's installed, what config was found, and what would
run. If anything's still missing it points you back here.

## Opt-in tools aren't auto-installed

[Optional steps](steps.md#optional-built-in-steps) like `composer-normalize` and `deptrac`
aren't part of `install` — you add them deliberately. The reason is the
[no-silent-mutation rule](configuration.md): `composer-normalize` is a Composer **plugin**,
and allow-listing a plugin grants it permission to run code during every `composer install`.
That's a security decision you should make yourself:

```bash
composer require --dev ergebnis/composer-normalize
composer require --dev deptrac/deptrac
```
