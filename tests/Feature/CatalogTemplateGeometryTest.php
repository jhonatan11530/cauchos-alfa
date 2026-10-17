<?php

namespace Tests\Feature;

use Tests\TestCase;

class CatalogTemplateGeometryTest extends TestCase
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
     * 1. Assert canvas resolution is set dynamically to plantilla image dimensions (2048x2048)
     */
    public function test_canvas_resolution_configured_for_native_2048x2048(): void
    {
        // Blade dynamic canvas configuration
        $this->assertStringContainsString('canvas.width = imgPlantilla.width;', $this->bladeContent);
        $this->assertStringContainsString('canvas.height = imgPlantilla.height;', $this->bladeContent);

        // Verify template asset on disk
        $assetPath = public_path('img/plantilla-estado.jpg');
        $this->assertFileExists($assetPath, 'Template asset must exist at public/img/plantilla-estado.jpg');
        $info = getimagesize($assetPath);
        $this->assertNotFalse($info, 'Template asset must be readable by getimagesize');
        $this->assertSame(2048, $info[0], 'Native template width must be 2048px');
        $this->assertSame(2048, $info[1], 'Native template height must be 2048px');
        $this->assertSame(IMAGETYPE_JPEG, $info[2], 'Native template must be JPEG');
    }

    /**
     * 2. Assert product centering (1024, 1010) and bounds (1350x950) with multiply blend mode
     */
    public function test_product_centering_and_maximum_bounds_in_blade(): void
    {
        $this->assertMatchesRegularExpression('/targetCenterX\s*=\s*1024;/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/targetCenterY\s*=\s*1010;/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/maxProdWidth\s*=\s*1350;/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/maxProdHeight\s*=\s*950;/', $this->bladeContent);
        $this->assertStringContainsString("ctx.globalCompositeOperation = 'multiply';", $this->bladeContent);

        // Mathematical clearance verification
        $centerX = 1024;
        $centerY = 1010;
        $maxW = 1350;
        $maxH = 950;

        $topEdge = $centerY - ($maxH / 2); // 535
        $bottomEdge = $centerY + ($maxH / 2); // 1485
        $leftEdge = $centerX - ($maxW / 2); // 349
        $rightEdge = $centerX + ($maxW / 2); // 1699

        // Title ceiling is Y <= 500
        $this->assertGreaterThan(500, $topEdge, 'Product top edge must remain below title ceiling (Y=500)');
        // Badges start at Y = 1539
        $this->assertLessThan(1539, $bottomEdge, 'Product bottom edge must remain above badges (Y=1539)');
        // Symmetrical margins
        $this->assertSame($leftEdge, 2048 - $rightEdge, 'Product bounding box must have symmetrical left and right margins');
        $this->assertEquals(349, $leftEdge, 'Product left margin must be exactly 349px');
    }

    /**
     * 3. Assert title positioning at (1024, 410), max width 1650, and proportional typography
     */
    public function test_title_positioning_and_maximum_width(): void
    {
        $this->assertMatchesRegularExpression('/topCenterX\s*=\s*1024;/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/topCenterY\s*=\s*410;/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/topMaxWidth\s*=\s*1650;/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/singleLineFs\s*=\s*75;/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/singleLineFs\s*>=\s*50/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/twoLineFs\s*=\s*64;/', $this->bladeContent);
    }

    /**
     * 4. Assert reference code at (1024, 1905) inside lower trapezoid in white font
     */
    public function test_reference_code_position_and_color(): void
    {
        $this->assertMatchesRegularExpression('/fillText\(\s*refCode\s*,\s*1024\s*,\s*1905\s*\)/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/refMaxWidth\s*=\s*780;/', $this->bladeContent);
        $this->assertMatchesRegularExpression('/refFontSize\s*=\s*58;/', $this->bladeContent);
        $this->assertStringContainsString("ctx.fillStyle = '#FFFFFF';", $this->bladeContent);
        $this->assertStringContainsString("ctx.textAlign = 'center';", $this->bladeContent);
        $this->assertStringContainsString("ctx.textBaseline = 'middle';", $this->bladeContent);
    }

    /**
     * 5. Assert square 2048x2048 flyer detection and clean extraction while preserving legacy rectangular
     */
    public function test_flyer_detection_supports_square_2048_aspect_ratio(): void
    {
        // In extractCleanProductIfFlyer
        $this->assertStringContainsString('isSquareFlyer', $this->bladeContent);
        $this->assertStringContainsString('isLegacyFlyer', $this->bladeContent);
        $this->assertMatchesRegularExpression('/img\.naturalWidth\s*===\s*2048\s*&&\s*img\.naturalHeight\s*===\s*2048/', $this->bladeContent);
        $this->assertMatchesRegularExpression(
            '/function\s+extractCleanProductIfFlyer[\s\S]*?const\s+canvas\s*=\s*document\.createElement\([\'"]canvas[\'"]\)/',
            $this->bladeContent,
            'extractCleanProductIfFlyer must instantiate a canvas element before assigning width/height.'
        );

        // Square crop factors (0.15, 0.26, 0.70, 0.48)
        $this->assertStringContainsString('img.naturalWidth * 0.15', $this->bladeContent);
        $this->assertStringContainsString('img.naturalHeight * 0.26', $this->bladeContent);
        $this->assertStringContainsString('img.naturalWidth * 0.70', $this->bladeContent);
        $this->assertStringContainsString('img.naturalHeight * 0.48', $this->bladeContent);

        // In processImageToStudioWhite
        $this->assertStringContainsString('isSquareRatio', $this->bladeContent);
        $this->assertStringContainsString('isLegacyRatio', $this->bladeContent);
        $this->assertStringContainsString('tempImg.height * 0.09', $this->bladeContent);
        $this->assertStringContainsString('tempImg.height * 0.93', $this->bladeContent);

        // Legacy flyer support preserved
        $this->assertMatchesRegularExpression('/img\.naturalWidth\s*===\s*928\s*&&\s*img\.naturalHeight\s*===\s*1150/', $this->bladeContent);
    }

    /**
     * 6. Assert global window bindings
     */
    public function test_window_bindings_for_template_and_flyer_functions(): void
    {
        $this->assertStringContainsString('window.aplicarPlantilla = aplicarPlantilla;', $this->bladeContent);
        $this->assertStringContainsString('window.extractCleanProductIfFlyer = extractCleanProductIfFlyer;', $this->bladeContent);
        $this->assertStringContainsString('window.MOTORCYCLE_BRANDS = MOTORCYCLE_BRANDS;', $this->bladeContent);
    }
}
