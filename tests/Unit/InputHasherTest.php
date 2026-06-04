<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Support\InputHasher;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InputHasher::class)]
final class InputHasherTest extends TestCase
{
    public function test_it_produces_a_stable_hash_for_unchanged_inputs(): void
    {
        $project = new TempProject();
        $project->file('src/A.php', '<?php // a');
        $project->file('composer.lock', '{"x":1}');

        $hasher = new InputHasher($project->root);
        $first = $hasher->hash(['src/A.php'], ['composer.lock']);
        $second = $hasher->hash(['src/A.php'], ['composer.lock']);

        $this->assertNotSame('', $first);
        $this->assertSame($first, $second, 'same inputs -> same hash');
    }

    public function test_changing_a_files_contents_changes_the_hash(): void
    {
        $project = new TempProject();
        $project->file('src/A.php', '<?php // a');
        $hasher = new InputHasher($project->root);

        $before = $hasher->hash(['src/A.php'], []);
        $project->file('src/A.php', '<?php // a CHANGED');
        $after = $hasher->hash(['src/A.php'], []);

        $this->assertNotSame($before, $after);
    }

    public function test_changing_a_config_file_changes_the_hash(): void
    {
        $project = new TempProject();
        $project->file('src/A.php', '<?php // a');
        $project->file('phpstan.neon', "parameters:\n    level: 5\n");
        $hasher = new InputHasher($project->root);

        $before = $hasher->hash(['src/A.php'], ['phpstan.neon']);
        $project->file('phpstan.neon', "parameters:\n    level: 9\n");
        $after = $hasher->hash(['src/A.php'], ['phpstan.neon']);

        $this->assertNotSame($before, $after, 'a ruleset change busts the hash');
    }

    public function test_the_hash_is_independent_of_input_order(): void
    {
        $project = new TempProject();
        $project->file('src/A.php', '<?php // a');
        $project->file('src/B.php', '<?php // b');
        $hasher = new InputHasher($project->root);

        $this->assertSame(
            $hasher->hash(['src/A.php', 'src/B.php'], []),
            $hasher->hash(['src/B.php', 'src/A.php'], []),
        );
    }

    public function test_a_missing_file_is_distinct_from_an_empty_one(): void
    {
        $project = new TempProject();
        $hasher = new InputHasher($project->root);

        $missing = $hasher->hash(['src/Gone.php'], []);
        $project->file('src/Gone.php', '');
        $empty = $hasher->hash(['src/Gone.php'], []);

        $this->assertNotSame($missing, $empty, 'creating a file changes the hash');
    }

    public function test_adding_a_file_to_the_scope_changes_the_hash(): void
    {
        $project = new TempProject();
        $project->file('src/A.php', '<?php // a');
        $project->file('src/B.php', '<?php // b');
        $hasher = new InputHasher($project->root);

        $this->assertNotSame(
            $hasher->hash(['src/A.php'], []),
            $hasher->hash(['src/A.php', 'src/B.php'], []),
        );
    }
}
