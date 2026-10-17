<?php

namespace Tests\Feature;

use Tests\TestCase;

class ImageProcessingOrientationFidelityTest extends TestCase
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
     * Test that all auto-rotation heuristics and canvas rotation operations are eliminated.
     */
    public function test_auto_rotation_heuristics_are_completely_eliminated(): void
    {
        // 1. No rotate() calls on context
        $this->assertDoesNotMatchRegularExpression(
            '/oCtx\.rotate\s*\(/',
            $this->bladeContent,
            'oCtx.rotate() must be completely eliminated from form.blade.php'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/ctx\.rotate\s*\(/',
            $this->bladeContent,
            'ctx.rotate() must not exist in form.blade.php'
        );

        // 2. No Math.PI rotation angles
        $this->assertStringNotContainsString(
            'Math.PI / 2',
            $this->bladeContent,
            'Math.PI / 2 rotation constant must not exist in form.blade.php'
        );
        $this->assertStringNotContainsString(
            '-Math.PI / 2',
            $this->bladeContent,
            '-Math.PI / 2 rotation constant must not exist in form.blade.php'
        );

        // 3. No cropW > cropH rotation conditional
        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*cropW\s*>\s*cropH\s*\)/',
            $this->bladeContent,
            'The conditional rotation check if (cropW > cropH) must be eliminated'
        );

        // 4. No dimension swapping
        $this->assertDoesNotMatchRegularExpression(
            '/orientedCanvas\.width\s*=\s*cropH/',
            $this->bladeContent,
            'orientedCanvas width must never be assigned cropH (dimension swapping)'
        );
    }

    /**
     * Test natural zero-rotation drawing onto orientedCanvas.
     */
    public function test_natural_zero_rotation_canvas_drawing(): void
    {
        // Must configure orientedCanvas width and height with cropW and cropH directly
        $this->assertMatchesRegularExpression(
            '/orientedCanvas\.width\s*=\s*cropW\s*;\s*orientedCanvas\.height\s*=\s*cropH\s*;/',
            $this->bladeContent,
            'orientedCanvas must preserve natural dimensions (width=cropW, height=cropH)'
        );

        // Must fill background with #ffffff
        $this->assertMatchesRegularExpression(
            '/oCtx\.fillStyle\s*=\s*[\'"]#ffffff[\'"]\s*;\s*oCtx\.fillRect\s*\(\s*0\s*,\s*0\s*,\s*cropW\s*,\s*cropH\s*\)\s*;/',
            $this->bladeContent,
            'oCtx must fill rectangle with #ffffff matching crop dimensions'
        );

        // Must draw source image at (0, 0) without translation or offset
        $this->assertMatchesRegularExpression(
            '/oCtx\.drawImage\s*\(\s*sourceCanvas\s*,\s*cropX\s*,\s*cropY\s*,\s*cropW\s*,\s*cropH\s*,\s*0\s*,\s*0\s*,\s*cropW\s*,\s*cropH\s*\)\s*;/',
            $this->bladeContent,
            'oCtx must draw sourceCanvas with 0, 0 destination offset preserving original orientation'
        );
    }

    /**
     * Test that both mainInput and galleryInput use processImageToStudioWhite.
     */
    public function test_main_and_gallery_inputs_invoke_process_image_to_studio_white(): void
    {
        // mainInput change listener calls processImageToStudioWhite
        $this->assertMatchesRegularExpression(
            '/mainInput\.addEventListener\s*\(\s*[\'"]change[\'"].*?processImageToStudioWhite\s*\(\s*originalMainFile/s',
            $this->bladeContent,
            'mainInput event listener must invoke processImageToStudioWhite(originalMainFile)'
        );

        // galleryInput change listener calls processImageToStudioWhite
        $this->assertMatchesRegularExpression(
            '/galleryInput\.addEventListener\s*\(\s*[\'"]change[\'"].*?processImageToStudioWhite\s*\(\s*file/s',
            $this->bladeContent,
            'galleryInput event listener must invoke processImageToStudioWhite(file)'
        );

        // enhanceAllImages also calls processImageToStudioWhite
        $this->assertMatchesRegularExpression(
            '/enhanceAllImages\s*=.*?processImageToStudioWhite\s*\(\s*file/s',
            $this->bladeContent,
            'enhanceAllImages must invoke processImageToStudioWhite(file)'
        );
    }

    /**
     * Test downstream centering and scaling onto 1500x1500px square canvas.
     */
    public function test_target_canvas_centering_and_scaling_formulas(): void
    {
        $this->assertStringContainsString('const TARGET_SIZE = 1500;', $this->bladeContent);
        $this->assertStringContainsString('const maxProductDim = 1300;', $this->bladeContent);

        // scaleFactor preserves aspect ratio
        $this->assertMatchesRegularExpression(
            '/const\s+scaleFactor\s*=\s*Math\.min\s*\(\s*maxProductDim\s*\/\s*orientedCanvas\.width\s*,\s*maxProductDim\s*\/\s*orientedCanvas\.height\s*\)\s*;/',
            $this->bladeContent,
            'scaleFactor must calculate minimum scale between width and height to preserve aspect ratio'
        );

        // destX and destY center within 1500px canvas
        $this->assertMatchesRegularExpression(
            '/const\s+destX\s*=\s*Math\.round\s*\(\s*\(\s*TARGET_SIZE\s*-\s*finalW\s*\)\s*\/\s*2\s*\)\s*;/',
            $this->bladeContent,
            'destX must center the product horizontally'
        );
        $this->assertMatchesRegularExpression(
            '/const\s+destY\s*=\s*Math\.round\s*\(\s*\(\s*TARGET_SIZE\s*-\s*finalH\s*\)\s*\/\s*2\s*\)\s*;/',
            $this->bladeContent,
            'destY must center the product vertically'
        );
    }

    /**
     * Test pure white background snapping (#FFFFFF).
     */
    public function test_studio_white_background_snapping(): void
    {
        // Scans finalCanvas and snaps pixels >= 252 (or 253 for Gemini) to 255, 255, 255
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*fData\[i\]\s*>=\s*snapThreshold\s*&&\s*fData\[i\s*\+\s*1\]\s*>=\s*snapThreshold\s*&&\s*fData\[i\s*\+\s*2\]\s*>=\s*snapThreshold\s*\)/',
            $this->bladeContent,
            'Studio white threshold test must exist'
        );
        $this->assertStringContainsString('fData[i] = 255;', $this->bladeContent);
        $this->assertStringContainsString('fData[i + 1] = 255;', $this->bladeContent);
        $this->assertStringContainsString('fData[i + 2] = 255;', $this->bladeContent);
    }

    /**
     * Test preservation of external functions: buildGeminiStudioPrompt, aplicarPlantilla, flyer logic.
     */
    public function test_external_functions_remain_intact(): void
    {
        $this->assertStringContainsString('function buildGeminiStudioPrompt()', $this->bladeContent);
        $this->assertStringContainsString('window.buildGeminiStudioPrompt = buildGeminiStudioPrompt;', $this->bladeContent);
        $this->assertStringContainsString('async function aplicarPlantilla(', $this->bladeContent);
        $this->assertStringContainsString('window.aplicarPlantilla = aplicarPlantilla;', $this->bladeContent);
        $this->assertStringContainsString('async function extractCleanProductIfFlyer(', $this->bladeContent);
        $this->assertStringContainsString('window.extractCleanProductIfFlyer = extractCleanProductIfFlyer;', $this->bladeContent);
    }
}
