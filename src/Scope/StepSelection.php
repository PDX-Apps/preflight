<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Scope;

/**
 * The parsed `--only` / `--skip` step selection from the CLI: each a list of step names.
 *
 * They are mutually exclusive — {@see fromCli()} rejects both at once — and the names are
 * validated against the resolved set later, in {@see \PdxApps\Preflight\Config\Configuration::resolveSteps()},
 * where an unknown name is a hard error with the valid names listed.
 */
final readonly class StepSelection
{
    /**
     * @param  list<string>  $only step names to keep, dropping all others
     * @param  list<string>  $skip step names to drop
     */
    public function __construct(
        public array $only = [],
        public array $skip = [],
    ) {
    }

    /**
     * Build from the raw flag values (comma-separated, possibly null).
     *
     * @throws \InvalidArgumentException when both `--only` and `--skip` are given
     */
    public static function fromCli(mixed $only, mixed $skip): self
    {
        $selection = new self(self::names($only), self::names($skip));

        if ($selection->only !== [] && $selection->skip !== []) {
            throw new \InvalidArgumentException('Use either --only or --skip, not both.');
        }

        return $selection;
    }

    public function isEmpty(): bool
    {
        return $this->only === [] && $this->skip === [];
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        return is_string($value)
            ? array_values(array_filter(array_map(trim(...), explode(',', $value))))
            : [];
    }
}
