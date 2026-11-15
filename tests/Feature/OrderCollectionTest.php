<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderCollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $collector;
    private Client $client;
    private OrderStatus $creadoStatus;
    private OrderStatus $despachadoStatus;
    private OrderStatus $entregadoStatus;
    private OrderStatus $cobradoStatus;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);
        $vendedorRole = Role::firstOrCreate(['slug' => 'vendedor'], ['name' => 'Vendedor']);

        $this->admin = User::firstOrCreate(['email' => 'admin_collection@test.com'], [
            'role_id' => $adminRole->id,
            'name' => 'Admin Cobranza',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->collector = User::firstOrCreate(['email' => 'collector_collection@test.com'], [
            'role_id' => $vendedorRole->id,
            'name' => 'Cobrador Asignado',
            'seller_code' => 'COB001',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Cliente Cobranza',
            'email' => 'cliente_cobranza@test.com',
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

        $this->cobradoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_COBRADO],
            ['name' => 'Pedido cobrado', 'color' => '#28a745', 'sort_order' => 10, 'is_final' => true, 'is_active' => true]
        );
    }

    private function createTestOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'code' => 'PED-COL-' . uniqid(),
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->creadoStatus->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ], $attributes));
    }

    public function test_payment_modality_contado_succeeds_without_due_date(): void
    {
        $order = $this->createTestOrder();
        $sig = UploadedFile::fake()->create('firma.png', 50, 'image/png');
        $photo = UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'signature' => $sig,
                'photo' => $photo,
                'payment_term' => 'contado',
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame('contado', $order->payment_term);
        $this->assertNull($order->payment_due_date);
        $this->assertFalse($order->isCredit());
    }

    public function test_payment_modality_credito_requires_due_date(): void
    {
        $order = $this->createTestOrder();
        $sig = UploadedFile::fake()->create('firma.png', 50, 'image/png');
        $photo = UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'signature' => $sig,
                'photo' => $photo,
                'payment_term' => 'credito',
                'payment_due_date' => '',
            ]);

        $response->assertSessionHasErrors(['due_date']);
        $order->refresh();
        $this->assertNull($order->payment_due_date);
    }

    public function test_payment_modality_credito_with_valid_due_date_succeeds(): void
    {
        $order = $this->createTestOrder();
        $sig = UploadedFile::fake()->create('firma.png', 50, 'image/png');
        $photo = UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg');
        $targetDueDate = now()->addDays(45)->toDateString();

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'signature' => $sig,
                'photo' => $photo,
                'payment_term' => 'credito',
                'due_date' => $targetDueDate,
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame('credito', $order->payment_term);
        $this->assertTrue($order->isCredit());
        $this->assertSame($targetDueDate, $order->payment_due_date->format('Y-m-d'));
    }

    public function test_invalid_payment_modality_is_rejected(): void
    {
        $order = $this->createTestOrder();
        $sig = UploadedFile::fake()->create('firma.png', 50, 'image/png');
        $photo = UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'signature' => $sig,
                'photo' => $photo,
                'payment_term' => 'criptomoneda_invalida',
            ]);

        $response->assertSessionHasErrors(['payment_modality']);
    }

    public function test_admin_can_record_collection_with_responsible_user_and_support_file(): void
    {
        $order = $this->createTestOrder([
            'order_status_id' => $this->entregadoStatus->id,
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(15)->toDateString(),
        ]);

        $supportFile = UploadedFile::fake()->create('comprobante_bancario.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->cobradoStatus->id,
                'payment_responsible_id' => $this->collector->id,
                'payment_support' => $supportFile,
                'observation' => 'Pago verificado en extracto Bancolombia',
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame($this->cobradoStatus->id, $order->order_status_id);
        $this->assertTrue($order->isPaid());
        $this->assertNotNull($order->paid_at);
        $this->assertSame($this->collector->id, $order->payment_responsible_id);
        $this->assertSame($this->collector->id, $order->paymentResponsible->id);
        $this->assertNotNull($order->payment_support_path);

        Storage::disk('public')->assertExists($order->payment_support_path);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'new_status_id' => $this->cobradoStatus->id,
            'observation' => 'Pago verificado en extracto Bancolombia',
        ]);
    }

    public function test_record_collection_fails_when_responsible_user_is_missing(): void
    {
        $order = $this->createTestOrder([
            'order_status_id' => $this->entregadoStatus->id,
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(15)->toDateString(),
        ]);

        $supportFile = UploadedFile::fake()->create('comprobante.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->cobradoStatus->id,
                'payment_support' => $supportFile,
            ]);

        $response->assertSessionHasErrors(['collected_by']);
        $order->refresh();
        $this->assertNull($order->paid_at);
        $this->assertFalse($order->isPaid());
    }

    public function test_record_collection_fails_when_support_file_is_missing(): void
    {
        $order = $this->createTestOrder([
            'order_status_id' => $this->entregadoStatus->id,
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(15)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->cobradoStatus->id,
                'payment_responsible_id' => $this->collector->id,
            ]);

        $response->assertSessionHasErrors(['payment_support']);
        $order->refresh();
        $this->assertNull($order->paid_at);
    }

    public function test_order_model_helper_methods_and_aliases(): void
    {
        $order = $this->createTestOrder([
            'payment_term' => 'credito',
            'payment_due_date' => '2026-12-31',
            'paid_at' => now(),
            'payment_responsible_id' => $this->collector->id,
        ]);

        $this->assertTrue($order->isCredit());
        $this->assertTrue($order->isPaid());
        $this->assertSame('credito', $order->payment_method);
        $this->assertSame('credito', $order->payment_modality);
        $this->assertSame('2026-12-31', $order->due_date->format('Y-m-d'));
        $this->assertSame($this->collector->id, $order->collector_id);
        $this->assertSame($this->collector->name, $order->collector_name);
        $this->assertSame('cobrado', $order->payment_status);

        $order->payment_method = 'contado';
        $order->paid_at = null;
        $this->assertFalse($order->isCredit());
        $this->assertFalse($order->isPaid());
        $this->assertSame('pendiente', $order->payment_status);
    }
}
