<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Tests\Unit;

use PdxApps\Preflight\Report\FreshnessCache;
use PdxApps\Preflight\Support\FrozenClock;
use PdxApps\Preflight\Tests\Support\TempProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FreshnessCache::class)]
final class FreshnessCacheTest extends TestCase
{
    private function cache(TempProject $project): FreshnessCache
    {
        return new FreshnessCache($project->root, FrozenClock::at('2026-01-02T03:04:05+00:00'));
    }

    public function test_a_matching_hash_after_a_passing_run_is_fresh(): void
    {
        $project = new TempProject();
        $cache = $this->cache($project);
        $cache->store('abc123', success: true);

        $this->assertTrue($cache->isFresh('abc123'));
    }

    public function test_a_different_hash_is_not_fresh(): void
    {
        $project = new TempProject();
        $cache = $this->cache($project);
        $cache->store('abc123', success: true);

        $this->assertFalse($cache->isFresh('different'));
    }

    public function test_a_matching_hash_after_a_failing_run_is_not_fresh(): void
    {
        $project = new TempProject();
        $cache = $this->cache($project);
        $cache->store('abc123', success: false);

        $this->assertFalse($cache->isFresh('abc123'), 'a failed run must always re-run');
    }

    public function test_no_cache_file_means_not_fresh(): void
    {
        $this->assertFalse($this->cache(new TempProject())->isFresh('abc123'));
    }

    public function test_corrupt_cache_file_is_treated_as_not_fresh(): void
    {
        $project = new TempProject();
        $project->file('.preflight.cache.json', 'not json{');

        $this->assertFalse($this->cache($project)->isFresh('abc123'));
    }

    public function test_store_writes_the_cache_file_with_hash_success_and_timestamp(): void
    {
        $project = new TempProject();
        $this->cache($project)->store('abc123', success: true);

        $path = $project->root . '/.preflight.cache.json';
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('abc123', $data['hash']);
        $this->assertTrue($data['success']);
        $this->assertSame('2026-01-02T03:04:05+00:00', $data['ranAt']);
    }

    public function test_storing_again_overwrites_the_previous_entry(): void
    {
        $project = new TempProject();
        $cache = $this->cache($project);
        $cache->store('old', success: true);
        $cache->store('new', success: true);

        $this->assertTrue($cache->isFresh('new'));
        $this->assertFalse($cache->isFresh('old'));
    }
}
