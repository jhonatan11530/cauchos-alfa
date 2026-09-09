<?php

namespace App\Services;

use InvalidArgumentException;
use OpenWA\Client;
use OpenWA\Exceptions\OpenWANotFoundException;
use RuntimeException;

/**
 * Frontera de dominio para la sesión OpenWA configurada en Laravel.
 */
class WhatsAppService
{
    public function __construct(
        private Client $client,
        private ?string $sessionId = null,
        private ?array $resolvedSession = null,
    ) {
        $this->sessionId ??= (string) config('whatsapp.session_id', 'default');
    }

    /** Estado normalizado para la vista administrativa. */
    public function status(): array
    {
        try {
            $session = $this->session();
            $status = strtolower((string) ($session['status'] ?? ''));
            $ready = $status === 'ready';
            $qr = null;

            if (! $ready) {
                try {
                    $qrResponse = $this->client->sessions->getQrCode($session['id']);
                    $qr = $qrResponse['qrCode'] ?? null;
                } catch (\Throwable) {
                    $qr = null;
                }
            }

            return [
                'ready' => $ready,
                'starting' => ! $ready && $qr === null && in_array($status, [
                    'created', 'initializing', 'authenticating', 'action_required',
                ], true),
                'qr' => $qr,
                'status' => $status ?: null,
                'session' => $session['id'] ?? $this->sessionId,
                'session_name' => $session['name'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'ready' => false,
                'starting' => false,
                'qr' => null,
                'session' => $this->sessionId,
                'error' => $this->safeError($e),
            ];
        }
    }

    /** Inicia la sesión configurada y devuelve su respuesta. */
    public function start(): array
    {
        return $this->client->sessions->start($this->session()['id']);
    }

    /** Cierra y vuelve a iniciar la sesión para solicitar un QR nuevo. */
    public function restart(): array
    {
        try {
            $this->client->sessions->logout($this->session()['id']);
        } catch (OpenWANotFoundException) {
            // La sesión puede no estar enlazada todavía; iniciar sigue siendo válido.
        }

        return $this->start();
    }

    /** Cierra la sesión de WhatsApp configurada. */
    public function logout(): void
    {
        $this->client->sessions->logout($this->session()['id']);
    }

    /** Envía un mensaje de texto simple y devuelve el resultado del gateway. */
    public function sendMessage(string $to, string $message): array
    {
        return $this->client->messages->sendText($this->session()['id'], [
            'chatId' => $this->normalizeNumber($to).'@c.us',
            'text' => $message,
        ]);
    }

    /** Resolve a configured UUID or session name to OpenWA's server-side ID. */
    private function session(): array
    {
        if ($this->resolvedSession !== null) {
            return $this->resolvedSession;
        }

        $sessions = $this->client->sessions->list();

        foreach ($sessions as $session) {
            if (($session['id'] ?? null) === $this->sessionId
                || ($session['name'] ?? null) === $this->sessionId) {
                return $this->resolvedSession = $session;
            }
        }

        throw new RuntimeException('La sesión de WhatsApp configurada no existe en OpenWA.');
    }

    /** Devuelve el número en formato internacional sin signos ni separadores. */
    public function normalizeNumber(string $number): string
    {
        $number = trim($number);
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $countryCode = preg_replace('/\D+/', '', (string) config('whatsapp.default_country_code', '57')) ?? '';
        if ($countryCode === '') {
            throw new InvalidArgumentException('El código de país de WhatsApp no está configurado.');
        }

        $digits = ltrim($digits, '0');
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new InvalidArgumentException('El número de WhatsApp no es válido.');
        }

        if (! str_starts_with($digits, $countryCode)) {
            $digits = $countryCode.$digits;
        }

        return $digits;
    }

    private function safeError(\Throwable $exception): string
    {
        if ($exception instanceof RuntimeException || $exception instanceof InvalidArgumentException) {
            return $exception->getMessage();
        }

        return 'No se pudo conectar con el gateway de WhatsApp.';
    }
}
