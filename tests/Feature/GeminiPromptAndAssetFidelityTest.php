<?php

namespace Tests\Feature;

use Tests\TestCase;

class GeminiPromptAndAssetFidelityTest extends TestCase
{
    /**
     * Expected prompt dynamically loaded from .env (PROMPT_GEMINI)
     */
    private string $expectedPrompt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expectedPrompt = (string)(env('PROMPT_GEMINI') ?: config('services.gemini.prompt'));
    }

    /**
     * Test extraction, dynamic env invocation, and character-by-character fidelity of buildGeminiStudioPrompt()
     */
    public function test_gemini_studio_prompt_fidelity_and_zero_discrepancies(): void
    {
        $bladePath = resource_path('views/admin/products/form.blade.php');
        $this->assertFileExists($bladePath, 'form.blade.php must exist.');

        $content = file_get_contents($bladePath);

        // 1. Verify buildGeminiStudioPrompt does not contain hardcoded string and dynamically invokes env/config
        $this->assertMatchesRegularExpression(
            '/function\s+buildGeminiStudioPrompt\s*\(\)\s*\{[^\}]*return\s+@json\(.*?PROMPT_GEMINI.*?\);?\s*\}/s',
            $content,
            'buildGeminiStudioPrompt function must invoke PROMPT_GEMINI from env/config rather than hardcoding string'
        );

        // 2. Render the Blade snippet with the active env/config
        $compiledSnippet = \Illuminate\Support\Facades\Blade::render(
            'function buildGeminiStudioPrompt() { return @json(env(\'PROMPT_GEMINI\') ?: config(\'services.gemini.prompt\'), JSON_UNESCAPED_UNICODE); }'
        );

        $matched = preg_match('/return\s+(["\'].*?["\']);/s', $compiledSnippet, $matches);
        $this->assertSame(1, $matched, 'Rendered buildGeminiStudioPrompt must return a quoted JSON string');
        $actualPrompt = json_decode($matches[1], true);

        // 3. Length comparison
        $expectedLength = mb_strlen($this->expectedPrompt, 'UTF-8');
        $actualLength = mb_strlen($actualPrompt, 'UTF-8');
        $this->assertSame($expectedLength, $actualLength, "Prompt length mismatch: expected {$expectedLength}, got {$actualLength}");

        // 4. Byte length comparison
        $expectedBytes = strlen($this->expectedPrompt);
        $actualBytes = strlen($actualPrompt);
        $this->assertSame($expectedBytes, $actualBytes, "Prompt byte length mismatch: expected {$expectedBytes}, got {$actualBytes}");

        // 5. SHA-256 hash comparison
        $expectedHash = hash('sha256', $this->expectedPrompt);
        $actualHash = hash('sha256', $actualPrompt);
        $this->assertSame($expectedHash, $actualHash, "Prompt SHA256 mismatch: expected {$expectedHash}, got {$actualHash}");

        // 6. Character-by-character comparison with exact code point assertions
        $discrepancies = [];
        for ($i = 0; $i < $expectedLength; $i++) {
            $expectedChar = mb_substr($this->expectedPrompt, $i, 1, 'UTF-8');
            $actualChar = mb_substr($actualPrompt, $i, 1, 'UTF-8');
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

        $this->assertEmpty($discrepancies, 'Found character discrepancies: ' . json_encode($discrepancies));

        // 7. Verify dynamic reactivity to env/config changes (not hardcoded/burned-in)
        $customPrompt = 'Prompt dinamico de prueba ' . uniqid();
        config(['services.gemini.prompt' => $customPrompt]);
        $customCompiled = \Illuminate\Support\Facades\Blade::render(
            'function buildGeminiStudioPrompt() { return @json(config(\'services.gemini.prompt\'), JSON_UNESCAPED_UNICODE); }'
        );
        $this->assertStringContainsString(json_encode($customPrompt, JSON_UNESCAPED_UNICODE), $customCompiled);
    }

    /**
     * Test global window bridge exposure
     */
    public function test_window_bridge_exposure(): void
    {
        $bladePath = resource_path('views/admin/products/form.blade.php');
        $content = file_get_contents($bladePath);

        // Verify window.buildGeminiStudioPrompt = buildGeminiStudioPrompt;
        $this->assertMatchesRegularExpression(
            '/window\.buildGeminiStudioPrompt\s*=\s*buildGeminiStudioPrompt\s*;/',
            $content,
            'window.buildGeminiStudioPrompt must be explicitly bound on window object'
        );

        // Verify bridge invocation inside generateWithGeminiExtension
        $this->assertMatchesRegularExpression(
            '/const\s+promptText\s*=\s*buildGeminiStudioPrompt\(\)\s*;/',
            $content,
            'generateWithGeminiExtension must invoke buildGeminiStudioPrompt()'
        );

        // Verify bridge transmission payload in runGeminiGenerationBridge
        $this->assertStringContainsString("type: 'CAUCHOS_START_GEMINI_AUTOMATION'", $content);
        $this->assertStringContainsString("prompt: promptText", $content);
    }

    /**
     * Test template asset on disk: existence, size, dimensions
     */
    public function test_template_asset_on_disk(): void
    {
        $assetPath = public_path('img/plantilla-estado.jpg');
        $this->assertFileExists($assetPath, 'public/img/plantilla-estado.jpg must exist on disk.');

        $fileSize = filesize($assetPath);
        $this->assertGreaterThan(100000, $fileSize, 'Template asset should be a valid large high-res JPEG image.');

        $imageInfo = getimagesize($assetPath);
        $this->assertNotFalse($imageInfo, 'Template asset must be a valid image readable by GD/getimagesize.');
        $this->assertSame(2048, $imageInfo[0], 'Template width must be exactly 2048px.');
        $this->assertSame(2048, $imageInfo[1], 'Template height must be exactly 2048px.');
        $this->assertSame(IMAGETYPE_JPEG, $imageInfo[2], 'Template must be JPEG MIME type.');
    }

    /**
     * Test HTTP 200 accessibility for public/img/plantilla-estado.jpg
     */
    public function test_template_asset_http_200_accessibility(): void
    {
        // Test via local web server (Apache in XAMPP)
        $url = 'http://localhost/cauchos-alfa/public/img/plantilla-estado.jpg';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);

        $this->assertSame(200, $httpCode, "HTTP HEAD request to {$url} must return HTTP 200 OK. Got: {$httpCode}");
        $this->assertStringStartsWith('image/jpeg', $contentType, "Content-Type must be image/jpeg. Got: {$contentType}");
        $this->assertSame(filesize(public_path('img/plantilla-estado.jpg')), (int)$contentLength, "HTTP Content-Length must match disk file size.");
    }
}
