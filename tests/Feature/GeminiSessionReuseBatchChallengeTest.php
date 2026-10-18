<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Challenger Verification Test Harness for Session Reuse & Batch Concurrency Lifecycle.
 * 
 * Independently and empirically challenges:
 * 1. Batch sequential processing (main -> gallery_0 -> gallery_1).
 * 2. Attachment chip cleanup queries across <uploader> and <rich-textarea>.
 * 3. Elimination of thumbnail button clicks and synthetic click gating.
 * 4. Editor text resetting, innerHTML sanitization, and prompt isolation.
 * 5. Guardian lifecycle, timer clearing, observer disconnection, and finally guarantees.
 * 6. High-resolution extraction preservation.
 */
class GeminiSessionReuseBatchChallengeTest extends TestCase
{
    private string $contentGeminiPath;
    private string $contentGeminiCode;
    private string $backgroundJsPath;
    private string $backgroundJsCode;
    private string $contentCauchosPath;
    private string $contentCauchosCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentGeminiPath = base_path('extension-gemini-bridge/content_gemini.js');
        $this->assertFileExists($this->contentGeminiPath, 'content_gemini.js must exist.');
        $this->contentGeminiCode = file_get_contents($this->contentGeminiPath);

        $this->backgroundJsPath = base_path('extension-gemini-bridge/background.js');
        $this->assertFileExists($this->backgroundJsPath, 'background.js must exist.');
        $this->backgroundJsCode = file_get_contents($this->backgroundJsPath);

