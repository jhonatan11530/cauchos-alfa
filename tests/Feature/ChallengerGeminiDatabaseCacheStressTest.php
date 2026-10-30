<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\GeminiPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Challenger Verification & Stress Test Harness
 *
 * Executed by teamwork_challenger to empirically verify:
 * 1. Zero-query cache guarantee under 100+ consecutive stress calls.
 * 2. Raw database mutations bypassing Eloquent detected by refreshIfChanged().
 * 3. Exact TTL boundary conditions ($ttl - 1 vs $ttl + 1 seconds).
 * 4. SHA-256 hash detection resilience even when updated_at is frozen.
 * 5. Raw SQL record deletion handling and fallback resilience.
 */
class ChallengerGeminiDatabaseCacheStressTest extends TestCase
{
    use RefreshDatabase;

    protected const CANONICAL_TEST_PROMPT = 'Prompt canónico de prueba para verificación empírica de GeminiPromptService con acentos: á, é, í, ó, ú, ñ y alineación ortogonal a 0 grados.';

    protected function setUp(): void
    {
        parent::setUp();

        GeminiPromptService::clearCache();
        Cache::flush();
        GeminiPromptService::setTtl(3600);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        GeminiPromptService::clearCache();
        Cache::flush();
        GeminiPromptService::setTtl(3600);

        parent::tearDown();
    }

    /**
     * EMPIRICAL CHALLENGE 1: Cache Zero-Query Stress Test across 150+ calls.
     */
    public function test_stress_probe_150_consecutive_calls_with_query_log_records_zero_sql_queries(): void
    {
        // 1. Persist initial prompt
        Setting::set('PROMPT_GEMINI', self::CANONICAL_TEST_PROMPT, 'Initial Test Prompt');

        // Warm up cache (1 cold query expected)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $initial = GeminiPromptService::getPrompt();
        $this->assertSame(self::CANONICAL_TEST_PROMPT, $initial);

        $warmQueries = DB::getQueryLog();
        $this->assertCount(1, $warmQueries, 'Cold cache retrieval must execute exactly 1 SQL query to load setting');
        $this->assertStringContainsString('settings', strtolower($warmQueries[0]['query']));

        // 2. Stress probe: 150 consecutive calls with active query log
        DB::flushQueryLog();
        $startTime = microtime(true);

        for ($i = 1; $i <= 150; $i++) {
            $prompt = GeminiPromptService::getPrompt();
            $this->assertSame(self::CANONICAL_TEST_PROMPT, $prompt, "Iteration {$i} must return exact canonical prompt");
        }

        $durationMs = (microtime(true) - $startTime) * 1000;
        $stressQueries = DB::getQueryLog();

        // 3. Assert zero queries
        $this->assertCount(0, $stressQueries, 'Zero SQL queries must be dispatched across 150 consecutive cache hits');
        $this->assertLessThan(100.0, $durationMs, '150 in-memory/cache hits should execute in under 100ms');
    }

    /**
     * EMPIRICAL CHALLENGE 2A: Direct Raw SQL Mutation Detection (bypassing Eloquent).
     */
    public function test_adversarial_raw_sql_mutation_bypassing_eloquent_is_detected_by_refresh_if_changed(): void
    {
        // Persist initial record and warm cache
        Setting::set('PROMPT_GEMINI', 'PROMPT_VERSION_A', 'Version A');
        $cachedA = GeminiPromptService::getPrompt();
        $this->assertSame('PROMPT_VERSION_A', $cachedA);

        // Verify refreshIfChanged() is a no-op when DB hasn't changed
        $changedBefore = GeminiPromptService::refreshIfChanged();
        $this->assertFalse($changedBefore, 'refreshIfChanged() must return false when DB matches cached hash');

        // Execute direct raw SQL update bypassing Eloquent model events
        $rawUpdated = DB::table('settings')
            ->where('key', 'PROMPT_GEMINI')
            ->update([
                'value' => 'ADVERSARIAL_RAW_SQL_MUTATION_PROMPT_VERSION_B',
                'updated_at' => now(),
            ]);
        $this->assertSame(1, $rawUpdated, 'Raw SQL update must affect 1 record');

        // Without refreshIfChanged and within TTL, cache still holds Version A
        $unrefreshed = GeminiPromptService::getPrompt();
        $this->assertSame('PROMPT_VERSION_A', $unrefreshed);

        // Execute refreshIfChanged()
        $changedAfter = GeminiPromptService::refreshIfChanged();
        $this->assertTrue($changedAfter, 'refreshIfChanged() must detect raw SQL mutation and return true');

        // Verify prompt is now Version B
        $refreshedPrompt = GeminiPromptService::getPrompt();
        $this->assertSame('ADVERSARIAL_RAW_SQL_MUTATION_PROMPT_VERSION_B', $refreshedPrompt);

        // Verify payload in Cache
        $cachedPayload = Cache::get(GeminiPromptService::CACHE_KEY);
        $this->assertIsArray($cachedPayload);
        $this->assertSame('ADVERSARIAL_RAW_SQL_MUTATION_PROMPT_VERSION_B', $cachedPayload['value']);
        $this->assertSame(hash('sha256', 'ADVERSARIAL_RAW_SQL_MUTATION_PROMPT_VERSION_B'), $cachedPayload['hash']);
    }

