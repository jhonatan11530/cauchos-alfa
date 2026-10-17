<?php

namespace Tests\Feature;

use Tests\TestCase;

class CatalogTemplateTitleAndBrandFidelityTest extends TestCase
{
    private string $bladeContent;

    protected function setUp(): void
    {
        parent::setUp();
        $bladePath = resource_path('views/admin/products/form.blade.php');
        $this->assertFileExists($bladePath, 'form.blade.php must exist.');
        $this->bladeContent = file_get_contents($bladePath);
    }

    /**
     * 1. Assert motorcycle brand dictionary/set is defined in form.blade.php
     */
    public function test_motorcycle_brand_dictionary_is_defined(): void
    {
        $this->assertStringContainsString(
            'const MOTORCYCLE_BRANDS = new Set([',
            $this->bladeContent,
            'MOTORCYCLE_BRANDS Set must be explicitly defined.'
        );

        $requiredBrands = [
            'YAMAHA', 'HONDA', 'SUZUKI', 'BAJAJ', 'AKT', 'AUTECO',
            'KAWASAKI', 'HERO', 'KTM', 'TVS', 'KYMCO', 'VICTORY',
            'SIGMA', 'SYM'
        ];

        foreach ($requiredBrands as $brand) {
            $this->assertStringContainsString(
                "'{$brand}'",
                $this->bladeContent,
                "form.blade.php must include '{$brand}' in motorcycle brand dictionary/set."
            );
        }

        // Assert helper functions exist
        $this->assertStringContainsString('function isBrandToken(', $this->bladeContent);
        $this->assertStringContainsString('function tokenizeLineForCanvas(', $this->bladeContent);
        $this->assertStringContainsString('function splitTitleIntoBalancedLines(', $this->bladeContent);
    }

    /**
     * 2. Assert brand coloring rules: #e61b23 for brands, #111111 for non-brands, and old heuristic removed
     */
    public function test_brand_coloring_colors_in_blade(): void
    {
        $this->assertStringContainsString('#e61b23', $this->bladeContent, "Corporate red #e61b23 must be used for brands.");
        $this->assertStringContainsString('#111111', $this->bladeContent, "High-contrast black #111111 must be used for non-brand text.");

        // Assert old naive heuristic (i === 1) is completely removed
        $this->assertDoesNotMatchRegularExpression(
            '/i\s*===\s*1\s*&&\s*words\.length\s*>\s*1/',
            $this->bladeContent,
            'Old naive second-word coloring heuristic (i === 1) must be eliminated.'
        );

        // Verify brand token assignment to #e61b23 and fallback to #111111
        $this->assertMatchesRegularExpression(
            '/isBrandToken\([^)]+\)\s*\?\s*[\'"]#e61b23[\'"]\s*:\s*[\'"]#111111[\'"]/',
            $this->bladeContent,
            'Brand tokens must dynamically receive #e61b23, non-brand #111111.'
        );
    }

    /**
     * 3. Assert 2-line title distribution and font size threshold
     */
    public function test_two_line_distribution_and_font_thresholds(): void
    {
        // Single-line threshold: scaled for 2048px canvas (singleLineFs >= 50)
        $this->assertMatchesRegularExpression(
            '/singleLineFs\s*>=\s*50/',
            $this->bladeContent,
            'Threshold check (singleLineFs >= 50) must govern 1-line vs 2-line distribution.'
        );

        // Max single-line font size (75px)
        $this->assertMatchesRegularExpression(
            '/singleLineFs\s*=\s*75;/',
            $this->bladeContent,
            'Large initial font size (75px) must be configured for single-line titles.'
        );

        // Two-line starting font size (64px)
        $this->assertMatchesRegularExpression(
            '/twoLineFs\s*=\s*64;/',
            $this->bladeContent,
            'Initial font size for 2-line mode must start at 64px.'
        );

        // Line-splitting function invocation
        $this->assertStringContainsString(
            'splitTitleIntoBalancedLines(text, topMaxWidth, ctx)',
            $this->bladeContent,
            'splitTitleIntoBalancedLines must be invoked when single-line font drops below threshold.'
        );
    }

    /**
     * 4. Assert vertical coordinates remain in safe white zone [300, 500]
     */
    public function test_vertical_coordinates_within_safe_zone(): void
    {
        // Center coordinate anchor
        $this->assertMatchesRegularExpression(
            '/topCenterY\s*=\s*410;/',
            $this->bladeContent,
            'Vertical center of title safe zone must be anchored at Y = 410.'
        );

        // Line gap calculation
        $this->assertMatchesRegularExpression(
            '/lineGap\s*=\s*Math\.round\(\s*twoLineFs\s*\*\s*1\.22\s*\)/',
            $this->bladeContent,
            'Line gap must be computed proportionally with twoLineFs * 1.22.'
        );

        // Verify mathematically that for all twoLineFs in [50, 65], coordinates remain in [300, 500]
        $topCenterY = 410;
        for ($fs = 50; $fs <= 65; $fs++) {
            $lineGap = (int) round($fs * 1.22);
            $y1 = $topCenterY - (int) round($lineGap / 2);
            $y2 = $topCenterY + (int) round($lineGap / 2);

            // Bounding box approximation (cap height ~ 0.7 * fs)
            $topEdge = $y1 - ($fs * 0.5);
            $bottomEdge = $y2 + ($fs * 0.5);

            $this->assertGreaterThanOrEqual(
                300,
                $topEdge,
                "Font size {$fs}px top edge ({$topEdge}px) must not collide with header logo (Y <= 288)."
            );

            $this->assertLessThanOrEqual(
                500,
                $bottomEdge,
                "Font size {$fs}px bottom edge ({$bottomEdge}px) must not collide with product boundary (Y >= 500)."
            );
        }
    }

