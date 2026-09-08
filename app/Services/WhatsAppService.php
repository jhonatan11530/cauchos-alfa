<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
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

    private function errorOf(\Illuminate\Http\Client\Response $response): string
    {
        return (string) ($response->json('error') ?? 'Error del microservicio de WhatsApp (HTTP ' . $response->status() . ').');
    }
}
