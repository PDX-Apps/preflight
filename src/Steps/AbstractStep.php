<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Steps;

use PdxApps\Preflight\Context;
use PdxApps\Preflight\Contracts\Step;
use PdxApps\Preflight\Steps\Concerns\DerivesName;

/**
 * Base for built-in and user-authored steps. Each step is its own immutable fluent
 * builder: {@see make()} returns an instance carrying the step's defaults, and every
 * `with`-style method ({@see config()}, {@see before()}, {@see args()}, plus any a
 * subclass adds) returns a configured clone, leaving the original untouched.
 *
 * Because steps are stateless at {@see plan()} (everything contextual comes from the
 * {@see Context}), a configured instance is safe to share and reuse.
 * The name derives from the class via {@see DerivesName}; subclasses override it only when
 * the desired id differs from the class.
 *
 * @phpstan-consistent-constructor Subclasses keep a no-argument constructor, so make()'s
 *   `new static()` is safe.
 */
abstract class AbstractStep implements Step
{
    use DerivesName;

    private ?string $config = null;

    /** @var list<list<string>> */
    private array $before = [];

    /** @var list<string> */
    private array $extraArgs = [];

    /**
     * A fresh instance carrying this step's defaults. The entry point used in a
     * `preflight.php` config (`Phpstan::make()->config('phpstan.neon')`).
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * The tool config file this step looks for by default. Steps with a config file
     * override this (e.g. Pint returns `pint.json`); the rest have none.
     */
    public function defaultConfig(): ?string
    {
        return null;
    }

    /**
     * The effective config reference: an explicit {@see config()} override, else the
     * step's {@see defaultConfig()}.
     */
    public function effectiveConfig(): ?string
    {
        return $this->configReference() ?? $this->defaultConfig();
    }

    /**
     * Override the config file passed to the tool. A bare filename or relative path
     * resolves against the project root; absolute passes through; null lets the tool find
     * its own config.
     */
    public function config(?string $reference): static
    {
        $clone = clone $this;
        $clone->config = $reference;

        return $clone;
    }

    /**
     * Add a command to run before this step's main command (e.g. `php artisan config:clear`).
     * Calls accumulate in order.
     *
     * @param  list<string>  $command
     */
    public function before(array $command): static
    {
        $clone = clone $this;
        $clone->before = [...$this->before, $command];

        return $clone;
    }

    /**
     * Append raw extra arguments to the tool invocation.
     *
     * @param  list<string>  $args
     */
    public function args(array $args): static
    {
        $clone = clone $this;
        $clone->extraArgs = [...$this->extraArgs, ...$args];

        return $clone;
    }

    public function configReference(): ?string
    {
        return $this->config;
    }

    /**
     * @return list<list<string>>
     */
    public function beforeCommands(): array
    {
        return $this->before;
    }

    /**
     * @return list<string>
     */
    public function extraArgs(): array
    {
        return $this->extraArgs;
    }
}