    /**
     * EMPIRICAL CHALLENGE 2B: TTL Boundary Conditions ($ttl - 1 vs $ttl + 1 seconds).
     */
    public function test_ttl_boundary_conditions_ttl_minus_1_vs_ttl_plus_1(): void
    {
        // Use a 60 second TTL for exact boundary testing
        GeminiPromptService::setTtl(60);
        $this->assertSame(60, GeminiPromptService::getTtl());

        Setting::set('PROMPT_GEMINI', 'TTL_BOUNDARY_PROMPT_ORIGINAL', 'TTL Original');
        $this->assertSame('TTL_BOUNDARY_PROMPT_ORIGINAL', GeminiPromptService::getPrompt());

        // Perform raw SQL mutation bypassing Eloquent
        DB::table('settings')
            ->where('key', 'PROMPT_GEMINI')
            ->update([
                'value' => 'TTL_BOUNDARY_PROMPT_MUTATED',
                'updated_at' => now(),
            ]);

        // Boundary Point 1: $ttl - 1 second (59 seconds)
        $this->travel(59)->seconds();

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Must still be served from cache with 0 SQL queries
        $promptAt59 = GeminiPromptService::getPrompt();
        $queriesAt59 = DB::getQueryLog();

        $this->assertSame('TTL_BOUNDARY_PROMPT_ORIGINAL', $promptAt59, 'At TTL - 1 second, cache must still be valid');
        $this->assertCount(0, $queriesAt59, 'At TTL - 1 second, zero SQL queries should be dispatched');

        // Boundary Point 2: $ttl + 1 second (61 seconds from origin, which is +2 seconds from point 1)
        $this->travel(2)->seconds(); // Now at 61 seconds from start

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Cache expired: must query DB and retrieve mutated prompt
        $promptAt61 = GeminiPromptService::getPrompt();
        $queriesAt61 = DB::getQueryLog();

        $this->assertSame('TTL_BOUNDARY_PROMPT_MUTATED', $promptAt61, 'At TTL + 1 second, cache must expire and reload mutated value');
        $this->assertGreaterThanOrEqual(1, count($queriesAt61), 'At TTL + 1 second, database query must be dispatched to refresh cache');

        // Subsequent call at 62 seconds must be cached again (0 queries)
        DB::flushQueryLog();
        $promptAt62 = GeminiPromptService::getPrompt();
        $this->assertSame('TTL_BOUNDARY_PROMPT_MUTATED', $promptAt62);
        $this->assertCount(0, DB::getQueryLog(), 'Subsequent read after reload must be served from cache with 0 queries');
    }

    /**
     * EMPIRICAL CHALLENGE 2C: Raw SQL Hash Mutation with Frozen Timestamp.
     */
    public function test_adversarial_raw_sql_hash_mutation_with_frozen_timestamp(): void
    {
        $frozenTime = now()->subDays(30);

        DB::table('settings')->where('key', 'PROMPT_GEMINI')->update([
            'value' => 'FROZEN_TIME_INITIAL_VALUE',
            'created_at' => $frozenTime,
            'updated_at' => $frozenTime,
        ]);

        $initial = GeminiPromptService::getPrompt();
        $this->assertSame('FROZEN_TIME_INITIAL_VALUE', $initial);

        // Mutate value via raw SQL but intentionally leave updated_at frozen in the past
        DB::table('settings')
            ->where('key', 'PROMPT_GEMINI')
            ->update([
                'value' => 'FROZEN_TIME_MUTATED_VALUE',
                'updated_at' => $frozenTime,
            ]);

        // refreshIfChanged must detect change via SHA-256 hash comparison despite frozen timestamp
        $detected = GeminiPromptService::refreshIfChanged();
        $this->assertTrue($detected, 'refreshIfChanged() must detect change by SHA-256 hash even if updated_at is unchanged');

        $this->assertSame('FROZEN_TIME_MUTATED_VALUE', GeminiPromptService::getPrompt());
    }

    /**
     * EMPIRICAL CHALLENGE 2D: Raw SQL Row Deletion Bypassing Eloquent.
     */
    public function test_adversarial_raw_sql_row_deletion_bypassing_eloquent(): void
    {
        Setting::set('PROMPT_GEMINI', 'DELETION_TEST_PROMPT');
        $this->assertSame('DELETION_TEST_PROMPT', GeminiPromptService::getPrompt());

        // Raw SQL delete bypassing Eloquent model events
        DB::table('settings')->where('key', 'PROMPT_GEMINI')->delete();

        // refreshIfChanged must detect that DB record is gone, clear cache, and return true
        $detected = GeminiPromptService::refreshIfChanged();
        $this->assertTrue($detected, 'refreshIfChanged() must detect row deletion and clear cache');

        // Subsequent getPrompt() must gracefully fall back to canonical fallback without throwing
        $fallback = GeminiPromptService::getPrompt();
        $this->assertSame(GeminiPromptService::CANONICAL_FALLBACK_PROMPT, $fallback);
    }
}
