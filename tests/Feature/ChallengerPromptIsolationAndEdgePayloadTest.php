<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

/**
 * Empirical Challenger Test Suite for PROMPT_GEMINI DB migration & cache.
 * Executed by challenger_prompt_2 to stress test:
 * 1. Strict environment isolation (.env elimination, config clearance)
 * 2. View and JavaScript bridge delivery strictly from DB/cache
 * 3. Multibyte UTF-8 integrity (Spanish accents, tildes, symbols)
 * 4. Adversarial payloads (quotes, HTML/script tags, nested JSON, escapes)
 * 5. Direct database tampering detection and self-healing
 */
class ChallengerPromptIsolationAndEdgePayloadTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    private ?string $origEnv;
    private ?string $origConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->origEnv = getenv('PROMPT_GEMINI') !== false ? getenv('PROMPT_GEMINI') : null;
        $this->origConfig = config('services.gemini.prompt');

        GeminiPromptService::clearCache();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        if ($this->origEnv !== null) {
            putenv("PROMPT_GEMINI={$this->origEnv}");
            $_ENV['PROMPT_GEMINI'] = $this->origEnv;
            $_SERVER['PROMPT_GEMINI'] = $this->origEnv;
        } else {
            putenv('PROMPT_GEMINI');
            unset($_ENV['PROMPT_GEMINI'], $_SERVER['PROMPT_GEMINI']);
        }

        config(['services.gemini.prompt' => $this->origConfig]);
        GeminiPromptService::clearCache();
        Cache::flush();

        parent::tearDown();
    }

    /**
     * Helper to completely wipe PROMPT_GEMINI from PHP environment and runtime config.
     */
    private function stripEnvironmentPrompt(): void
    {
        putenv('PROMPT_GEMINI');
        unset($_ENV['PROMPT_GEMINI'], $_SERVER['PROMPT_GEMINI']);
        config(['services.gemini.prompt' => null]);

        $this->assertTrue(getenv('PROMPT_GEMINI') === false || getenv('PROMPT_GEMINI') === '');
        $this->assertArrayNotHasKey('PROMPT_GEMINI', $_ENV);
        $this->assertNull(config('services.gemini.prompt'));
    }

    /**
     * Helper to determine the product creation route name.
     */
    private function getProductCreateRoute(): string
    {
        if (\Illuminate\Support\Facades\Route::has('admin.products.create')) {
            return route('admin.products.create');
        }
        if (\Illuminate\Support\Facades\Route::has('productos.create')) {
            return route('productos.create');
        }
        return url('/productos/create');
    }

    /**
     * Helper to authenticate an administrative user.
     */
    private function authenticateAdminUser(): User
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);
        return $admin;
    }

    /**
     * Challenge 1: Strict environment isolation.
     * Asserts prompt is loaded strictly from DB and cached with zero subsequent DB queries.
     */
    public function test_strict_env_isolation_without_env_and_config(): void
    {
        $this->stripEnvironmentPrompt();
        GeminiPromptService::clearCache();

        $dbOnlyPrompt = 'CHALLENGER_DB_ONLY_PROMPT_' . bin2hex(random_bytes(16));
        Setting::set('PROMPT_GEMINI', $dbOnlyPrompt, 'Adversarial isolation test');

        // Cold cache load
        $loaded = GeminiPromptService::getPrompt();
        $this->assertSame($dbOnlyPrompt, $loaded, 'Prompt must resolve from DB when env and config are completely absent');

        // Subsequent reads must execute 0 DB queries
        DB::enableQueryLog();
        for ($i = 0; $i < 20; $i++) {
            $cached = GeminiPromptService::getPrompt();
            $this->assertSame($dbOnlyPrompt, $cached);
        }
        $queries = DB::getQueryLog();
        $this->assertCount(0, $queries, 'Subsequent prompt retrievals must be served strictly from cache with 0 SQL queries');
    }

    /**
     * Challenge 1 (cont): Prove prompt is delivered to view and JS bridge strictly from DB/cache without .env.
     */
    public function test_prompt_delivered_to_view_and_js_bridge_strictly_from_db_without_env(): void
    {
        $this->stripEnvironmentPrompt();
        $this->authenticateAdminUser();

        $uniquePrompt = 'UNIQUE_VIEW_PROMPT_' . bin2hex(random_bytes(12));
        Setting::set('PROMPT_GEMINI', $uniquePrompt);
        GeminiPromptService::clearCache();

        $response = $this->get($this->getProductCreateRoute());
        $response->assertOk();
        $content = $response->getContent();

        // Verify JS bridge presence
        $this->assertStringContainsString('function buildGeminiStudioPrompt()', $content);
        $this->assertStringContainsString('window.buildGeminiStudioPrompt = buildGeminiStudioPrompt;', $content);

        // Extract rendered function body
        $matched = preg_match('/function\s+buildGeminiStudioPrompt\s*\(\)\s*\{\s*return\s+(.+?);\s*\}/s', $content, $matches);
        $this->assertSame(1, $matched, 'buildGeminiStudioPrompt must be defined with a return expression');

        $jsReturn = trim($matches[1]);
        $decoded = json_decode($jsReturn, true);

        $this->assertSame($uniquePrompt, $decoded, 'Rendered JS return expression must match DB prompt exactly');
        $this->assertNull(json_last_error() === JSON_ERROR_NONE ? null : json_last_error_msg());
    }

    /**
     * Challenge 2: Multibyte Spanish characters fidelity.
     * Asserts Spanish accents, tildes, question marks, and symbols are preserved without corruption.
     */
    public function test_multibyte_spanish_characters_fidelity_in_view_and_js_bridge(): void
    {
        $this->stripEnvironmentPrompt();
        $this->authenticateAdminUser();

        $multibytePrompt = '¡Atención, Cauchos Alfa! Válvulas, neumáticos, cuñas cónicas y piñones con alineación a 0° de inclinación. '
            . 'Conservar proporciones, texturas, iluminación óptima y diseño único sobre fondo blanco puro: '
            . 'á é í ó ú ñ Á É Í Ó Ú Ñ ¿ ¡ ° « »';

        Setting::set('PROMPT_GEMINI', $multibytePrompt);
        GeminiPromptService::clearCache();

        $servicePrompt = GeminiPromptService::getPrompt();
        $this->assertSame($multibytePrompt, $servicePrompt);
        $this->assertSame(mb_strlen($multibytePrompt, 'UTF-8'), mb_strlen($servicePrompt, 'UTF-8'));
        $this->assertSame(hash('sha256', $multibytePrompt), hash('sha256', $servicePrompt));

        $response = $this->get($this->getProductCreateRoute());
        $response->assertOk();
        $content = $response->getContent();

        $matched = preg_match('/function\s+buildGeminiStudioPrompt\s*\(\)\s*\{\s*return\s+(.+?);\s*\}/s', $content, $matches);
        $this->assertSame(1, $matched);

        $jsReturn = trim($matches[1]);
        $decoded = json_decode($jsReturn, true);

        $this->assertSame($multibytePrompt, $decoded, 'Rendered JS decoded prompt must preserve all Spanish multibyte characters verbatim');
        $this->assertSame(hash('sha256', $multibytePrompt), hash('sha256', $decoded));
    }

    /**
     * Challenge 2 (cont): Adversarial payload with quotes, script tags, JSON formatting, backslashes and newlines.
     */
    public function test_adversarial_payload_quotes_script_tags_and_json_structures(): void
    {
        $this->stripEnvironmentPrompt();
        $this->authenticateAdminUser();

        $adversarialPrompt = "Adversarial \"double quotes\" and 'single quotes', <script>alert('XSS')</script>, "
            . "JSON: {\"geometry\": {\"angle\": 0, \"ratio\": 1.5, \"tags\": [\"alfa\", \"caucho\"]}}, "
            . "backslashes: \\\\ and special control chars: \t \n \r.";

        Setting::set('PROMPT_GEMINI', $adversarialPrompt);
        GeminiPromptService::clearCache();

        $response = $this->get($this->getProductCreateRoute());
        $response->assertOk();
        $content = $response->getContent();

        $matched = preg_match('/function\s+buildGeminiStudioPrompt\s*\(\)\s*\{\s*return\s+(.+?);\s*\}/s', $content, $matches);
        $this->assertSame(1, $matched, 'JavaScript function syntax must remain valid even with adversarial payload');

        $jsReturn = trim($matches[1]);
        $decoded = json_decode($jsReturn, true);

        $this->assertNotNull($decoded, 'Rendered return statement must be valid JSON');
        $this->assertSame($adversarialPrompt, $decoded, 'Adversarial payload must round-trip through Blade @json without corruption or truncation');
    }

    /**
     * Challenge: Missing DB record with missing .env falls back to canonical prompt gracefully.
     */
    public function test_graceful_canonical_fallback_when_db_record_is_missing_and_env_is_absent(): void
    {
        $this->stripEnvironmentPrompt();
        Setting::where('key', 'PROMPT_GEMINI')->delete();
        GeminiPromptService::clearCache();

        $prompt = GeminiPromptService::getPrompt();

        $this->assertNotEmpty($prompt);
        $this->assertSame(GeminiPromptService::CANONICAL_FALLBACK_PROMPT, $prompt);
    }

    /**
     * Challenge: Direct SQL database modification detection and cache invalidation.
     */
    public function test_direct_db_tamper_detection_and_cache_refresh(): void
    {
        $this->stripEnvironmentPrompt();

        Setting::set('PROMPT_GEMINI', 'INITIAL_PROMPT_VAL');
        GeminiPromptService::clearCache();

        $initial = GeminiPromptService::getPrompt();
        $this->assertSame('INITIAL_PROMPT_VAL', $initial);

        // Direct SQL update (simulating external database administrator edit)
        $tamperedVal = 'TAMPERED_BY_SQL_DIRECT_UPDATE_' . bin2hex(random_bytes(8));
        DB::table('settings')->where('key', 'PROMPT_GEMINI')->update([
            'value' => $tamperedVal,
            'updated_at' => now(),
        ]);

        // Prior to TTL expiration or refreshIfChanged, cached value is served
        $stillCached = GeminiPromptService::getPrompt();
        $this->assertSame('INITIAL_PROMPT_VAL', $stillCached);

        // When refreshIfChanged is called, it detects the SHA-256 hash change
        $changed = GeminiPromptService::refreshIfChanged();
        $this->assertTrue($changed, 'refreshIfChanged must return true when SQL record changed');

        // Immediately subsequent getPrompt() returns the updated value
        $updated = GeminiPromptService::getPrompt();
        $this->assertSame($tamperedVal, $updated, 'Subsequent getPrompt() must return updated direct SQL value');
    }

    /**
     * Challenge: Multiline prompt with CRLF and LF newlines preserved verbatim in Blade and JS bridge.
     */
    public function test_multiline_crlf_and_lf_newlines_preservation_in_view_and_js_bridge(): void
    {
        $this->stripEnvironmentPrompt();
        $this->authenticateAdminUser();

        $multilinePrompt = "Line 1: Studio Photography Prompt\r\nLine 2: 0° Geometric Alignment\nLine 3: White Background\r\n\r\nLine 4: Final Touch";
        Setting::set('PROMPT_GEMINI', $multilinePrompt);
        GeminiPromptService::clearCache();

        $response = $this->get($this->getProductCreateRoute());
        $response->assertOk();
        $content = $response->getContent();

        $matched = preg_match('/function\s+buildGeminiStudioPrompt\s*\(\)\s*\{\s*return\s+(.+?);\s*\}/s', $content, $matches);
        $this->assertSame(1, $matched);

        $jsReturn = trim($matches[1]);
        $decoded = json_decode($jsReturn, true);

        $this->assertSame($multilinePrompt, $decoded, 'Multiline CRLF/LF newlines must be preserved in JS bridge without corrupting function syntax');
    }

    /**
     * Challenge: 4-byte UTF-8 emojis and mathematical symbols fidelity.
     */
    public function test_4byte_utf8_emoji_and_special_symbols_fidelity(): void
    {
        $this->stripEnvironmentPrompt();
        $this->authenticateAdminUser();

        $emojiPrompt = "📷 Studio Photography ⚙️ Precision Alignment 0° 📐 Perspective frontale 🎨 Couleurs fidèles 🌟 100% Top";
        Setting::set('PROMPT_GEMINI', $emojiPrompt);
        GeminiPromptService::clearCache();

        $response = $this->get($this->getProductCreateRoute());
        $response->assertOk();
        $content = $response->getContent();

        $matched = preg_match('/function\s+buildGeminiStudioPrompt\s*\(\)\s*\{\s*return\s+(.+?);\s*\}/s', $content, $matches);
        $this->assertSame(1, $matched);

        $jsReturn = trim($matches[1]);
        $decoded = json_decode($jsReturn, true);

        $this->assertSame($emojiPrompt, $decoded, '4-byte UTF-8 emojis and symbols must render accurately');
    }

    /**
     * Challenge: Explicit NULL in database column falls back gracefully to canonical prompt.
     */
    public function test_explicit_null_value_in_db_falls_back_to_canonical(): void
    {
        $this->stripEnvironmentPrompt();

        Setting::updateOrCreate(['key' => 'PROMPT_GEMINI'], ['value' => null]);
        GeminiPromptService::clearCache();

        $prompt = GeminiPromptService::getPrompt();
        $this->assertSame(GeminiPromptService::CANONICAL_FALLBACK_PROMPT, $prompt);
    }

    /**
     * Challenge: Automatic revalidation when time travels past custom TTL with direct SQL update.
     */
    public function test_automatic_revalidation_when_time_travels_past_custom_ttl(): void
    {
        $this->stripEnvironmentPrompt();

        GeminiPromptService::setTtl(60); // 60 seconds TTL
        Setting::set('PROMPT_GEMINI', 'TTL_TEST_INITIAL');
        GeminiPromptService::clearCache();

        // 1. Initial load
        $this->assertSame('TTL_TEST_INITIAL', GeminiPromptService::getPrompt());

        // 2. Direct SQL modification without touching model events
        DB::table('settings')->where('key', 'PROMPT_GEMINI')->update([
            'value' => 'TTL_TEST_EXPIRED_SQL',
            'updated_at' => now(),
        ]);

        // 3. Within TTL (30 seconds later), cached value is retained
        $this->travel(30)->seconds();
        $this->assertSame('TTL_TEST_INITIAL', GeminiPromptService::getPrompt());

        // 4. Past TTL (61 seconds later), auto-revalidation kicks in
        $this->travel(35)->seconds(); // total 65 seconds
        $this->assertSame('TTL_TEST_EXPIRED_SQL', GeminiPromptService::getPrompt(), 'After TTL expiration, getPrompt() must reload from DB');
    }
}
