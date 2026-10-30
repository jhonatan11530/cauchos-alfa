<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\GeminiPromptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Muestra la pantalla de configuración general y edición del prompt de Gemini.
     */
    public function index(): View
    {
        $setting = Setting::where('key', GeminiPromptService::PROMPT_KEY)->first();
        $activePrompt = GeminiPromptService::get(GeminiPromptService::PROMPT_KEY);
        $canonicalFallback = GeminiPromptService::CANONICAL_FALLBACK_PROMPT;

        return view('admin.settings.index', compact('setting', 'activePrompt', 'canonicalFallback'));
    }

    /**
     * Actualiza el prompt de Gemini en base de datos e invalida/sincroniza la caché.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt_gemini' => ['required', 'string', 'min:10'],
        ], [
            'prompt_gemini.required' => 'El prompt de Gemini no puede estar vacío.',
            'prompt_gemini.min' => 'El prompt debe tener al menos 10 caracteres.',
        ]);

        Setting::set(
            GeminiPromptService::PROMPT_KEY,
            trim($validated['prompt_gemini']),
            'Prompt utilizado por Gemini para la optimización de fotografías de estudio de productos'
        );

        return redirect()
            ->route('settings.index')
            ->with('success', 'Prompt de Gemini actualizado con éxito. La memoria caché ha sido sincronizada.');
    }

    /**
     * Restablece el prompt de Gemini a su valor canónico por defecto.
     */
    public function resetPrompt(): RedirectResponse
    {
        Setting::set(
            GeminiPromptService::PROMPT_KEY,
            GeminiPromptService::CANONICAL_FALLBACK_PROMPT,
            'Prompt utilizado por Gemini para la optimización de fotografías de estudio de productos'
        );

        return redirect()
            ->route('settings.index')
            ->with('info', 'El prompt ha sido restablecido a su valor canónico predeterminado.');
    }
}

