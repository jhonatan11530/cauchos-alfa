<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\WhatsAppService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenWA\Client;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    public function test_it_sends_text_through_the_configured_openwa_session(): void
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([[
                'id' => 'session-uuid',
                'name' => 'ventas',
                'status' => 'ready',
            ]], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'messageId' => 'message-123',
            ], JSON_THROW_ON_ERROR)),
        ]));
        $client = new Client([
            'baseUrl' => 'https://openwa.test',
            'apiKey' => 'test-api-key',
            'httpClient' => new GuzzleClient(['handler' => $handler]),
        ]);

        $service = new WhatsAppService($client, 'ventas');

        $result = $service->sendMessage('+57 300-123-4567', 'Hola');

        $this->assertSame('message-123', $result['messageId']);
    }

    public function test_it_normalizes_a_disconnected_session_with_its_qr_code(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([[
                'id' => 'session-uuid',
                'name' => 'ventas',
                'status' => 'qr_ready',
            ]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['qrCode' => 'data:image/png;base64,qr'], JSON_THROW_ON_ERROR)),
        ]);

        $status = (new WhatsAppService($client, 'ventas'))->status();

        $this->assertFalse($status['ready']);
        $this->assertSame('data:image/png;base64,qr', $status['qr']);
    }

    public function test_it_logs_out_and_starts_again_when_restarting(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([[
                'id' => 'session-uuid',
                'name' => 'ventas',
                'status' => 'ready',
            ]], JSON_THROW_ON_ERROR)),
            new Response(200, [], '{}'),
            new Response(200, [], json_encode(['status' => 'starting'], JSON_THROW_ON_ERROR)),
        ]);

        $result = (new WhatsAppService($client, 'ventas'))->restart();

        $this->assertSame('starting', $result['status']);
    }

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses): Client
    {
        $handler = HandlerStack::create(new MockHandler($responses));

        return new Client([
            'baseUrl' => 'https://openwa.test',
            'apiKey' => 'test-api-key',
            'httpClient' => new GuzzleClient(['handler' => $handler]),
        ]);
    }
}
