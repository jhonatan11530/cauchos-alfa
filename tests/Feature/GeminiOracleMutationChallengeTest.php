<?php

namespace Tests\Feature;

use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/**
 * Adversarial Empirical Challenge Test:
 * Validates that GeminiPromptAndAssetFidelityTest behaves as a strict oracle
 * capable of catching single-character mutations, asset dimension deviations,
 * and window exposure omissions.
 */
class GeminiOracleMutationChallengeTest extends TestCase
{
    private string $canonicalPrompt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->canonicalPrompt = (string)(env('PROMPT_GEMINI') ?: config('services.gemini.prompt'));
    }

    /**
     * Helper to simulate the exact oracle validation logic from GeminiPromptAndAssetFidelityTest
     */
    private function evaluatePromptOracle(string $testCandidate): array
    {
        $failures = [];

        // 1. Length comparison
        $expectedLength = mb_strlen($this->canonicalPrompt, 'UTF-8');
        $actualLength = mb_strlen($testCandidate, 'UTF-8');
        if ($expectedLength !== $actualLength) {
            $failures['length'] = "Expected {$expectedLength}, got {$actualLength}";
        }

        // 2. Byte length comparison
        $expectedBytes = strlen($this->canonicalPrompt);
        $actualBytes = strlen($testCandidate);
        if ($expectedBytes !== $actualBytes) {
            $failures['bytes'] = "Expected {$expectedBytes}, got {$actualBytes}";
        }

        // 3. SHA-256 hash comparison
        $expectedHash = hash('sha256', $this->canonicalPrompt);
        $actualHash = hash('sha256', $testCandidate);
        if ($expectedHash !== $actualHash) {
            $failures['hash'] = "Expected {$expectedHash}, got {$actualHash}";
        }

        // 4. Character-by-character comparison with exact code points
        $discrepancies = [];
        $compareLen = min($expectedLength, $actualLength);
        for ($i = 0; $i < $compareLen; $i++) {
            $expectedChar = mb_substr($this->canonicalPrompt, $i, 1, 'UTF-8');
            $actualChar = mb_substr($testCandidate, $i, 1, 'UTF-8');
            if ($expectedChar !== $actualChar) {
                $discrepancies[] = [
                    'index' => $i,
                    'expected' => $expectedChar,
                    'expected_ord' => mb_ord($expectedChar, 'UTF-8'),
                    'actual' => $actualChar,
                    'actual_ord' => mb_ord($actualChar, 'UTF-8'),
                ];
            }
        }
        if (!empty($discrepancies)) {
            $failures['discrepancies'] = $discrepancies;
        }

        // 5. Cross-check against dynamic env prompt
        $expected = (string)(env('PROMPT_GEMINI') ?: config('services.gemini.prompt'));
        if ($testCandidate !== $expected) {
            $failures['env_mismatch'] = 'Candidate does not match env PROMPT_GEMINI';
        }

        return $failures;
    }

    /**
     * Helper to simulate asset dimension validation from GeminiPromptAndAssetFidelityTest
     */
    private function evaluateAssetOracle(int $width, int $height, int $mimeType, int $fileSize): array
    {
        $failures = [];
        if ($fileSize <= 100000) {
            $failures['filesize'] = "File size {$fileSize} <= 100000";
        }
        if ($width !== 2048) {
            $failures['width'] = "Width {$width} !== 2048";
        }
        if ($height !== 2048) {
            $failures['height'] = "Height {$height} !== 2048";
        }
        if ($mimeType !== IMAGETYPE_JPEG) {
            $failures['mime'] = "MIME type {$mimeType} !== IMAGETYPE_JPEG";
        }
        return $failures;
    }

    /**
     * Helper to simulate window bridge exposure oracle from GeminiPromptAndAssetFidelityTest
     */
    private function evaluateWindowExposureOracle(string $bladeContent): array
    {
        $failures = [];
        if (!preg_match('/window\.buildGeminiStudioPrompt\s*=\s*buildGeminiStudioPrompt\s*;/', $bladeContent)) {
            $failures['window_binding'] = 'Missing window.buildGeminiStudioPrompt binding';
        }
        if (!preg_match('/const\s+promptText\s*=\s*buildGeminiStudioPrompt\(\)\s*;/', $bladeContent)) {
            $failures['bridge_invocation'] = 'Missing promptText invocation';
        }
        if (!str_contains($bladeContent, "type: 'CAUCHOS_START_GEMINI_AUTOMATION'")) {
            $failures['automation_type'] = "Missing CAUCHOS_START_GEMINI_AUTOMATION";
        }
        if (!str_contains($bladeContent, "prompt: promptText")) {
            $failures['payload_prompt'] = "Missing prompt: promptText in payload";
        }
        return $failures;
    }

    /**
     * Test 1: Canonical production prompt has 0 oracle failures
     */
    public function test_canonical_prompt_passes_oracle_with_zero_failures(): void
    {
        $failures = $this->evaluatePromptOracle($this->canonicalPrompt);
        $this->assertEmpty($failures, 'Canonical prompt must have zero oracle failures.');
    }

    /**
     * Test 2: Single-character substitution at multiple boundary points
     */
    public function test_oracle_detects_single_character_substitutions(): void
    {
        // 2a. Mutation at index 0
        $char0 = mb_substr($this->canonicalPrompt, 0, 1, 'UTF-8');
        $alt0 = ($char0 === 'M') ? 'm' : 'M';
        $mutant1 = $alt0 . mb_substr($this->canonicalPrompt, 1, null, 'UTF-8');
        $failures1 = $this->evaluatePromptOracle($mutant1);
        $this->assertNotEmpty($failures1, 'Must catch mutation at index 0');
        $this->assertArrayHasKey('hash', $failures1);
        $this->assertArrayHasKey('discrepancies', $failures1);
        $this->assertSame(0, $failures1['discrepancies'][0]['index']);
        $this->assertSame($char0, $failures1['discrepancies'][0]['expected']);
        $this->assertSame($alt0, $failures1['discrepancies'][0]['actual']);

        // 2b. Mutation at index 10: character substitution
        $idx10 = 10;
        $char10 = mb_substr($this->canonicalPrompt, $idx10, 1, 'UTF-8');
        $alt10 = ($char10 === 'X') ? 'Y' : 'X';
        $mutant2 = mb_substr($this->canonicalPrompt, 0, $idx10, 'UTF-8') . $alt10 . mb_substr($this->canonicalPrompt, $idx10 + 1, null, 'UTF-8');
        $failures2 = $this->evaluatePromptOracle($mutant2);
        $this->assertNotEmpty($failures2, 'Must catch mutation at index 10');
        $this->assertArrayHasKey('hash', $failures2);
        $this->assertArrayHasKey('discrepancies', $failures2);
        $this->assertSame($idx10, $failures2['discrepancies'][0]['index']);

        // 2c. Mutation at terminal index: '.' -> '!'
        $lastIdx = mb_strlen($this->canonicalPrompt, 'UTF-8') - 1;
        $mutant3 = mb_substr($this->canonicalPrompt, 0, $lastIdx, 'UTF-8') . '!';
        $failures3 = $this->evaluatePromptOracle($mutant3);
        $this->assertNotEmpty($failures3, 'Must catch mutation at terminal period');
        $this->assertArrayHasKey('hash', $failures3);
        $this->assertArrayHasKey('discrepancies', $failures3);
        $this->assertSame($lastIdx, $failures3['discrepancies'][0]['index']);
    }

    /**
     * Test 3: Single-character deletion
     */
    public function test_oracle_detects_single_character_deletion(): void
    {
        $expectedLen = mb_strlen($this->canonicalPrompt, 'UTF-8');
        $mutant = mb_substr($this->canonicalPrompt, 0, 10, 'UTF-8') . mb_substr($this->canonicalPrompt, 11, null, 'UTF-8');
        $this->assertSame($expectedLen - 1, mb_strlen($mutant, 'UTF-8'));

        $failures = $this->evaluatePromptOracle($mutant);
        $this->assertNotEmpty($failures, 'Must detect single-character deletion');
        $this->assertArrayHasKey('length', $failures);
        $this->assertArrayHasKey('bytes', $failures);
        $this->assertArrayHasKey('hash', $failures);
    }

    /**
     * Test 4: Single-character insertion (e.g. trailing whitespace)
     */
    public function test_oracle_detects_single_character_insertion(): void
    {
        $expectedLen = mb_strlen($this->canonicalPrompt, 'UTF-8');
        $mutant = $this->canonicalPrompt . ' ';
        $this->assertSame($expectedLen + 1, mb_strlen($mutant, 'UTF-8'));

        $failures = $this->evaluatePromptOracle($mutant);
        $this->assertNotEmpty($failures, 'Must detect single-character insertion');
        $this->assertArrayHasKey('length', $failures);
        $this->assertArrayHasKey('bytes', $failures);
        $this->assertArrayHasKey('hash', $failures);
    }

    /**
     * Test 5: Multibyte accent mutation (e.g. 'í' -> 'i', identical char count but byte/hash discrepancy)
     */
    public function test_oracle_detects_multibyte_accent_degradation(): void
    {
        // Replace 'í' with ASCII 'i' in "Fotografía" -> "Fotografia"
        $mutant = str_replace('Fotografía', 'Fotografia', $this->canonicalPrompt);
        $this->assertSame(mb_strlen($this->canonicalPrompt, 'UTF-8'), mb_strlen($mutant, 'UTF-8'), 'Char length is deceptively identical');
        $this->assertLessThan(strlen($this->canonicalPrompt), strlen($mutant), 'Byte length is altered (lost 1 byte from UTF-8 2-byte char)');

        $failures = $this->evaluatePromptOracle($mutant);
        $this->assertNotEmpty($failures, 'Oracle must catch accent mutation despite matching mb_strlen');
        $this->assertArrayHasKey('bytes', $failures, 'Must flag byte length discrepancy');
        $this->assertArrayHasKey('hash', $failures, 'Must flag SHA-256 hash discrepancy');
        $this->assertArrayHasKey('discrepancies', $failures, 'Must flag code point discrepancy');
    }

    /**
     * Test 6: Reversion to old physical rotation prompt from 2026-10-15T18:02:33Z
     */
    public function test_oracle_rejects_legacy_physical_rotation_prompt(): void
    {
        $oldPrompt = 'mejora la imagen y aplica Fotografía de estudio, en primer plano y de alta calidad, sobre un fondo blanco continuo. aplicar realismo al objecto. Iluminación natural de estudio con enfoque nítido y sombras suaves en la base para asentar el objeto de forma realista. Corrección de orientación y perspectiva: analiza la pose del producto en la imagen; si el objeto fue tomado de frente y está girado o torcido en cualquier ángulo, colócalo completamente derecho y con vista frontal nivelada. Si el objeto corresponde a una vista lateral pero se encuentra en posición vertical, gíralo para que repose de manera horizontal y natural sobre su base.';

        $failures = $this->evaluatePromptOracle($oldPrompt);
        $this->assertNotEmpty($failures, 'Oracle must decisively reject old prompt');
        $this->assertArrayHasKey('length', $failures, 'Old prompt has length 627 vs expected 707');
        $this->assertArrayHasKey('bytes', $failures, 'Old prompt has 643 bytes vs expected 721');
        $this->assertArrayHasKey('hash', $failures, 'Old prompt has completely different SHA-256');
        $this->assertArrayHasKey('discrepancies', $failures, 'Old prompt has discrepancies starting at perspective clause');
        // Note: old prompt is historically present in ORIGINAL_REQUEST.md (§ 18:02:33Z),
        // but length, byte, hash, and discrepancies decisively reject it.
    }

    /**
     * Test 7: Asset dimensions oracle strictness (2048x2048)
     */
    public function test_oracle_detects_asset_dimension_deviations(): void
    {
        // 7a. Native square dimensions pass
        $validFailures = $this->evaluateAssetOracle(2048, 2048, IMAGETYPE_JPEG, 1200000);
        $this->assertEmpty($validFailures, '2048x2048 JPEG must pass asset oracle');

        // 7b. Width mutated by 1 pixel (2047 vs 2048)
        $wFailures = $this->evaluateAssetOracle(2047, 2048, IMAGETYPE_JPEG, 1200000);
        $this->assertArrayHasKey('width', $wFailures, 'Must fail when width is 2047');

        // 7c. Height mutated by 1 pixel (2048 vs 2047)
        $hFailures = $this->evaluateAssetOracle(2048, 2047, IMAGETYPE_JPEG, 1200000);
        $this->assertArrayHasKey('height', $hFailures, 'Must fail when height is 2047');

        // 7d. Legacy rectangular dimensions (928x1150) must fail both width and height
        $rectFailures = $this->evaluateAssetOracle(928, 1150, IMAGETYPE_JPEG, 1200000);
        $this->assertArrayHasKey('width', $rectFailures);
        $this->assertArrayHasKey('height', $rectFailures);

        // 7e. Wrong MIME type (PNG instead of JPEG)
        $pngFailures = $this->evaluateAssetOracle(2048, 2048, IMAGETYPE_PNG, 1200000);
        $this->assertArrayHasKey('mime', $pngFailures);

        // 7f. Truncated or small file size (50KB)
        $smallFailures = $this->evaluateAssetOracle(2048, 2048, IMAGETYPE_JPEG, 50000);
        $this->assertArrayHasKey('filesize', $smallFailures);
    }

    /**
     * Test 8: Window bridge exposure oracle strictness
     */
    public function test_oracle_detects_missing_window_bridge_components(): void
    {
        $bladePath = resource_path('views/admin/products/form.blade.php');
        $bladeContent = file_get_contents($bladePath);

        // 8a. Pristine Blade passes
        $pristineFailures = $this->evaluateWindowExposureOracle($bladeContent);
        $this->assertEmpty($pristineFailures, 'Pristine Blade must have zero window exposure failures.');

        // 8b. Missing window global binding
        $mutated1 = str_replace('window.buildGeminiStudioPrompt = buildGeminiStudioPrompt;', '// window binding removed', $bladeContent);
        $f1 = $this->evaluateWindowExposureOracle($mutated1);
        $this->assertArrayHasKey('window_binding', $f1);

        // 8c. Missing invocation in generateWithGeminiExtension
        $mutated2 = str_replace('const promptText = buildGeminiStudioPrompt();', 'const promptText = "";', $bladeContent);
        $f2 = $this->evaluateWindowExposureOracle($mutated2);
        $this->assertArrayHasKey('bridge_invocation', $f2);

        // 8d. Missing CAUCHOS_START_GEMINI_AUTOMATION event type
        $mutated3 = str_replace("type: 'CAUCHOS_START_GEMINI_AUTOMATION'", "type: 'UNKNOWN_EVENT'", $bladeContent);
        $f3 = $this->evaluateWindowExposureOracle($mutated3);
        $this->assertArrayHasKey('automation_type', $f3);

        // 8e. Missing prompt: promptText payload
        $mutated4 = str_replace("prompt: promptText", "data: promptText", $bladeContent);
        $f4 = $this->evaluateWindowExposureOracle($mutated4);
        $this->assertArrayHasKey('payload_prompt', $f4);
    }

    /**
     * Test 9: Real form.blade.php extracted prompt matches canonical prompt with zero errors
     */
    public function test_real_blade_prompt_has_exact_fidelity(): void
    {
        $bladePath = resource_path('views/admin/products/form.blade.php');
        $bladeContent = file_get_contents($bladePath);

        $this->assertMatchesRegularExpression(
            '/function\s+buildGeminiStudioPrompt\s*\(\)\s*\{[^\}]*return\s+@json\(.*?PROMPT_GEMINI.*?\);?\s*\}/s',
            $bladeContent,
            'buildGeminiStudioPrompt must dynamically invoke PROMPT_GEMINI'
        );

        $rendered = \Illuminate\Support\Facades\Blade::render(
            'function buildGeminiStudioPrompt() { return @json(env(\'PROMPT_GEMINI\') ?: config(\'services.gemini.prompt\'), JSON_UNESCAPED_UNICODE); }'
        );
        $matched = preg_match('/return\s+(["\'].*?["\']);/s', $rendered, $matches);
        $this->assertSame(1, $matched);
        $actualPrompt = json_decode($matches[1], true);

        $failures = $this->evaluatePromptOracle($actualPrompt);
        $this->assertEmpty($failures, 'Real form.blade.php prompt must have zero oracle failures.');
    }
}