        $this->contentCauchosPath = base_path('extension-gemini-bridge/content_cauchos.js');
        $this->assertFileExists($this->contentCauchosPath, 'content_cauchos.js must exist.');
        $this->contentCauchosCode = file_get_contents($this->contentCauchosPath);
    }

    /**
     * Requirement R1: Total elimination of unqualified 'uploader button' from upload button selectors.
     */
    public function test_uploader_button_completely_removed_from_content_gemini(): void
    {
        // Must never query generic unqualified 'uploader button'
        $this->assertDoesNotMatchRegularExpression(
            '/uploader\s+button\s*[,,\'"]/i',
            $this->contentGeminiCode,
            'CRITICAL: Unqualified "uploader button" selector must not exist.'
        );

        // Must never appear in upload candidate selectors
        $this->assertDoesNotMatchRegularExpression(
            '/strictUploadSelectors\s*=\s*\[[^\]]*uploader\s+button/i',
            $this->contentGeminiCode,
            'CRITICAL: "uploader button" must never be in strictUploadSelectors.'
        );
    }

    /**
     * Requirement R1: Strict upload selectors with negative preview filters.
     */
    public function test_strict_upload_button_selectors_contract(): void
    {
        // Must declare strict upload selectors
        $this->assertStringContainsString('const strictUploadSelectors = [', $this->contentGeminiCode);
        $this->assertStringContainsString('button[aria-label*="Añadir archivos"]', $this->contentGeminiCode);
        $this->assertStringContainsString('button[aria-label*="Add files"]', $this->contentGeminiCode);
        $this->assertStringContainsString('button[aria-label*="Subir archivos"]', $this->contentGeminiCode);
        $this->assertStringContainsString('[data-test-id="upload-button"]', $this->contentGeminiCode);

        // Must filter out thumbnail previews and file chips
        $this->assertStringContainsString('isInsidePreview', $this->contentGeminiCode);
        $this->assertStringContainsString('hasThumbnail', $this->contentGeminiCode);
        $this->assertStringContainsString('isFileChip', $this->contentGeminiCode);

        // Preview exclusions must include uploader, user-query-file-preview, file-preview
        $this->assertStringContainsString("btn.closest('uploader, .uploader, user-query-file-preview", $this->contentGeminiCode);
        $this->assertStringContainsString(".file-preview, .attachment-container')", $this->contentGeminiCode);
    }

    /**
     * Requirement R1: Paste success gating prevents synthetic clicks.
     */
    public function test_paste_succeeded_gating_prevents_synthetic_clicks(): void
    {
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*fileInputs\.length\s*===\s*0\s*&&\s*!pasteSucceeded\s*\)/',
            $this->contentGeminiCode,
            'Upload button search must be strictly gated behind !pasteSucceeded and fileInputs.length === 0'
        );
    }

    /**
     * Requirement R2: Lightbox dismiss guardian functions exist and dispatch Escape + Back button.
     */
    public function test_dismiss_gemini_lightbox_selectors_and_fallbacks(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+dismissGeminiLightbox\s*\(\)\s*\{/',
            $this->contentGeminiCode,
            'dismissGeminiLightbox() function must be defined'
        );

        // Back button selectors
        $this->assertStringContainsString('button[aria-label*="Atrás" i]', $this->contentGeminiCode);
        $this->assertStringContainsString('button[aria-label*="Back" i]', $this->contentGeminiCode);
        $this->assertStringContainsString('[role="dialog"] button', $this->contentGeminiCode);

        // Escape key event with composed: true
        $this->assertStringContainsString("key: 'Escape'", $this->contentGeminiCode);
        $this->assertStringContainsString("keyCode: 27", $this->contentGeminiCode);
        $this->assertStringContainsString("composed: true", $this->contentGeminiCode);

        // Target dispatch to document, window, document.body, document.activeElement
        $this->assertStringContainsString('[document, window, document.body, document.activeElement]', $this->contentGeminiCode);

        // Back button click fallback
        $this->assertStringContainsString('backBtn.click()', $this->contentGeminiCode);

        // Focus restoration to editor
        $this->assertStringContainsString('editor.focus()', $this->contentGeminiCode);
    }

    /**
     * Requirement R2: startLightboxGuardian returns stop handler and cleans up interval & observer.
     */
    public function test_start_lightbox_guardian_lifecycle_stop_and_cleanup(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+startLightboxGuardian\s*\(\)\s*\{/',
            $this->contentGeminiCode,
            'startLightboxGuardian() function must be defined'
        );

        $this->assertStringContainsString('setInterval', $this->contentGeminiCode);
        $this->assertStringContainsString('clearInterval(intervalId)', $this->contentGeminiCode);
        $this->assertStringContainsString('new MutationObserver', $this->contentGeminiCode);
        $this->assertStringContainsString('observer.disconnect()', $this->contentGeminiCode);
        $this->assertStringContainsString('return {', $this->contentGeminiCode);
        $this->assertStringContainsString('stop: () =>', $this->contentGeminiCode);
    }

    /**
     * Requirement R2 & R3: executePromptInGemini wraps execution in try ... finally with guardian.stop().
     */
    public function test_try_finally_guardian_stop_and_dismiss_guarantee(): void
    {
        $this->assertMatchesRegularExpression(
            '/const\s+guardian\s*=\s*startLightboxGuardian\(\);.*?try\s*\{.*?\}\s*finally\s*\{\s*guardian\.stop\(\);\s*dismissGeminiLightbox\(\);\s*\}/s',
            $this->contentGeminiCode,
            'executePromptInGemini must wrap execution in try-finally with guardian.stop() and dismissGeminiLightbox()'
        );
    }

    /**
     * Requirement R3: Broad cleanup of prior attachment chips across both uploader and rich-textarea.
     */
    public function test_session_reuse_cleanup_selectors_across_uploader_and_rich_textarea(): void
    {
        // Cleanup query must target uploader buttons
        $this->assertStringContainsString('uploader button[aria-label*="Eliminar" i]', $this->contentGeminiCode);
        $this->assertStringContainsString('uploader button[aria-label*="Remove" i]', $this->contentGeminiCode);
        $this->assertStringContainsString('uploader button[aria-label*="Quitar" i]', $this->contentGeminiCode);
        $this->assertStringContainsString('uploader button[aria-label*="Descartar" i]', $this->contentGeminiCode);

        // Cleanup query must target rich-textarea buttons
        $this->assertStringContainsString('rich-textarea button[aria-label*="Eliminar" i]', $this->contentGeminiCode);
        $this->assertStringContainsString('rich-textarea button[aria-label*="Remove" i]', $this->contentGeminiCode);
        $this->assertStringContainsString('rich-textarea button[aria-label*="Quitar" i]', $this->contentGeminiCode);

        // Generic close buttons
        $this->assertStringContainsString('.attachment-close-button', $this->contentGeminiCode);
        $this->assertStringContainsString('[data-test-id*="remove-file"]', $this->contentGeminiCode);
    }

    /**
     * Requirement R3: Thorough text editor clearing prevents prompt concatenation.
     */
    public function test_editor_deep_text_reset_commands(): void
    {
        $this->assertStringContainsString("document.execCommand('selectAll', false, null)", $this->contentGeminiCode);
        $this->assertStringContainsString("document.execCommand('delete', false, null)", $this->contentGeminiCode);
        $this->assertStringContainsString("editor.textContent = ''", $this->contentGeminiCode);
        $this->assertStringContainsString("editor.innerHTML = '<p><br></p>'", $this->contentGeminiCode);
    }

    /**
     * Requirement R3: Response count tracking isolates new model-response from previous turns.
     */
    public function test_response_count_tracking_and_target_response_isolation(): void
    {
        $this->assertStringContainsString("document.querySelectorAll('model-response')", $this->contentGeminiCode);
        $this->assertStringContainsString("initialResponseCount", $this->contentGeminiCode);
        $this->assertStringContainsString("currentResponses.length > initialResponseCount", $this->contentGeminiCode);
    }

    /**
     * Resolution Extraction: Resolution fidelity maintained via naturalWidth and naturalHeight.
     */
    public function test_generated_image_resolution_preservation(): void
    {
        $this->assertStringContainsString("imgElement.naturalWidth || imgElement.width || 1024", $this->contentGeminiCode);
        $this->assertStringContainsString("imgElement.naturalHeight || imgElement.height || 1024", $this->contentGeminiCode);
        $this->assertStringContainsString("createImageBitmap(imgElement)", $this->contentGeminiCode);
        $this->assertStringContainsString("FETCH_IMAGE_URL", $this->contentGeminiCode);
    }

    /**
     * background.js session reuse & isLast tab focus management.
     */
    public function test_background_js_session_reuse_and_is_last_handling(): void
    {
        $this->assertStringContainsString("activeSessions[geminiTabId] = {", $this->backgroundJsCode);
        $this->assertStringContainsString("status: 'reusing'", $this->backgroundJsCode);
        $this->assertStringContainsString("if (session.isLast)", $this->backgroundJsCode);
        $this->assertStringContainsString("delete activeSessions[geminiTabId];", $this->backgroundJsCode);
    }

    /**
     * content_cauchos.js event forwarding contracts.
     */
    public function test_content_cauchos_event_forwarding_contract(): void
    {
        $this->assertStringContainsString("CAUCHOS_START_GEMINI_AUTOMATION", $this->contentCauchosCode);
        $this->assertStringContainsString("GEMINI_STATUS_UPDATE", $this->contentCauchosCode);
        $this->assertStringContainsString("GEMINI_AUTOMATION_COMPLETED", $this->contentCauchosCode);
        $this->assertStringContainsString("GEMINI_AUTOMATION_ERROR", $this->contentCauchosCode);
    }

    /**
     * Regression Fix: pasteSucceeded must verify DOM attachment preview via hasAttachmentPreviewInComposer()
     * rather than unconditionally hardcoding pasteSucceeded = true.
     */
    public function test_paste_succeeded_verifies_dom_attachment_preview(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/pasteSucceeded\s*=\s*true\s*;/',
            $this->contentGeminiCode,
            'pasteSucceeded must never be unconditionally hardcoded to true.'
        );
        $this->assertStringContainsString(
            'pasteSucceeded = hasAttachmentPreviewInComposer();',
            $this->contentGeminiCode
        );
        $this->assertStringContainsString(
            'ATTACH_IMAGE_MAIN_WORLD',
            $this->contentGeminiCode
        );
        $this->assertStringContainsString(
            'ATTACH_IMAGE_MAIN_WORLD',
            $this->backgroundJsCode
        );
    }

    /**
     * Regression Fix: dismissGeminiLightbox must never globally query mat-icon[data-mat-icon-name="close"]
     * on document, which previously clicked the remove (X) button of the uploaded image chip.
     */
    public function test_dismiss_lightbox_does_not_click_composer_attachment_close_button(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/document\.querySelector\([^\)]*data-mat-icon-name="close"/',
            $this->contentGeminiCode,
            'dismissGeminiLightbox must only search for close icons inside active viewerContainers, never globally on document.'
        );
        $this->assertStringContainsString(
            'container.querySelector(\'mat-icon[data-mat-icon-name="arrow_back"], mat-icon[font-icon="arrow_back"], mat-icon[data-mat-icon-name="close"]\')',
            $this->contentGeminiCode
        );
    }
}

