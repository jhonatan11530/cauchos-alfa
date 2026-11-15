<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private OrderStatus $creadoStatus;
    private OrderStatus $despachadoStatus;
    private OrderStatus $entregadoStatus;
    private OrderStatus $devueltoStatus;
    private OrderStatus $rechazadoStatus;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);

        $this->admin = User::firstOrCreate(['email' => 'admin_test@test.com'], [
            'role_id' => $adminRole->id,
            'name' => 'Admin Test',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Cliente Transición',
            'email' => 'cliente_transicion@test.com',
            'is_active' => true,
        ]);

        $this->creadoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_CREADO],
            ['name' => 'Pedido creado', 'color' => '#1572e8', 'sort_order' => 1, 'is_active' => true]
        );

        $this->despachadoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_DESPACHADO],
            ['name' => 'Pedido despachado', 'color' => '#31ce36', 'sort_order' => 5, 'is_active' => true]
        );

        $this->entregadoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_ENTREGADO],
            ['name' => 'Pedido entregado', 'color' => '#2bb930', 'sort_order' => 6, 'is_final' => true, 'is_active' => true]
        );

        $this->devueltoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_DEVUELTO],
            ['name' => 'Pedido devuelto', 'color' => '#e83e8c', 'sort_order' => 8, 'is_final' => true, 'is_active' => true]
        );

        $this->rechazadoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_RECHAZADO],
            ['name' => 'Pedido rechazado', 'color' => '#dc3545', 'sort_order' => 9, 'is_final' => true, 'is_cancelled' => true, 'is_active' => true]
        );
    }

    private function createTestOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'code' => 'PED-TEST-' . uniqid(),
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->creadoStatus->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ], $attributes));
    }

    public function test_admin_can_transition_to_despachado_with_file_signature_and_photo(): void
    {
        $order = $this->createTestOrder();

        $signatureFile = UploadedFile::fake()->create('firma.png', 50, 'image/png');
        $dispatchPhoto = UploadedFile::fake()->create('despacho.jpg', 150, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'signature_file' => $signatureFile,
                'dispatch_photo' => $dispatchPhoto,
                'payment_term' => 'contado',
                'observation' => 'Despachado en camión 1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
        $this->assertNotNull($order->dispatch_signature_path);
        $this->assertNotNull($order->dispatch_photo_path);
        $this->assertSame('contado', $order->payment_term);

        Storage::disk('public')->assertExists($order->dispatch_signature_path);
        Storage::disk('public')->assertExists($order->dispatch_photo_path);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'new_status_id' => $this->despachadoStatus->id,
            'observation' => 'Despachado en camión 1',
        ]);
    }

    public function test_admin_can_transition_to_despachado_with_base64_signature(): void
    {
        $order = $this->createTestOrder();

        // 1x1 transparent png in base64
        $base64Sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        $dispatchPhoto = UploadedFile::fake()->create('despacho.jpg', 120, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'dispatch_signature' => $base64Sig,
                'dispatch_photo' => $dispatchPhoto,
                'payment_term' => 'credito',
                'payment_due_date' => now()->addDays(30)->toDateString(),
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
        $this->assertNotNull($order->dispatch_signature_path);
        $this->assertSame('credito', $order->payment_term);
        $this->assertNotNull($order->payment_due_date);
        Storage::disk('public')->assertExists($order->dispatch_signature_path);
    }

    public function test_transition_to_despachado_fails_when_signature_is_missing(): void
    {
        $order = $this->createTestOrder();
        $dispatchPhoto = UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'dispatch_photo' => $dispatchPhoto,
                'payment_term' => 'contado',
            ]);

        $response->assertSessionHasErrors(['signature']);
        $order->refresh();
        $this->assertSame($this->creadoStatus->id, $order->order_status_id);
    }

    public function test_transition_to_despachado_fails_when_photo_is_missing(): void
    {
        $order = $this->createTestOrder();
        $signatureFile = UploadedFile::fake()->create('firma.png', 50, 'image/png');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'signature_file' => $signatureFile,
                'payment_term' => 'contado',
            ]);

        $response->assertSessionHasErrors(['dispatch_photo']);
        $order->refresh();
        $this->assertSame($this->creadoStatus->id, $order->order_status_id);
    }

    public function test_transition_to_despachado_fails_when_payment_modality_is_missing(): void
    {
        $order = $this->createTestOrder();
        $signatureFile = UploadedFile::fake()->create('firma.png', 50, 'image/png');
        $dispatchPhoto = UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'signature_file' => $signatureFile,
                'dispatch_photo' => $dispatchPhoto,
            ]);

        $response->assertSessionHasErrors(['payment_term']);
        $order->refresh();
        $this->assertSame($this->creadoStatus->id, $order->order_status_id);
    }

    public function test_admin_can_transition_to_entregado_with_photo_and_location(): void
    {
        $order = $this->createTestOrder([
            'order_status_id' => $this->despachadoStatus->id,
            'payment_term' => 'contado',
        ]);

        $deliveryPhoto = UploadedFile::fake()->create('entrega.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->entregadoStatus->id,
                'delivery_photo' => $deliveryPhoto,
                'client_location' => '4.609710, -74.081750 (Bogotá D.C.)',
                'observation' => 'Recibido a satisfacción por el cliente',
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame($this->entregadoStatus->id, $order->order_status_id);
        $this->assertNotNull($order->delivery_photo_path);
        $this->assertSame('4.609710, -74.081750 (Bogotá D.C.)', $order->client_location);
        Storage::disk('public')->assertExists($order->delivery_photo_path);
    }

    public function test_transition_to_entregado_fails_when_photo_is_missing(): void
    {
        $order = $this->createTestOrder([
            'order_status_id' => $this->despachadoStatus->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->entregadoStatus->id,
                'client_location' => 'Calle 100 # 15-20',
            ]);

        $response->assertSessionHasErrors(['delivery_photo']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
    }

    public function test_transition_to_entregado_fails_when_location_is_missing(): void
    {
        $order = $this->createTestOrder([
            'order_status_id' => $this->despachadoStatus->id,
            'payment_term' => 'contado',
        ]);

        $deliveryPhoto = UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->entregadoStatus->id,
                'delivery_photo' => $deliveryPhoto,
            ]);

        $response->assertSessionHasErrors(['delivery_location']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
    }

    public function test_admin_can_transition_to_devuelto_with_justification_file(): void
    {
        $order = $this->createTestOrder();

        $justificationFile = UploadedFile::fake()->create('devolucion_soporte.pdf', 250, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->devueltoStatus->id,
                'return_justification_file' => $justificationFile,
                'return_reason' => 'Cliente solicitó referencia equivocada',
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame($this->devueltoStatus->id, $order->order_status_id);
        $this->assertNotNull($order->return_justification_path);
        Storage::disk('public')->assertExists($order->return_justification_path);
    }

    public function test_transition_to_devuelto_fails_when_justification_file_is_missing(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->devueltoStatus->id,
                'return_reason' => 'Sin archivo',
            ]);

        $response->assertSessionHasErrors(['return_justification_file']);
        $order->refresh();
        $this->assertSame($this->creadoStatus->id, $order->order_status_id);
    }

    public function test_admin_can_transition_to_rechazado_with_cancellation_reason(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->rechazadoStatus->id,
                'cancellation_reason' => 'Cliente canceló el pedido por demora en obra.',
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame($this->rechazadoStatus->id, $order->order_status_id);
        $this->assertSame('Cliente canceló el pedido por demora en obra.', $order->rejection_reason);
    }

    public function test_transition_to_rechazado_fails_when_reason_is_empty(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->rechazadoStatus->id,
                'cancellation_reason' => '',
            ]);

        $response->assertSessionHasErrors(['cancellation_reason']);
        $order->refresh();
        $this->assertSame($this->creadoStatus->id, $order->order_status_id);
    }
}
