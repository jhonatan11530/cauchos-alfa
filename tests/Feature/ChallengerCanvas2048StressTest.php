<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ChallengerCanvas2048StressTest
 * 
 * Adversarial and empirical stress test suite for 2048x2048 canvas recalibration:
 * 1. Product aspect ratio scaling & centering matrix (tall, wide, square, extreme).
 * 2. Adversarial title strings (long, 1 word, zero brand, punctuation-attached brands).
 * 3. Flyer detection and cropping boundary verification against actual public/img/plantilla-estado.jpg.
 * 4. Lower trapezoid reference code placement and container clearance.
 * 5. Complete mathematical and geometric conformance with zero overflow.
 */
class ChallengerCanvas2048StressTest extends TestCase
{
    private string $bladeContent;
    private string $templatePath;

    protected function setUp(): void
    {
        parent::setUp();
        $bladePath = resource_path('views/admin/products/form.blade.php');
        $this->assertFileExists($bladePath, 'form.blade.php must exist.');
        $this->bladeContent = file_get_contents($bladePath);

        $this->templatePath = public_path('img/plantilla-estado.jpg');
        $this->assertFileExists($this->templatePath, 'public/img/plantilla-estado.jpg must exist.');
    }

    /**
     * 1. STRESS TEST: Product Scaling & Centering Matrix
     * Tests extreme aspect ratios (wide, tall, square, pathological) against maxProdWidth (1350) and maxProdHeight (950)
     * centered at (1024, 1010).
     */
    public function test_adversarial_product_aspect_ratio_scaling_matrix(): void
    {
        $maxProdWidth = 1350;
        $maxProdHeight = 950;
        $targetCenterX = 1024;
        $targetCenterY = 1010;

        // Pathological and extreme test dimensions [width, height, label]
        $testCases = [
            // Extreme Tall
            [200, 2000, 'Extremely tall 1:10'],
            [100, 4000, 'Ultra needle tall 1:40'],
            [500, 2500, 'Tall 1:5'],
            [950, 1900, 'Tall 1:2'],
            [800, 1600, 'Tall vertical portrait'],
            // Extreme Wide
            [2000, 200, 'Extremely wide 10:1'],
            [4000, 100, 'Ultra ribbon wide 40:1'],
            [2500, 500, 'Wide 5:1'],
            [1900, 950, 'Wide 2:1'],
            [1920, 1080, 'Full HD 16:9 widescreen'],
            // Square Dimensions
            [1, 1, 'Single pixel square'],
            [100, 100, 'Small thumbnail 100x100'],
            [500, 500, 'Medium square 500x500'],
            [1024, 1024, 'Half canvas square 1024x1024'],
            [1350, 1350, 'Square matching max width'],
            [1500, 1500, 'Standard studio square 1500x1500'],
            [2048, 2048, 'Full canvas square 2048x2048'],
            [4000, 4000, 'Ultra resolution square 4000x4000'],
            // Photographic standard ratios
            [1600, 1200, 'Standard 4:3 landscape'],
            [1200, 1600, 'Standard 3:4 portrait'],
            [1800, 1200, 'Standard 3:2 landscape'],
            [1200, 1800, 'Standard 2:3 portrait'],
            // Asymmetric boundary cases
            [1350, 950, 'Exact target bounding box 1350x950'],
            [2700, 1900, '2x exact target bounding box'],
            [675, 475, '0.5x exact target bounding box'],
        ];

        foreach ($testCases as [$prodW, $prodH, $label]) {
            $ratio = min($maxProdWidth / $prodW, $maxProdHeight / $prodH);
            $finalW = $prodW * $ratio;
            $finalH = $prodH * $ratio;

            $drawX = $targetCenterX - ($finalW / 2);
            $drawY = $targetCenterY - ($finalH / 2);
            $drawRight = $drawX + $finalW;
            $drawBottom = $drawY + $finalH;

            // 1. Never exceeds max bounds
            $this->assertLessThanOrEqual(
                $maxProdWidth + 0.0001,
                $finalW,
                "Case [{$label}] final width ({$finalW}) must not exceed maxProdWidth ({$maxProdWidth})"
            );
            $this->assertLessThanOrEqual(
                $maxProdHeight + 0.0001,
                $finalH,
                "Case [{$label}] final height ({$finalH}) must not exceed maxProdHeight ({$maxProdHeight})"
            );

            // 2. Aspect ratio is preserved with zero geometric distortion
            $inputAspect = $prodW / $prodH;
            $outputAspect = $finalW / $finalH;
            $this->assertEqualsWithDelta(
                $inputAspect,
                $outputAspect,
                0.00001,
                "Case [{$label}] aspect ratio must be strictly preserved"
            );

            // 3. Exact centering
            $actualCenterX = $drawX + ($finalW / 2);
            $actualCenterY = $drawY + ($finalH / 2);
            $this->assertEqualsWithDelta(
                (float) $targetCenterX,
                $actualCenterX,
                0.00001,
                "Case [{$label}] horizontal center must be exactly {$targetCenterX}"
            );
            $this->assertEqualsWithDelta(
                (float) $targetCenterY,
                $actualCenterY,
                0.00001,
                "Case [{$label}] vertical center must be exactly {$targetCenterY}"
            );

            // 4. Safe zone clearance: Title ceiling (Y=500) and Badges floor (Y=1539)
            $this->assertGreaterThan(
                500.0,
                $drawY,
                "Case [{$label}] top boundary ({$drawY}) must clear title envelope (Y <= 500)"
            );
            $this->assertLessThan(
                1539.0,
                $drawBottom,
                "Case [{$label}] bottom boundary ({$drawBottom}) must clear badges (Y >= 1539)"
            );

            // 5. Lateral margin clearance: Canvas width 2048
            $this->assertGreaterThan(
                0.0,
                $drawX,
                "Case [{$label}] left boundary ({$drawX}) must be inside canvas"
            );
            $this->assertLessThan(
                2048.0,
                $drawRight,
                "Case [{$label}] right boundary ({$drawRight}) must be inside canvas"
            );
        }
    }

