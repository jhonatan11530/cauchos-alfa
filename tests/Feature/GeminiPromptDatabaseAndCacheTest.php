<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\GeminiPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

/**
 * E2E Feature Test Suite: PROMPT_GEMINI Database Migration & Auto-Invalidating Cache
 *
 * Requirements Covered:
 * - R1: Database Storage for PROMPT_GEMINI (Setting model / settings table) & .env independence
 * - R2: Zero-DB-Hit Cache Service, TTL Invalidation, and Automatic Change Detection
 * - R3: View & Application Integration, UTF-8 Multibyte Fidelity, Fallback & Resilience
 *
 * Interface Contracts Verified:
 * - App\Models\Setting::get(string $key, ?string $default = null): ?string
 * - App\Models\Setting::set(string $key, ?string $value, ?string $description = null): Setting
 * - App\Services\GeminiPromptService::getPrompt(): string
 * - App\Services\GeminiPromptService::get(string $key = 'PROMPT_GEMINI'): string
 * - App\Services\GeminiPromptService::refreshIfChanged(): bool
 * - App\Services\GeminiPromptService::clearCache(): void
 * - App\Services\GeminiPromptService::setTtl(int $seconds): void
 * - App\Services\GeminiPromptService::getTtl(): int
 */
class GeminiPromptDatabaseAndCacheTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    protected const CANONICAL_PROMPT = 'Mejora la imagen aplicando un estilo de fotografía de estudio en primer plano y de alta calidad sobre un fondo blanco puro y continuo, utilizando iluminación natural con enfoque nítido y sombras suaves en la base para asentar el objeto de forma realista. Es indispensable lograr una alineación geométrica absoluta: el producto debe quedar perfectamente recto, nivelado y con 0 grados de inclinación de extremo a extremo, respetando su orientación natural (estrictamente horizontal o vertical) y quedando totalmente paralelo a los bordes del encuadre, sin ladeos, desniveles ni caídas accidentales hacia ningún lado. Asimismo, aplica una corrección de perspectiva rigurosa para obtener una vista frontal plana y ortogonal, eliminando por completo cualquier fuga diagonal, distorsión de lente o ángulo de cámara que haga que una parte se vea más cercana o distorsionada, asegurando una simetría y estabilidad visual perfectas. Finalmente, conserva con absoluta fidelidad la forma, proporciones, materiales y texturas originales del objeto, centrándolo en la composición con un espacio blanco equilibrado alrededor, sin marcas de agua ni elementos adicionales, para entregar un acabado hiperrealista, nítido y profesional ideal para un catálogo online.';

    private ?string $originalEnvPrompt = null;
    private ?string $originalConfigPrompt = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnvPrompt = getenv('PROMPT_GEMINI') !== false ? getenv('PROMPT_GEMINI') : null;
        $this->originalConfigPrompt = config('services.gemini.prompt');

        if (class_exists(GeminiPromptService::class)) {
            GeminiPromptService::clearCache();
        }
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        if ($this->originalEnvPrompt !== null) {
            putenv("PROMPT_GEMINI={$this->originalEnvPrompt}");
            $_ENV['PROMPT_GEMINI'] = $this->originalEnvPrompt;
            $_SERVER['PROMPT_GEMINI'] = $this->originalEnvPrompt;
        } else {
            putenv('PROMPT_GEMINI');
            unset($_ENV['PROMPT_GEMINI'], $_SERVER['PROMPT_GEMINI']);
        }

        config(['services.gemini.prompt' => $this->originalConfigPrompt]);

        if (class_exists(GeminiPromptService::class)) {
            GeminiPromptService::clearCache();
        }
        Cache::flush();

        parent::tearDown();
    }

    /**
     * Helper to persist a prompt record to settings table using Setting model or DB query.
     */
    private function persistPromptToDatabase(string $prompt, ?string $description = null): void
    {
        if (class_exists(Setting::class)) {
            Setting::set('PROMPT_GEMINI', $prompt, $description ?? 'Gemini Studio photography prompt');
        } else {
            DB::table('settings')->updateOrInsert(
                ['key' => 'PROMPT_GEMINI'],
                [
                    'value' => $prompt,
                    'description' => $description ?? 'Gemini Studio photography prompt',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Helper to determine the product creation route name.
     */
    private function getProductCreateRoute(): string
    {
        if (Route::has('admin.products.create')) {
            return route('admin.products.create');
        }
        if (Route::has('productos.create')) {
            return route('productos.create');
        }
        return url('/productos/create');
    }

    // =========================================================================
    // 1. Initial DB Retrieval & Cache Population (R1 & Acceptance Criteria)
    // =========================================================================

    /**
     * Requirement: Cold cache loads prompt from database and immediately populates cache.
     */
    public function test_cold_cache_retrieves_prompt_from_database_and_populates_cache(): void
    {
        $testPrompt = 'Prompt inicial almacenado en base de datos para verificación de carga en frío.';
        $this->persistPromptToDatabase($testPrompt);

        GeminiPromptService::clearCache();
        Cache::flush();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $retrieved = GeminiPromptService::getPrompt();

        $queries = DB::getQueryLog();
        $this->assertNotEmpty($queries, 'Expected at least one database query on cold cache retrieval.');
        $this->assertSame($testPrompt, $retrieved, 'Returned prompt must match the database record exactly.');

        // Verify that cache is now populated by asserting the very next retrieval does not query DB
        DB::flushQueryLog();
        $cachedRetrieved = GeminiPromptService::getPrompt();
        $this->assertSame($testPrompt, $cachedRetrieved);
        $this->assertEmpty(DB::getQueryLog(), 'Cache was expected to be populated, but database was queried again.');
    }

    /**
     * Requirement: Eloquent Setting model get() and set() accessors contract.
     */
    public function test_setting_model_accessors_get_and_set(): void
    {
        $prompt = 'Prompt registrado mediante el modelo Eloquent Setting.';
        $description = 'Configuración principal para Gemini Studio';

        $setting = Setting::set('PROMPT_GEMINI', $prompt, $description);

        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertDatabaseHas('settings', [
            'key' => 'PROMPT_GEMINI',
            'value' => $prompt,
        ]);

        $retrieved = Setting::get('PROMPT_GEMINI');
        $this->assertSame($prompt, $retrieved);

        $fallback = Setting::get('NON_EXISTENT_SETTING_KEY', 'default_fallback_value');
        $this->assertSame('default_fallback_value', $fallback);
    }

    // =========================================================================
    // 2. Zero-DB-Hit Cache Service (R2 & Acceptance Criteria)
    // =========================================================================

    /**
     * Requirement: Subsequent retrievals within TTL must be served 100% from cache with 0 DB queries.
     */
    public function test_subsequent_prompt_retrievals_within_ttl_served_with_zero_database_queries(): void
    {
        $testPrompt = 'Prompt para prueba de cero consultas SQL dentro del periodo TTL.';
        $this->persistPromptToDatabase($testPrompt);

        // Warm-up cache
        $initial = GeminiPromptService::getPrompt();
        $this->assertSame($testPrompt, $initial);

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Perform 10 consecutive reads
        for ($i = 1; $i <= 10; $i++) {
            $result = GeminiPromptService::getPrompt();
            $this->assertSame($testPrompt, $result, "Mismatch on iteration {$i}");
        }

        $queries = DB::getQueryLog();
        $this->assertEmpty(
            $queries,
            'Subsequent prompt retrievals within TTL must execute ZERO database queries. Found: ' . json_encode($queries)
        );
    }

    /**
     * Requirement: In-memory request memoization (Tier 1) provides consistent instant responses.
     */
    public function test_concurrent_or_repetitive_in_memory_tier1_memoization(): void
    {
        $prompt = 'Prompt memoizado en memoria para alta concurrencia de petición.';
        $this->persistPromptToDatabase($prompt);

        $first = GeminiPromptService::getPrompt();
        $this->assertSame($prompt, $first);

        for ($i = 0; $i < 50; $i++) {
            $this->assertSame($prompt, GeminiPromptService::getPrompt());
        }
    }

    // =========================================================================
    // 3. Automatic Change Detection on Direct DB Modification (R2 & Acceptance Criteria)
    // =========================================================================

    /**
     * Requirement: Direct DB update via SQL is automatically detected upon TTL expiration and updates cache.
     */
    public function test_automatic_change_detection_after_ttl_expiration_with_direct_db_update(): void
    {
        $initialPrompt = 'Prompt inicial antes de modificación directa en SQL.';
        $updatedPrompt = 'Prompt actualizado directamente en SQL sin intervención de la aplicación.';

        $this->persistPromptToDatabase($initialPrompt);

        // Warm cache
        $this->assertSame($initialPrompt, GeminiPromptService::getPrompt());

        // Direct DB update bypassing Eloquent
        DB::table('settings')->where('key', 'PROMPT_GEMINI')->update([
            'value' => $updatedPrompt,
            'updated_at' => now()->addSeconds(60),
        ]);

        // Before TTL expiration, cached value remains served
        $this->assertSame($initialPrompt, GeminiPromptService::getPrompt());

        // Advance time beyond TTL (standard 3600 seconds + 1)
        $ttl = method_exists(GeminiPromptService::class, 'getTtl') ? GeminiPromptService::getTtl() : 3600;
        $this->travel($ttl + 1)->seconds();

        // After TTL expires, service revalidates/refreshes automatically from DB
        $refreshedPrompt = GeminiPromptService::getPrompt();
        $this->assertSame($updatedPrompt, $refreshedPrompt, 'Prompt after TTL expiration must reflect the direct DB update.');

        // Assert subsequent read is also served from new cache with zero DB queries
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($updatedPrompt, GeminiPromptService::getPrompt());
        $this->assertEmpty(DB::getQueryLog(), 'Subsequent read after auto-refresh must be served from cache with 0 queries.');
    }

    /**
     * Requirement: Explicit revalidation via refreshIfChanged() detects changes and returns bool status.
     */
    public function test_explicit_revalidation_via_refresh_if_changed(): void
    {
        $prompt1 = 'Prompt base versión 1.';
        $prompt2 = 'Prompt base versión 2 modificado externamente.';

        $this->persistPromptToDatabase($prompt1);
        $this->assertSame($prompt1, GeminiPromptService::getPrompt());

        // Revalidation without changes should return false
        $changed = GeminiPromptService::refreshIfChanged();
        $this->assertFalse($changed, 'refreshIfChanged() should return false when DB has not changed.');

        // Directly update DB record
        DB::table('settings')->where('key', 'PROMPT_GEMINI')->update([
            'value' => $prompt2,
            'updated_at' => now()->addMinutes(5),
        ]);

        // Explicit revalidation detects change, updates cache, and returns true
        $changedAfterUpdate = GeminiPromptService::refreshIfChanged();
        $this->assertTrue($changedAfterUpdate, 'refreshIfChanged() should return true when DB record was modified.');

        // Next getPrompt returns the new prompt immediately
        $this->assertSame($prompt2, GeminiPromptService::getPrompt());

        // Subsequent revalidation without further changes returns false
        $this->assertFalse(GeminiPromptService::refreshIfChanged(), 'Second refreshIfChanged() without DB change should return false.');
    }

    /**
     * Requirement: Direct DB modification altering ONLY value (with identical timestamp) is detected by hash.
     */
    public function test_direct_db_modification_detected_via_hash_change_even_if_timestamp_unchanged(): void
    {
        $timestamp = now()->subHour();
        DB::table('settings')->updateOrInsert(
            ['key' => 'PROMPT_GEMINI'],
            [
                'value' => 'Prompt con timestamp fijo inicial.',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]
        );

        $this->assertSame('Prompt con timestamp fijo inicial.', GeminiPromptService::getPrompt());

        // Raw SQL update altering ONLY value, keeping updated_at strictly identical
        DB::table('settings')->where('key', 'PROMPT_GEMINI')->update([
            'value' => 'Prompt modificado manteniendo exactamente el mismo timestamp.',
        ]);

        $changed = GeminiPromptService::refreshIfChanged();
        $this->assertTrue($changed, 'Hash check must detect content changes even when updated_at is unchanged.');
        $this->assertSame(
            'Prompt modificado manteniendo exactamente el mismo timestamp.',
            GeminiPromptService::getPrompt()
        );
    }

    // =========================================================================
    // 4. Total Independence from .env (R1 & Acceptance Criteria)
    // =========================================================================

    /**
     * Requirement: Unsetting PROMPT_GEMINI from .env/config does not break prompt retrieval.
     */
    public function test_total_independence_from_env_variable(): void
    {
        $dbPrompt = 'Prompt completamente independiente del archivo .env alojado en base de datos.';
        $this->persistPromptToDatabase($dbPrompt);

        // Purge PROMPT_GEMINI from all environment vectors
        putenv('PROMPT_GEMINI');
        unset($_ENV['PROMPT_GEMINI']);
        unset($_SERVER['PROMPT_GEMINI']);
        config(['services.gemini.prompt' => null]);

        GeminiPromptService::clearCache();

        $retrieved = GeminiPromptService::getPrompt();

        $this->assertNotEmpty($retrieved, 'Prompt retrieval must not fail or return empty when .env is absent.');
        $this->assertSame($dbPrompt, $retrieved, 'Prompt must be resolved from database when .env variable is not set.');
    }

    /**
     * Requirement: Database record always takes precedence over .env variable.
     */
    public function test_database_record_takes_precedence_over_env_variable(): void
    {
        $dbPrompt = 'Prompt legítimo de Base de Datos que prevalece.';
        $this->persistPromptToDatabase($dbPrompt);

        // Force legacy .env value
        $envPrompt = 'Prompt obsoleto de archivo .env que debe ser ignorado.';
        putenv("PROMPT_GEMINI={$envPrompt}");
        $_ENV['PROMPT_GEMINI'] = $envPrompt;
        $_SERVER['PROMPT_GEMINI'] = $envPrompt;
        config(['services.gemini.prompt' => $envPrompt]);

        GeminiPromptService::clearCache();

        $retrieved = GeminiPromptService::getPrompt();
        $this->assertSame($dbPrompt, $retrieved, 'Database record must always override .env variable.');
        $this->assertNotSame($envPrompt, $retrieved);
    }

    // =========================================================================
    // 5. Edge Cases & Resilience (Tiers 2-4)
    // =========================================================================

    /**
     * Requirement: Multibyte UTF-8 Spanish accents and symbols are preserved without corruption.
     */
    public function test_multibyte_utf8_spanish_accent_preservation(): void
    {
        $this->persistPromptToDatabase(self::CANONICAL_PROMPT);
        GeminiPromptService::clearCache();

        $retrieved = GeminiPromptService::getPrompt();

        $this->assertTrue(mb_check_encoding($retrieved, 'UTF-8'), 'Retrieved prompt must be valid UTF-8.');
        $this->assertSame(self::CANONICAL_PROMPT, $retrieved, 'Verbatim character equality required.');
        $this->assertSame(
            mb_strlen(self::CANONICAL_PROMPT, 'UTF-8'),
            mb_strlen($retrieved, 'UTF-8'),
            'UTF-8 character length mismatch.'
        );
        $this->assertSame(
            strlen(self::CANONICAL_PROMPT),
            strlen($retrieved),
            'Byte length mismatch.'
        );
        $this->assertSame(
            hash('sha256', self::CANONICAL_PROMPT),
            hash('sha256', $retrieved),
            'SHA-256 hash mismatch.'
        );

        // Specific Spanish accents verification
        $this->assertStringContainsString('fotografía de estudio', mb_strtolower($retrieved, 'UTF-8'));
        $this->assertStringContainsString('orientación', $retrieved);
        $this->assertStringContainsString('inclinación', $retrieved);
        $this->assertStringContainsString('catálogo', $retrieved);
        $this->assertStringContainsString('nítido', $retrieved);
    }

    /**
     * Requirement: Model saved event immediately purges/refreshes cache without waiting for TTL.
     */
    public function test_model_event_immediately_invalidates_cache_on_save(): void
    {
        $initialPrompt = 'Prompt inicial para prueba de evento de modelo.';
        $updatedPrompt = 'Prompt actualizado via Setting::set() con invalidación inmediata.';

        Setting::set('PROMPT_GEMINI', $initialPrompt);
        $this->assertSame($initialPrompt, GeminiPromptService::getPrompt());

        // Verify it is cached (0 queries)
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($initialPrompt, GeminiPromptService::getPrompt());
        $this->assertEmpty(DB::getQueryLog());

        // Update via Eloquent Model set()
        Setting::set('PROMPT_GEMINI', $updatedPrompt);

        // Immediate retrieval without advancing time must return the new prompt
        $retrieved = GeminiPromptService::getPrompt();
        $this->assertSame($updatedPrompt, $retrieved, 'Model save event must immediately refresh or purge cache.');
    }

    /**
     * Requirement: Model deleted event invalidates cache on record removal.
     */
    public function test_model_event_invalidates_cache_on_deletion(): void
    {
        $prompt = 'Prompt que será eliminado mediante Eloquent.';
        Setting::set('PROMPT_GEMINI', $prompt);
        $this->assertSame($prompt, GeminiPromptService::getPrompt());

        // Delete record via Eloquent model
        $setting = Setting::where('key', 'PROMPT_GEMINI')->first();
        $this->assertNotNull($setting);
        $setting->delete();

        // Calling getPrompt should not return stale deleted prompt
        $fallback = GeminiPromptService::getPrompt();
        $this->assertNotSame($prompt, $fallback, 'Cache must be invalidated when setting record is deleted.');
    }

    /**
     * Requirement: Graceful fallback when settings table has no PROMPT_GEMINI record.
     */
    public function test_graceful_fallback_when_db_record_is_missing_or_table_empty(): void
    {
        DB::table('settings')->truncate();
        GeminiPromptService::clearCache();

        // Must not throw fatal unhandled exception
        $retrieved = GeminiPromptService::getPrompt();
        $this->assertIsString($retrieved);

        // Setting model returns default
        $modelDefault = Setting::get('PROMPT_GEMINI', 'fallback_default');
        $this->assertSame('fallback_default', $modelDefault);
    }

    /**
     * Requirement: GeminiPromptService::clearCache() empties both tier 1 and tier 2 cache.
     */
    public function test_cache_clearing_forces_database_reload(): void
    {
        $prompt = 'Prompt para prueba de limpieza explícita de caché.';
        $this->persistPromptToDatabase($prompt);

        // Warm cache
        $this->assertSame($prompt, GeminiPromptService::getPrompt());

        // Explicit cache clear
        GeminiPromptService::clearCache();

        // Next getPrompt must hit database
        DB::enableQueryLog();
        DB::flushQueryLog();

        $retrieved = GeminiPromptService::getPrompt();
        $this->assertSame($prompt, $retrieved);

        $this->assertNotEmpty(DB::getQueryLog(), 'DB must be queried after GeminiPromptService::clearCache().');
    }

    /**
     * Requirement: Custom TTL configuration is respected.
     */
    public function test_custom_ttl_configuration_respected(): void
    {
        $prompt1 = 'Prompt con TTL personalizado inicial.';
        $prompt2 = 'Prompt con TTL personalizado actualizado.';

        $this->persistPromptToDatabase($prompt1);
        GeminiPromptService::clearCache();

        // Set custom TTL of 120 seconds
        GeminiPromptService::setTtl(120);

        $this->assertSame($prompt1, GeminiPromptService::getPrompt());

        // Direct DB update
        DB::table('settings')->where('key', 'PROMPT_GEMINI')->update([
            'value' => $prompt2,
            'updated_at' => now()->addMinutes(1),
        ]);

        // Travel 60 seconds (within 120s TTL): should still be prompt1
        $this->travel(60)->seconds();
        $this->assertSame($prompt1, GeminiPromptService::getPrompt());

        // Travel another 65 seconds (total 125s > 120s TTL): should detect and return prompt2
        $this->travel(65)->seconds();
        $this->assertSame($prompt2, GeminiPromptService::getPrompt());
    }

    /**
     * Requirement: Cache corruption self-healing and recovery.
     */
    public function test_cache_corruption_self_healing(): void
    {
        $dbPrompt = 'Prompt en DB para prueba de auto-reparación ante corrupción.';
        $this->persistPromptToDatabase($dbPrompt);

        // Intentionally inject invalid corrupted scalar data into cache key
        Cache::put('setting_prompt_gemini', 'INVALID_SCALAR_CORRUPTED_PAYLOAD', 3600);

        // Service should detect corrupt entry, fallback to DB, and self-heal
        $retrieved = GeminiPromptService::getPrompt();
        $this->assertSame($dbPrompt, $retrieved);

        // Verify cache is healed and next retrieval is served with 0 queries
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($dbPrompt, GeminiPromptService::getPrompt());
        $this->assertEmpty(DB::getQueryLog(), 'Corrupted cache must be repaired to enable zero-query caching.');
    }

    /**
     * Requirement: Special characters, quotes, slashes, and HTML script tags are preserved verbatim.
     */
    public function test_special_characters_quotes_and_script_tags_escaping_integrity(): void
    {
        $adversarialPrompt = 'Prompt con comillas "dobles", \'simples\', slashes \\/ y tags <script>alert("xss")</script> & símbolos: €$@#%*';
        $this->persistPromptToDatabase($adversarialPrompt);
        GeminiPromptService::clearCache();

        $retrieved = GeminiPromptService::getPrompt();
        $this->assertSame($adversarialPrompt, $retrieved, 'Adversarial characters must be preserved verbatim in memory.');

        // Blade rendering integration test with special characters
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->get($this->getProductCreateRoute());
        $response->assertOk();

        // Verify JavaScript function buildGeminiStudioPrompt syntax integrity is not broken
        $content = $response->getContent();
        $this->assertStringContainsString('function buildGeminiStudioPrompt()', $content);
    }

    /**
     * Requirement: Generic Setting::get() and GeminiPromptService::get() support arbitrary keys.
     */
    public function test_generic_get_accessor_supports_arbitrary_keys(): void
    {
        Setting::set('SITE_NAME', 'Cauchos Alfa Producción');
        Setting::set('MAX_UPLOAD_SIZE_MB', '25');

        $this->assertSame('Cauchos Alfa Producción', Setting::get('SITE_NAME'));
        $this->assertSame('25', Setting::get('MAX_UPLOAD_SIZE_MB'));
        $this->assertSame('default_val', Setting::get('NON_EXISTENT_KEY', 'default_val'));

        if (method_exists(GeminiPromptService::class, 'get')) {
            $this->assertSame(
                Setting::get('PROMPT_GEMINI'),
                GeminiPromptService::get('PROMPT_GEMINI')
            );
        }
    }

    // =========================================================================
    // 6. View Rendering Integration Tests
    // =========================================================================

    /**
     * Requirement: Product creation form renders cached DB prompt inside buildGeminiStudioPrompt().
     */
    public function test_product_form_blade_renders_cached_db_prompt_in_build_gemini_studio_prompt(): void
    {
        $customPrompt = 'PROMPT_DE_ESTUDIO_BLADE_INTEGRATION_TEST_UNICODE_ñáéíóú';
        $this->persistPromptToDatabase($customPrompt);
        GeminiPromptService::clearCache();

        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->get($this->getProductCreateRoute());

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('function buildGeminiStudioPrompt()', $content);
        $this->assertStringContainsString($customPrompt, $content, 'Rendered Blade view must contain prompt loaded from database/cache.');
        $this->assertStringContainsString('window.buildGeminiStudioPrompt = buildGeminiStudioPrompt;', $content);
    }

    /**
     * Requirement: Product edit/create form rendering makes zero additional queries to settings table when prompt is cached.
     */
    public function test_product_form_renders_with_zero_additional_db_queries_for_prompt(): void
    {
        $prompt = 'Prompt precalentado para prueba de aislamiento de consultas en formulario.';
        $this->persistPromptToDatabase($prompt);

        // Warm prompt cache
        GeminiPromptService::getPrompt();

        $admin = $this->createAdmin();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->actingAs($admin)->get($this->getProductCreateRoute());
        $response->assertOk();

        $queries = DB::getQueryLog();
        $settingsQueries = array_filter($queries, function ($q) {
            return str_contains(strtolower($q['query']), 'settings');
        });

        $this->assertEmpty(
            $settingsQueries,
            'Form rendering must make 0 queries to settings table when prompt is cached. Found: ' . json_encode($settingsQueries)
        );
    }
}
