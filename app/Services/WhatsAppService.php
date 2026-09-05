<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cliente HTTP hacia el microservicio open source de WhatsApp (OpenWA)
 * que vive en /whatsapp-server.
 */
class WhatsAppService
{
    public function baseUrl(): string
    {
        return rtrim((string) config('whatsapp.base_url'), '/');
    }

    /** Estado de la sesion del microservicio (ready + QR en dataURL). */
    public function status(): array
    {
        try {
            return Http::timeout(8)->get($this->baseUrl() . '/status')->json() ?? [];
        } catch (\Throwable $e) {
            return ['ready' => false, 'qr' => null, 'error' => 'Microservicio no disponible (' . $e->getMessage() . ')'];
        }
    }

    /** Cierra la sesion de WhatsApp del microservicio. */
    public function logout(): void
    {
        Http::timeout(15)->post($this->baseUrl() . '/logout')->throw();
    }

    /** Envia un mensaje de texto simple. */
    public function sendMessage(string $to, string $message): void
    {
        $response = Http::timeout(30)->post($this->baseUrl() . '/send-message', [
            'to' => $to,
            'message' => $message,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->errorOf($response));
        }
    }

    /** Envia un archivo arbitrario (UploadedFile) con mensaje opcional. */
    public function sendFile(string $to, UploadedFile $file, string $message = ''): void
    {
        $response = Http::timeout(120)
            ->attach('file', $file->getContent(), $file->getClientOriginalName())
            ->post($this->baseUrl() . '/send-file', [
                'to' => $to,
                'message' => $message,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->errorOf($response));
        }
    }

    /**
     * Envia el PDF de un catalogo a un numero. El PDF se genera en memoria
     * con el mismo layout usado en la vista previa del panel.
     */
    public function sendCatalogPdf(string $to, \App\Models\Catalog $catalog, string $message = ''): void
    {
        $pdf = app('dompdf.wrapper')
            ->loadView('admin.catalogs.pdf', ['catalog' => $catalog->load('products.category')])
            ->output();

        $response = Http::timeout(120)
            ->attach('file', $pdf, 'catalogo-' . $catalog->id . '.pdf')
            ->post($this->baseUrl() . '/send-file', [
                'to' => $to,
                'message' => $message,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->errorOf($response));
        }
    }

    /** Envia un archivo del disco publico (ruta relativa a storage/app/public). */
    public function sendPublicFile(string $to, string $relativePath, string $message = ''): void
    {
        if (!Storage::disk('public')->exists($relativePath)) {
            throw new RuntimeException('El archivo no existe: ' . $relativePath);
        }

        $response = Http::timeout(120)
            ->attach('file', Storage::disk('public')->get($relativePath), basename($relativePath))
            ->post($this->baseUrl() . '/send-file', [
                'to' => $to,
                'message' => $message,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->errorOf($response));
        }
    }

    private function errorOf(\Illuminate\Http\Client\Response $response): string
    {
        return (string) ($response->json('error') ?? 'Error del microservicio de WhatsApp (HTTP ' . $response->status() . ').');
    }
}
