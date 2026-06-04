<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Install;

/**
 * Generates starter config-file content for the tools the install command sets up.
 *
 * Content is generated (not copied from static stubs) because the analysers need real source
 * paths: on a whole-project run each tool reads its own config for what to scan, so a
 * scaffolded config must point at directories that actually exist. {@see sourcePaths()}
 * detects them; configs that don't take paths (pint, phpmd) are static.
 */
final readonly class ConfigScaffolder
{
    /** Candidate source directories, in the order they should appear when present. */
    private const array CANDIDATE_DIRS = ['app', 'src', 'tests'];

    public function __construct(private string $projectRoot)
    {
    }

    /**
     * Existing source directories to scan, or `['src']` as a fallback when none are present.
     *
     * @return list<string>
     */
    public function sourcePaths(): array
    {
        $found = array_values(array_filter(
            self::CANDIDATE_DIRS,
            fn (string $dir): bool => is_dir(rtrim($this->projectRoot, '/') . '/' . $dir),
        ));

        return $found !== [] ? $found : ['src'];
    }

    /**
     * Starter content for the given config filename, or null if it isn't one we scaffold.
     */
    public function contentsFor(string $file): ?string
    {
        return match ($file) {
            'phpstan.neon' => $this->phpstanNeon(),
            'phpcs.xml' => $this->phpcsXml(),
            'rector.php' => $this->rectorPhp(),
            'pint.json' => $this->pintJson(),
            'phpmd.xml' => $this->phpmdXml(),
            default => null,
        };
    }

    private function phpstanNeon(): string
    {
        $paths = implode("\n", array_map(static fn (string $p): string => '        - ' . $p, $this->sourcePaths()));

        return "parameters:\n    level: 5\n    paths:\n" . $paths . "\n";
    }

    private function phpcsXml(): string
    {
        $files = implode("\n", array_map(static fn (string $p): string => '    <file>' . $p . '</file>', $this->sourcePaths()));

        return <<<XML
            <?xml version="1.0"?>
            <ruleset name="App">
                <rule ref="PSR12"/>
            {$files}
            </ruleset>

            XML;
    }

    private function rectorPhp(): string
    {
        $paths = implode("\n", array_map(static fn (string $p): string => "        __DIR__ . '/" . $p . "',", $this->sourcePaths()));

        return <<<PHP
            <?php

            declare(strict_types=1);

            use Rector\\Config\\RectorConfig;

            return RectorConfig::configure()
                ->withPaths([
            {$paths}
                ])
                ->withPhpSets();

            PHP;
    }

    private function pintJson(): string
    {
        return <<<'JSON'
            {
                "preset": "laravel"
            }

            JSON;
    }

    private function phpmdXml(): string
    {
        return <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="App"
                xmlns="http://pmd.sf.net/ruleset/1.0.0">
                <rule ref="rulesets/cleancode.xml"/>
                <rule ref="rulesets/codesize.xml"/>
                <rule ref="rulesets/naming.xml"/>
                <rule ref="rulesets/unusedcode.xml"/>
            </ruleset>

            XML;
    }
}
