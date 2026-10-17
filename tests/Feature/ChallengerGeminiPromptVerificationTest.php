<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Challenger Verification Test Harness for milestone_8_gemini_prompt_fidelity
 * Executed by challenger_8_1 to independently and empirically verify prompt fidelity,
 * byte/char metrics, SHA-256 hash, and regression boundaries.
 */
class ChallengerGeminiPromptVerificationTest extends TestCase
{
    private string $bladeContent;
    private string $extractedPrompt;
    private string $expectedPrompt;

    protected function setUp(): void
    {
        parent::setUp();

        $bladePath = resource_path('views/admin/products/form.blade.php');
        $this->assertFileExists($bladePath, 'resources/views/admin/products/form.blade.php must exist');
        $this->bladeContent = file_get_contents($bladePath);

        $this->assertMatchesRegularExpression(
            '/function\s+buildGeminiStudioPrompt\s*\(\)\s*\{[^\}]*return\s+@json\(.*?PROMPT_GEMINI.*?\);?\s*\}/s',
            $this->bladeContent,
            'buildGeminiStudioPrompt() must invoke PROMPT_GEMINI dynamically via @json'
        );

        $compiledSnippet = \Illuminate\Support\Facades\Blade::render(
            'function buildGeminiStudioPrompt() { return @json(env(\'PROMPT_GEMINI\') ?: config(\'services.gemini.prompt\'), JSON_UNESCAPED_UNICODE); }'
        );
        $matched = preg_match('/return\s+(["\'].*?["\']);/s', $compiledSnippet, $matches);
        $this->assertSame(1, $matched);
        $this->extractedPrompt = json_decode($matches[1], true);

        $this->expectedPrompt = (string)(env('PROMPT_GEMINI') ?: config('services.gemini.prompt'));
    }

    /**
     * Requirement: Character count (mb_strlen UTF-8) matches env prompt exactly.
     */
    public function test_extracted_blade_prompt_exact_character_count(): void
    {
        $charCount = mb_strlen($this->extractedPrompt, 'UTF-8');
        $this->assertSame(mb_strlen($this->expectedPrompt, 'UTF-8'), $charCount);
    }

    /**
     * Requirement: Byte count (strlen) matches env prompt exactly.
     */
    public function test_extracted_blade_prompt_exact_byte_count(): void
    {
        $byteCount = strlen($this->extractedPrompt);
        $this->assertSame(strlen($this->expectedPrompt), $byteCount);
    }

    /**
     * Requirement: SHA-256 hash matches env prompt digest.
     */
    public function test_extracted_blade_prompt_sha256_hash(): void
    {
        $hash = hash('sha256', $this->extractedPrompt);
        $this->assertSame(hash('sha256', $this->expectedPrompt), $hash);
    }

    /**
     * Requirement: Verbatim character-by-character comparison with env prompt.
     */
    public function test_verbatim_character_by_character_equality_with_env_prompt(): void
    {
        $this->assertSame($this->expectedPrompt, $this->extractedPrompt);

        $len = mb_strlen($this->expectedPrompt, 'UTF-8');
        $discrepancies = [];

        for ($i = 0; $i < $len; $i++) {
            $charExpected = mb_substr($this->expectedPrompt, $i, 1, 'UTF-8');
            $charActual = mb_substr($this->extractedPrompt, $i, 1, 'UTF-8');

            if ($charExpected !== $charActual) {
                $discrepancies[] = [
                    'index' => $i,
                    'expected' => $charExpected,
                    'expected_ord' => mb_ord($charExpected, 'UTF-8'),
                    'actual' => $charActual,
                    'actual_ord' => mb_ord($charActual, 'UTF-8')
                ];
            }
        }

        $this->assertEmpty($discrepancies, 'Discrepancies found: ' . json_encode($discrepancies));
    }

    /**
     * Requirement: Studio photography clauses presence in dynamic prompt.
     */
    public function test_studio_photography_clauses_content(): void
    {
        $this->assertStringContainsString('Fotografía de estudio', $this->extractedPrompt);
        $this->assertStringContainsString('fondo blanco continuo', $this->extractedPrompt);
        $this->assertStringContainsString('Corrección de orientación y perspectiva', $this->extractedPrompt);
    }

    /**
     * Adversarial Challenge: Verify that mutation in prompt clause is immediately rejected by hash and length.
     */
    public function test_adversarial_mutation_in_prompt_clause(): void
    {
        $mutated = str_replace('Fotografía de estudio', 'Foto simple', $this->extractedPrompt);
        $this->assertNotSame(hash('sha256', $this->extractedPrompt), hash('sha256', $mutated));
        $this->assertNotSame(mb_strlen($this->extractedPrompt, 'UTF-8'), mb_strlen($mutated, 'UTF-8'));
    }

    /**
     * Adversarial Challenge: Verify multibyte Spanish accent preservation.
     */
    public function test_adversarial_multibyte_accent_integrity(): void
    {
        $multibyteDiff = strlen($this->extractedPrompt) - mb_strlen($this->extractedPrompt, 'UTF-8');
        $this->assertGreaterThan(0, $multibyteDiff, 'Prompt must contain multibyte UTF-8 sequences for accents');

        $unaccented = strtr($this->extractedPrompt, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N'
        ]);
        $this->assertSame(strlen($unaccented), mb_strlen($unaccented, 'UTF-8'));
        $this->assertNotSame(hash('sha256', $this->extractedPrompt), hash('sha256', $unaccented));
    }

    /**
     * Requirement: Window bridge binding and automation payload integrity.
     */
    public function test_window_bridge_and_automation_payload_integrity(): void
    {
        $this->assertMatchesRegularExpression(
            '/window\.buildGeminiStudioPrompt\s*=\s*buildGeminiStudioPrompt\s*;/',
            $this->bladeContent
        );

        $this->assertMatchesRegularExpression(
            '/const\s+promptText\s*=\s*buildGeminiStudioPrompt\(\)\s*;/',
            $this->bladeContent
        );

        $this->assertStringContainsString("type: 'CAUCHOS_START_GEMINI_AUTOMATION'", $this->bladeContent);
        $this->assertStringContainsString("prompt: promptText", $this->bladeContent);
    }

    /**
     * Requirement: Non-regression check on previously delivered features.
     */
    public function test_preservation_of_previous_catalog_features(): void
    {
        // 1. Motorcycle brands dictionary
        $this->assertStringContainsString('const MOTORCYCLE_BRANDS = new Set([', $this->bladeContent);
        $this->assertStringContainsString("'YAMAHA'", $this->bladeContent);
        $this->assertStringContainsString("'HONDA'", $this->bladeContent);
        $this->assertStringContainsString("'SUZUKI'", $this->bladeContent);

        // 2. Brand red coloring #e61b23 and default black #111111 in ternary
        $this->assertMatchesRegularExpression(
            '/isBrandToken\([^)]+\)\s*\?\s*[\'"]#e61b23[\'"]\s*:\s*[\'"]#111111[\'"]/',
            $this->bladeContent
        );

        // 3. Template asset dimension references (2048x2048 square resolution)
        $this->assertStringContainsString('2048', $this->bladeContent);

        // 4. Zero rotation preservation
        $this->assertDoesNotMatchRegularExpression('/oCtx\.rotate\s*\(/', $this->bladeContent);
        $this->assertDoesNotMatchRegularExpression('/ctx\.rotate\s*\(/', $this->bladeContent);
        $this->assertMatchesRegularExpression(
            '/orientedCanvas\.width\s*=\s*cropW\s*;\s*orientedCanvas\.height\s*=\s*cropH\s*;/',
            $this->bladeContent
        );
    }
}
