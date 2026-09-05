<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    public function index(): View
    {
        return view('admin.whatsapp.index', [
            'status' => $this->whatsapp->status(),
            'sellers' => User::whereHas('role', fn($q) => $q->where('slug', 'vendedor'))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'catalogs' => Catalog::orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function status(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->whatsapp->status());
    }

    /** Reinicia la sesion del microservicio para regenerar el QR. */
    public function restart(): RedirectResponse
    {
        try {
            Http::timeout(15)->post(rtrim(config('whatsapp.base_url'), '/').'/restart');
            return back()->with('success', 'Reiniciando sesion; el QR aparecera en unos segundos.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Microservicio no disponible: '.$e->getMessage());
        }
    }

    public function logout(): RedirectResponse
    {
        try {
            $this->whatsapp->logout();
            // Regenera la sesion para que el dashboard muestre un nuevo QR
            try { Http::timeout(15)->post(rtrim(config('whatsapp.base_url'), '/').'/restart'); } catch (\Throwable $e) {}
            return back()->with('success', 'Sesion cerrada. Escanea el nuevo QR para volver a conectarte.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo cerrar la sesion: ' . $e->getMessage());
        }
    }

    /** Enviar un mensaje de texto (o con archivo adjunto) a numeros libres. */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'numbers' => ['required', 'string', 'min:6'],
            'message' => ['nullable', 'string', 'max:4096'],
            'attachment' => ['nullable', 'file', 'max:51200', 'mimes:png,jpg,jpeg,gif,webp,pdf'],
        ]);

        $numbers = $this->parseNumbers($data['numbers']);
        $errors = [];

        foreach ($numbers as $number) {
            try {
                if ($request->hasFile('attachment')) {
                    $this->whatsapp->sendFile($number, $request->file('attachment'), $data['message'] ?? '');
                } else {
                    $this->whatsapp->sendMessage($number, $data['message'] ?? '');
                }
            } catch (\Throwable $e) {
                $errors[] = $number . ': ' . $e->getMessage();
            }
        }

        return $this->result($numbers, $errors, 'Mensaje(s) enviado(s) correctamente.');
    }

    /** Enviar el PDF del catalogo a los vendedores seleccionados. */
    public function sendCatalog(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'catalog_id' => ['required', 'exists:catalogs,id'],
            'sellers' => ['required', 'array', 'min:1'],
            'sellers.*' => ['exists:users,id'],
            'message' => ['nullable', 'string', 'max:4096'],
        ]);

        $catalog = Catalog::findOrFail($data['catalog_id']);
        $sellers = User::whereIn('id', $data['sellers'])->where('is_active', true)->get();
        $numbers = $sellers->pluck('phone')->filter()->all();
        $errors = [];

        foreach ($sellers as $seller) {
            if (!$seller->phone) {
                $errors[] = $seller->name . ': sin numero de telefono registrado.';
                continue;
            }
            try {
                $this->whatsapp->sendCatalogPdf($seller->phone, $catalog, $data['message'] ?? '');
            } catch (\Throwable $e) {
                $errors[] = $seller->name . ': ' . $e->getMessage();
            }
        }

        return $this->result($numbers, $errors, 'Catalogo enviado correctamente a los vendedores.');
    }

    private function result(array $attempted, array $errors, string $okMessage): RedirectResponse
    {
        if (empty($errors)) {
            return back()->with('success', $okMessage . ' (' . count($attempted) . ' destinatario(s)).');
        }

        return back()->with('error', 'Se completaron ' . (count($attempted) - count($errors)) . ' de '
            . count($attempted) . ' envios. Detalles: ' . implode(' | ', $errors));
    }

    /** Convierte texto libre (comas, saltos de linea, espacios) en lista de numeros. */
    private function parseNumbers(string $raw): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/[\s,;]+/', $raw) ?: [],
            fn($n) => strlen(preg_replace('/\D/', '', $n) ?? '') >= 10
        )));
    }
}