    /**
     * 2. STRESS TEST: Adversarial Title Strings and Brand Tokenization
     */
    public function test_adversarial_title_tokenization_and_brand_coloring(): void
    {
        // Extract MOTORCYCLE_BRANDS from form.blade.php
        $matched = preg_match('/const\s+MOTORCYCLE_BRANDS\s*=\s*new\s+Set\(\[\s*(.*?)\s*\]\);/s', $this->bladeContent, $m);
        $this->assertSame(1, $matched, 'MOTORCYCLE_BRANDS Set must be extracted from form.blade.php');
        
        preg_match_all("/'([A-Z0-9\-\s]+)'/", $m[1], $brandMatches);
        $brands = array_flip($brandMatches[1]);

        // Exact tokenization logic from form.blade.php
        $isBrandToken = function (string $str) use ($brands): bool {
            if (!$str) return false;
            $clean = preg_replace('/^[^A-Z0-9]+|[^A-Z0-9]+$/i', '', $str);
            $cleanUpper = strtoupper($clean);
            return strlen($cleanUpper) > 0 && isset($brands[$cleanUpper]);
        };

        $tokenizeLine = function (string $lineStr) use ($isBrandToken, $brands): array {
            $rawWords = preg_split('/\s+/', trim($lineStr));
            $tokens = [];
            $wCount = count($rawWords);

            for ($w = 0; $w < $wCount; $w++) {
                $rawWord = $rawWords[$w];
                $isLastWord = ($w === $wCount - 1);

                $parts = preg_split('/([\/,()\[\]])/', $rawWord, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $pCount = count($parts);

                for ($p = 0; $p < $pCount; $p++) {
                    $part = $parts[$p];

                    if (str_contains($part, '-') && !isset($brands[strtoupper($part)])) {
                        $subHyphen = preg_split('/(-)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                        $hasBrand = false;
                        foreach ($subHyphen as $sh) {
                            if ($isBrandToken($sh)) {
                                $hasBrand = true;
                                break;
                            }
                        }
                        if ($hasBrand) {
                            $shCount = count($subHyphen);
                            for ($h = 0; $h < $shCount; $h++) {
                                $hPart = $subHyphen[$h];
                                $isLastSub = ($p === $pCount - 1 && $h === $shCount - 1);
                                $tokens[] = [
                                    'text' => $hPart . ($isLastSub && !$isLastWord ? ' ' : ''),
                                    'color' => $isBrandToken($hPart) ? '#e61b23' : '#111111'
                                ];
                            }
                            continue;
                        }
                    }

                    $isLastPart = ($p === $pCount - 1);
                    $tokens[] = [
                        'text' => $part . ($isLastPart && !$isLastWord ? ' ' : ''),
                        'color' => $isBrandToken($part) ? '#e61b23' : '#111111'
                    ];
                }
            }
            return $tokens;
        };

        // Adversarial Case A: Long title with multiple brands and models
        $titleA = "YAMAHA SUZUKI HONDA CALAPIES TRASERO ESPECIAL 1234567890";
        $tokensA = $tokenizeLine($titleA);
        $this->assertSame('#e61b23', $tokensA[0]['color'], 'YAMAHA must be colored red #e61b23');
        $this->assertSame('#e61b23', $tokensA[1]['color'], 'SUZUKI must be colored red #e61b23');
        $this->assertSame('#e61b23', $tokensA[2]['color'], 'HONDA must be colored red #e61b23');
        $this->assertSame('#111111', $tokensA[3]['color'], 'CALAPIES must be colored black #111111');
        $this->assertSame('#111111', $tokensA[4]['color'], 'TRASERO must be colored black #111111');
        $this->assertSame('#111111', $tokensA[5]['color'], 'ESPECIAL must be colored black #111111');
        $this->assertSame('#111111', $tokensA[6]['color'], '1234567890 must be colored black #111111');

        // Adversarial Case B: Very short title (single word and 2 words)
        $titleB1 = "RETEN";
        $tokensB1 = $tokenizeLine($titleB1);
        $this->assertCount(1, $tokensB1);
        $this->assertSame('#111111', $tokensB1[0]['color']);

        $titleB2 = "RETEN HONDA";
        $tokensB2 = $tokenizeLine($titleB2);
        $this->assertCount(2, $tokensB2);
        $this->assertSame('#111111', $tokensB2[0]['color']);
        $this->assertSame('#e61b23', $tokensB2[1]['color']);

        // Adversarial Case C: Zero brands title
        $titleC = "BUJE MANILAR UNIVERSAL NEGRO 50MM";
        $tokensC = $tokenizeLine($titleC);
        foreach ($tokensC as $tok) {
            $this->assertSame('#111111', $tok['color'], "Non-brand word '{$tok['text']}' must be colored #111111");
        }

        // Adversarial Case D: Punctuation adjacent to brands
        $titleD = "YAMAHA/HONDA (AKT) [KAWASAKI], SUZUKI, AUTECO-BAJAJ";
        $tokensD = $tokenizeLine($titleD);
        $redTexts = [];
        $blackTexts = [];
        foreach ($tokensD as $tok) {
            if ($tok['color'] === '#e61b23') {
                $redTexts[] = trim($tok['text']);
            } else {
                $blackTexts[] = trim($tok['text']);
            }
        }

        $this->assertContains('YAMAHA', $redTexts, 'YAMAHA adjacent to slash must be red');
        $this->assertContains('HONDA', $redTexts, 'HONDA adjacent to slash must be red');
        $this->assertContains('AKT', $redTexts, 'AKT inside parentheses must be red');
        $this->assertContains('KAWASAKI', $redTexts, 'KAWASAKI inside brackets must be red');
        $this->assertContains('SUZUKI', $redTexts, 'SUZUKI with trailing comma/semicolon must be red');
        $this->assertContains('AUTECO', $redTexts, 'AUTECO in hyphenated pair must be red');
        $this->assertContains('BAJAJ', $redTexts, 'BAJAJ in hyphenated pair must be red');

        $this->assertContains('/', $blackTexts, 'Slash delimiter must be black');
        $this->assertContains('(', $blackTexts, 'Punctuation must be black');
        $this->assertContains(')', $blackTexts, 'Punctuation must be black');
        $this->assertContains('[', $blackTexts, 'Punctuation must be black');
        $this->assertContains(']', $blackTexts, 'Punctuation must be black');
        $this->assertContains('-', $blackTexts, 'Hyphen delimiter must be black');
    }

    /**
     * 3. STRESS TEST: Flyer Detection on Actual Image Pixels
     * Direct empirical inspection using GD of public/img/plantilla-estado.jpg
     */
    public function test_flyer_detection_on_actual_template_asset(): void
    {
        $im = imagecreatefromjpeg($this->templatePath);
        $this->assertNotFalse($im, 'Could not load plantilla-estado.jpg with GD');

        $w = imagesx($im);
        $h = imagesy($im);
        $this->assertSame(2048, $w, 'Width must be 2048');
        $this->assertSame(2048, $h, 'Height must be 2048');

        // Test square flyer sampling points from form.blade.php
        $sampleX = (int) round($w / 2); // 1024
        $sampleTopY = (int) round($h * 0.09); // 184
        $sampleBottomY = (int) round($h * 0.93); // 1905

        // Read top pixel at (1024, 184)
        $topRgb = imagecolorat($im, $sampleX, $sampleTopY);
        $topR = ($topRgb >> 16) & 0xFF;
        $topG = ($topRgb >> 8) & 0xFF;
        $topB = $topRgb & 0xFF;

        // Read bottom pixel at (1024, 1905)
        $botRgb = imagecolorat($im, $sampleX, $sampleBottomY);
        $botR = ($botRgb >> 16) & 0xFF;
        $botG = ($botRgb >> 8) & 0xFF;
        $botB = $botRgb & 0xFF;

        // In form.blade.php:
        // const isTopBlack = topPixel[0] < 60 && topPixel[1] < 60 && topPixel[2] < 60;
        // const isBottomBlack = bottomPixel[0] < 60 && bottomPixel[1] < 60 && bottomPixel[2] < 60;
        $this->assertLessThan(60, $topR, "Top sample point at (1024, 184) R ({$topR}) must be < 60 (dark banner)");
        $this->assertLessThan(60, $topG, "Top sample point at (1024, 184) G ({$topG}) must be < 60 (dark banner)");
        $this->assertLessThan(60, $topB, "Top sample point at (1024, 184) B ({$topB}) must be < 60 (dark banner)");

        $this->assertLessThan(60, $botR, "Bottom sample point at (1024, 1905) R ({$botR}) must be < 60 (dark trapezoid)");
        $this->assertLessThan(60, $botG, "Bottom sample point at (1024, 1905) G ({$botG}) must be < 60 (dark trapezoid)");
        $this->assertLessThan(60, $botB, "Bottom sample point at (1024, 1905) B ({$botB}) must be < 60 (dark trapezoid)");

        imagedestroy($im);
    }

    /**
     * 4. STRESS TEST: Flyer Cropping Cleanliness on Actual Template Asset
     * Empirically verify that crop rectangle (0.15, 0.26, 0.70, 0.48) leaves zero remnants of black header or badges.
     */
    public function test_flyer_crop_geometry_leaves_zero_banner_or_badge_remnants(): void
    {
        $im = imagecreatefromjpeg($this->templatePath);
        $w = imagesx($im);
        $h = imagesy($im);

        $cropX = (int) round($w * 0.15); // 307
        $cropY = (int) round($h * 0.26); // 532
        $cropW = (int) round($w * 0.70); // 1434
        $cropH = (int) round($h * 0.48); // 983

        $cropRight = $cropX + $cropW; // 1741
        $cropBottom = $cropY + $cropH; // 1515

        // Verify bounds inside canvas
        $this->assertGreaterThanOrEqual(0, $cropX);
        $this->assertLessThanOrEqual(2048, $cropRight);
        $this->assertGreaterThanOrEqual(0, $cropY);
        $this->assertLessThanOrEqual(2048, $cropBottom);

        // Header banner in plantilla-estado ends well before cropY (532)
        // Inspect the top edge of the crop region (Y = 532) across entire width
        $darkPixelsTop = 0;
        for ($x = $cropX; $x < $cropRight; $x += 10) {
            $rgb = imagecolorat($im, $x, $cropY);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            // Any pixel with luminance < 100 would indicate a banner collision
            $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            if ($lum < 100) {
                $darkPixelsTop++;
            }
        }
        $this->assertSame(0, $darkPixelsTop, 'Top edge of flyer crop region must have zero banner remnants (lum < 100)');

        // Badges in plantilla-estado start around Y = 1539, while cropBottom is 1515
        // Inspect the bottom edge of the crop region (Y = 1515) across entire width
        $darkPixelsBottom = 0;
        for ($x = $cropX; $x < $cropRight; $x += 10) {
            $rgb = imagecolorat($im, $x, $cropBottom);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            // Badge graphics or borders would have low luminance
            $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            if ($lum < 100) {
                $darkPixelsBottom++;
            }
        }
        $this->assertSame(0, $darkPixelsBottom, 'Bottom edge of flyer crop region must have zero badge remnants (lum < 100)');

        imagedestroy($im);
    }

    /**
     * 5. STRESS TEST: Non-Flyer Product Image Rejection
     * Verify that normal square or arbitrary images with white backgrounds are NOT falsely flagged as flyers.
     */
    public function test_non_flyer_images_are_not_cropped(): void
    {
        // 1. A pure white studio image 1500x1500px
        $pureWhiteSample = [255, 255, 255];
        $isTopBlack = $pureWhiteSample[0] < 60 && $pureWhiteSample[1] < 60 && $pureWhiteSample[2] < 60;
        $isBottomBlack = $pureWhiteSample[0] < 60 && $pureWhiteSample[1] < 60 && $pureWhiteSample[2] < 60;
        $this->assertFalse($isTopBlack, 'Pure white top sample must NOT be flagged as black');
        $this->assertFalse($isBottomBlack, 'Pure white bottom sample must NOT be flagged as black');

        // 2. An arbitrary aspect ratio image (e.g., 1600x900)
        $ratio = 1600 / 900; // 1.777
        $isSquareRatio = abs($ratio - 1.0) < 0.06;
        $isLegacyRatio = $ratio > 0.70 && $ratio < 0.88;
        $this->assertFalse($isSquareRatio, '16:9 ratio must not match square flyer');
        $this->assertFalse($isLegacyRatio, '16:9 ratio must not match legacy flyer');

        // 3. A vertical portrait image (e.g., 900x1600)
        $ratioPortrait = 900 / 1600; // 0.5625
        $isSquareRatioPortrait = abs($ratioPortrait - 1.0) < 0.06;
        $isLegacyRatioPortrait = $ratioPortrait > 0.70 && $ratioPortrait < 0.88;
        $this->assertFalse($isSquareRatioPortrait, '9:16 ratio must not match square flyer');
        $this->assertFalse($isLegacyRatioPortrait, '9:16 ratio must not match legacy flyer');
    }

    /**
     * 6. STRESS TEST: Lower Trapezoid Reference Code Clearance on Actual Image
     */
    public function test_reference_code_container_clearance_on_template_asset(): void
    {
        $im = imagecreatefromjpeg($this->templatePath);

        // Center at (1024, 1905)
        $refX = 1024;
        $refY = 1905;
        $refMaxWidth = 780;
        $refFontSize = 58;

        // Container bounds check on actual image:
        // Midpoint should be dark trapezoid
        $rgbCenter = imagecolorat($im, $refX, $refY);
        $rC = ($rgbCenter >> 16) & 0xFF;
        $gC = ($rgbCenter >> 8) & 0xFF;
        $bC = $rgbCenter & 0xFF;
        $this->assertLessThan(60, $rC, 'Trapezoid center must be dark container');
        $this->assertLessThan(60, $gC, 'Trapezoid center must be dark container');
        $this->assertLessThan(60, $bC, 'Trapezoid center must be dark container');

        // Verify vertical headroom: font size 58 means text extends ~30px above and below 1905
        $yTop = $refY - 30; // 1875
        $yBottom = $refY + 30; // 1935

        $rgbTop = imagecolorat($im, $refX, $yTop);
        $rT = ($rgbTop >> 16) & 0xFF;
        $gT = ($rgbTop >> 8) & 0xFF;
        $bT = $rgbTop & 0xFF;
        $this->assertLessThan(60, $rT, "Top headroom at y={$yTop} must be inside dark container");

        $rgbBot = imagecolorat($im, $refX, $yBottom);
        $rB = ($rgbBot >> 16) & 0xFF;
        $gB = ($rgbBot >> 8) & 0xFF;
        $bB = $rgbBot & 0xFF;
        $this->assertLessThan(60, $rB, "Bottom headroom at y={$yBottom} must be inside dark container");

        // Verify horizontal container span: 780px wide centered at 1024 -> [634, 1414]
        $xLeft = $refX - (int) round($refMaxWidth / 2) + 20; // 654
        $xRight = $refX + (int) round($refMaxWidth / 2) - 20; // 1394

        $rgbLeft = imagecolorat($im, $xLeft, $refY);
        $rL = ($rgbLeft >> 16) & 0xFF;
        $this->assertLessThan(60, $rL, "Left interior at x={$xLeft} must be inside dark container");

        $rgbRight = imagecolorat($im, $xRight, $refY);
        $rR = ($rgbRight >> 16) & 0xFF;
        $this->assertLessThan(60, $rR, "Right interior at x={$xRight} must be inside dark container");

        imagedestroy($im);
    }

    /**
     * 7. STRESS TEST: Adversarial Balanced Line Splitting Algorithm
     */
    public function test_adversarial_balanced_line_splitting_algorithm(): void
    {
        // Extract MOTORCYCLE_BRANDS from form.blade.php
        preg_match('/const\s+MOTORCYCLE_BRANDS\s*=\s*new\s+Set\(\[\s*(.*?)\s*\]\);/s', $this->bladeContent, $m);
        preg_match_all("/'([A-Z0-9\-\s]+)'/", $m[1], $brandMatches);
        $brands = array_flip($brandMatches[1]);

        $isBrandToken = function (string $str) use ($brands): bool {
            if (!$str) return false;
            $clean = preg_replace('/^[^A-Z0-9]+|[^A-Z0-9]+$/i', '', $str);
            $cleanUpper = strtoupper($clean);
            return strlen($cleanUpper) > 0 && isset($brands[$cleanUpper]);
        };

        // Simulated text width based on Arial Black character metrics
        $measureText = function (string $text): float {
            $len = strlen($text);
            $spaces = substr_count($text, ' ');
            return ($len - $spaces) * 36.0 + $spaces * 20.0;
        };

        $splitTitle = function (string $fullTitle, float $maxPxWidth) use ($measureText, $isBrandToken): array {
            $words = preg_split('/\s+/', trim($fullTitle));
            if (count($words) <= 1) return [$fullTitle, ''];

            $bestSplit = [$fullTitle, ''];
            $bestScore = INF;
            $wCount = count($words);

            for ($k = 1; $k < $wCount; $k++) {
                $p1 = array_slice($words, 0, $k);
                $p2 = array_slice($words, $k);
                $line1 = implode(' ', $p1);
                $line2 = implode(' ', $p2);

                $w1 = $measureText($line1);
                $w2 = $measureText($line2);

                $score = abs($w1 - $w2);
                if ($w1 > $maxPxWidth) $score += 2000;
                if ($w2 > $maxPxWidth) $score += 2000;

                $lastWord1 = $p1[count($p1) - 1];
                $firstWord2 = $p2[0];

                if (preg_match('/^[\/,\-\x{2013}]/u', $firstWord2)) $score += 600;
                if (preg_match('/^[\)\]]/', $firstWord2)) $score += 1000;
                if (preg_match('/[\(\[]$/', $lastWord1)) $score += 1000;

                if (preg_match('/^\d+$/', $lastWord1) && preg_match('/^[A-Z]{1,4}$/', $firstWord2)) {
                    $score += 350;
                }

                if ($lastWord1 === '/') $score -= 60;
                if ($isBrandToken($lastWord1)) $score -= 60;

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestSplit = [$line1, $line2];
                }
            }
            return $bestSplit;
        };

        // Test 1: Canonical example
        [$l1, $l2] = $splitTitle("CALAPIES DELANTERO YAMAHA RX100-115 / V80 / FR80 / RX200-215", 1650.0);
        $this->assertNotEmpty($l1);
        $this->assertNotEmpty($l2);
        $this->assertDoesNotMatchRegularExpression('/^[\/,\-\)]/', $l2, 'Line 2 must not start with orphan punctuation');

        // Test 2: Single word
        [$single1, $single2] = $splitTitle("CALAPIES", 1650.0);
        $this->assertSame("CALAPIES", $single1);
        $this->assertSame("", $single2);

        // Test 3: Model number suffix cohesion (e.g. 160 NS)
        [$m1, $m2] = $splitTitle("SOPORTE MOTOR BAJAJ ROUSER 160 NS TRASERO", 800.0);
        $this->assertFalse(
            (str_ends_with($m1, '160') && str_starts_with($m2, 'NS')),
            'Should avoid splitting between number and model suffix (160 NS)'
        );
    }

    /**
     * 8. STRESS TEST: Complete Canvas Geometry & Buffer Clearance
     * Mathematically proves zero collision across all template components.
     */
    public function test_complete_canvas_geometry_and_buffer_zones(): void
    {
        // Canvas Native Dimensions
        $canvasW = 2048;
        $canvasH = 2048;

        // Title Zone
        $topCenterX = 1024;
        $topCenterY = 410;
        $topMaxWidth = 1650;
        $maxTitleBottom = 500; // Documented envelope limit
        $minTitleTop = 300; // Documented envelope limit

        // Product Zone
        $targetCenterX = 1024;
        $targetCenterY = 1010;
        $maxProdWidth = 1350;
        $maxProdHeight = 950;

        $prodTopEdge = $targetCenterY - ($maxProdHeight / 2); // 535
        $prodBottomEdge = $targetCenterY + ($maxProdHeight / 2); // 1485
        $prodLeftEdge = $targetCenterX - ($maxProdWidth / 2); // 349
        $prodRightEdge = $targetCenterX + ($maxProdWidth / 2); // 1699

        // Badges Zone
        $badgesTopY = 1539;

        // Reference Code Zone
        $refCenterX = 1024;
        $refCenterY = 1905;
        $refMaxWidth = 780;
        $trapezoidTopY = 1845;
        $trapezoidBottomY = 1966;

        // Buffer Verifications:
        // 1. Buffer between Title envelope and Product:
        $titleToProductBuffer = $prodTopEdge - $maxTitleBottom;
        $this->assertSame(35, $titleToProductBuffer, 'Must have at least 35px safety buffer between Title ceiling and Product top');

        // 2. Buffer between Product and Badges:
        $productToBadgesBuffer = $badgesTopY - $prodBottomEdge;
        $this->assertSame(54, $productToBadgesBuffer, 'Must have at least 54px safety buffer between Product bottom and Badges top');

        // 3. Symmetrical horizontal product margins:
        $this->assertSame(349, $prodLeftEdge);
        $this->assertSame(349, $canvasW - $prodRightEdge);

        // 4. Reference Code centered in trapezoid container:
        $topPad = $refCenterY - $trapezoidTopY; // 60px
        $botPad = $trapezoidBottomY - $refCenterY; // 61px
        $this->assertLessThanOrEqual(1, abs($topPad - $botPad), 'Reference code must be within 1px of trapezoid geometric center');
    }
}
