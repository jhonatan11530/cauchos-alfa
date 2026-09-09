<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function index(): View
    {
        return view('admin.whatsapp.index', [
            'status' => $this->whatsapp->status(),
            'catalogs' => Catalog::orderBy('name')->get(['id', 'name', 'is_active']),
            'templates' => WhatsAppMessageTemplate::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function templatesIndex(): View
    {
        return view('admin.whatsapp.templates.index', [
            'templates' => WhatsAppMessageTemplate::orderBy('name')->paginate(10),
        ]);
    }

    public function templatesCreate(): View
    {
        return view('admin.whatsapp.templates.form', ['template' => new WhatsAppMessageTemplate]);
    }

    public function templatesStore(Request $request): RedirectResponse
    {
        $data = $this->validatedTemplateData($request);
        $data['is_active'] = $request->boolean('is_active');
        WhatsAppMessageTemplate::create($data);

        return redirect()->route('whatsapp.templates.index')->with('success', 'Plantilla creada correctamente.');
    }

    public function templatesEdit(WhatsAppMessageTemplate $template): View
    {
        return view('admin.whatsapp.templates.form', ['template' => $template]);
    }

    public function templatesUpdate(Request $request, WhatsAppMessageTemplate $template): RedirectResponse
    {
        $data = $this->validatedTemplateData($request);
        $data['is_active'] = $request->boolean('is_active');
        $template->update($data);

        return redirect()->route('whatsapp.templates.index')->with('success', 'Plantilla actualizada correctamente.');
    }

    public function templatesDestroy(WhatsAppMessageTemplate $template): RedirectResponse
    {
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', 'Estado de la plantilla actualizado.');
    }

    public function status(): JsonResponse
    {
        return response()->json($this->whatsapp->status());
    }

    /** Reinicia la sesión OpenWA para regenerar el QR. */
    public function restart(): RedirectResponse
    {
        try {
            $this->whatsapp->restart();

            return back()->with('success', 'Reiniciando sesion; el QR aparecera en unos segundos.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo reiniciar WhatsApp: '.$e->getMessage());
        }
    }

    public function logout(): RedirectResponse
    {
        try {
            $this->whatsapp->logout();
            $this->whatsapp->start();

            return back()->with('success', 'Sesion cerrada. Escanea el nuevo QR para volver a conectarte.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo cerrar la sesion: '.$e->getMessage());
        }
    }

    /** Enviar un mensaje de texto a numeros libres. */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'numbers' => ['required', 'string', 'min:6'],
            'message' => ['nullable', 'string', 'max:4096'],
        ]);

        $numbers = $this->parseNumbers($data['numbers']);
        $errors = [];

        if (empty($numbers)) {
            return back()->withInput()->with('error', 'No se encontraron números de WhatsApp válidos.');
        }

        foreach ($numbers as $number) {
            try {
                $this->whatsapp->sendMessage($number, $data['message'] ?? '');
            } catch (\Throwable $e) {
                $errors[] = $number.': '.$e->getMessage();
            }
        }

        return $this->result($numbers, $errors, 'Mensaje(s) enviado(s) correctamente.');
    }

    /** Enviar el enlace publico del catalogo a todos los vendedores activos. */
    public function sendCatalog(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'catalog_id' => ['required', 'exists:catalogs,id'],
            'message' => ['nullable', 'string', 'max:4096'],
        ]);

        $catalog = Catalog::findOrFail($data['catalog_id']);
        $sellers = User::whereHas('role', fn ($q) => $q->where('slug', 'vendedor'))
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->get(['name', 'phone']);
        $numbers = $sellers->pluck('phone')->filter()->all();
        $errors = [];

        if (empty($numbers)) {
            return back()->with('error', 'No hay vendedores activos con un teléfono válido.');
        }
        $catalogUrl = route('catalogos.public-pdf', $catalog);
        $catalogMessage = trim(($data['message'] ?? '')."\n".$catalogUrl);

        foreach ($sellers as $seller) {
            try {
                $this->whatsapp->sendMessage($seller->phone, $catalogMessage);
            } catch (\Throwable $e) {
                $errors[] = $seller->name.': '.$e->getMessage();
            }
        }

        return $this->result($numbers, $errors, 'Enlace del catalogo enviado correctamente a los vendedores.');
    }

    private function result(array $attempted, array $errors, string $okMessage): RedirectResponse
    {
        if (empty($errors)) {
            return back()->with('success', $okMessage.' ('.count($attempted).' destinatario(s)).');
        }

        return back()->with('error', 'Se completaron '.(count($attempted) - count($errors)).' de '
            .count($attempted).' envios. Detalles: '.implode(' | ', $errors));
    }

    private function validatedTemplateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:4096'],
        ]);
    }

    /** Convierte texto libre en números internacionales únicos. */
    private function parseNumbers(string $raw): array
    {
        $numbers = [];

        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $number) {
            try {
                $numbers[] = $this->whatsapp->normalizeNumber($number);
            } catch (\Throwable) {
                continue;
            }
        }

        return array_values(array_unique($numbers));
    }
}