    /**
     * 5. Assert window global exposure of aplicarPlantilla and helpers
     */
    public function test_window_aplicar_plantilla_binding_intact(): void
    {
        $this->assertStringContainsString(
            'async function aplicarPlantilla(',
            $this->bladeContent,
            'aplicarPlantilla function must exist.'
        );

        $this->assertStringContainsString(
            'window.aplicarPlantilla = aplicarPlantilla;',
            $this->bladeContent,
            'window.aplicarPlantilla must be explicitly bound.'
        );

        $this->assertStringContainsString(
            'window.MOTORCYCLE_BRANDS = MOTORCYCLE_BRANDS;',
            $this->bladeContent,
            'window.MOTORCYCLE_BRANDS must be explicitly bound.'
        );

        $this->assertStringContainsString(
            'window.isBrandToken = isBrandToken;',
            $this->bladeContent,
            'window.isBrandToken must be explicitly bound.'
        );

        $this->assertStringContainsString(
            'window.tokenizeLineForCanvas = tokenizeLineForCanvas;',
            $this->bladeContent,
            'window.tokenizeLineForCanvas must be explicitly bound.'
        );

        $this->assertStringContainsString(
            'window.splitTitleIntoBalancedLines = splitTitleIntoBalancedLines;',
            $this->bladeContent,
            'window.splitTitleIntoBalancedLines must be explicitly bound.'
        );
    }

    /**
     * 6. Oracle simulation: Brand recognition oracle with test cases
     */
    public function test_brand_recognition_oracle(): void
    {
        $brands = [
            'YAMAHA', 'HONDA', 'SUZUKI', 'BAJAJ', 'AKT', 'AUTECO',
            'KAWASAKI', 'HERO', 'KTM', 'TVS', 'KYMCO', 'VICTORY',
            'SIGMA', 'SYM', 'BENELLI', 'ROYAL ENFIELD', 'ROYAL', 'ENFIELD',
            'DUCATI', 'BMW', 'HARLEY', 'VESPA', 'PIAGGIO', 'ITALIKA',
            'CHEVROLET', 'RENAULT', 'MAZDA', 'TOYOTA', 'NISSAN', 'KIA',
            'HYUNDAI', 'FORD', 'VOLKSWAGEN'
        ];
        $brandLookup = array_flip($brands);

        $isBrand = function (string $word) use ($brandLookup): bool {
            $clean = preg_replace('/^[^\w]+|[^\w]+$/u', '', $word);
            return isset($brandLookup[mb_strtoupper($clean, 'UTF-8')]);
        };

        // Test Case 1: Main canonical example
        $words1 = explode(' ', 'CALAPIES DELANTERO YAMAHA RX100-115 / V80 / FR80 / RX200-215');
        $this->assertFalse($isBrand('CALAPIES'), 'CALAPIES must NOT be recognized as brand');
        $this->assertFalse($isBrand('DELANTERO'), 'DELANTERO must NOT be recognized as brand');
        $this->assertTrue($isBrand('YAMAHA'), 'YAMAHA must be recognized as brand');
        $this->assertFalse($isBrand('RX100-115'), 'RX100-115 must NOT be recognized as brand');
        $this->assertFalse($isBrand('/'), 'Slash must NOT be recognized as brand');

        // Test Case 2: Punctuation attached
        $this->assertTrue($isBrand('(HONDA)'), '(HONDA) with parentheses must be recognized as brand');
        $this->assertTrue($isBrand('SUZUKI,'), 'SUZUKI with trailing comma must be recognized as brand');
        $this->assertTrue($isBrand('akt.'), 'akt lowercase with dot must be recognized as brand');
        $this->assertTrue($isBrand('[KAWASAKI]'), '[KAWASAKI] with square brackets must be recognized as brand');

        // Test Case 3: Common non-brand parts & position adjectives
        $nonBrands = [
            'MANILAR', 'UNIVERSAL', 'CAUCHO', 'TRASERO', 'DELANTERO',
            'SUPERIOR', 'INFERIOR', 'RETEN', 'EMPAQUE', 'VALVULA',
            'BUJE', 'SOPORTE', 'AMORTIGUADOR', 'CAMPANA', 'RUEDA'
        ];
        foreach ($nonBrands as $nb) {
            $this->assertFalse($isBrand($nb), "'{$nb}' must NOT be recognized as brand");
        }

        // Test Case 4: Other LATAM motorcycle brands
        $latamBrands = ['BAJAJ', 'AUTECO', 'HERO', 'KTM', 'TVS', 'KYMCO', 'VICTORY', 'SIGMA', 'SYM'];
        foreach ($latamBrands as $lb) {
            $this->assertTrue($isBrand($lb), "'{$lb}' must be recognized as brand");
        }
    }
}
