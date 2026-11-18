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

class CollectionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected OrderStatus $statusCreated;

    protected OrderStatus $statusCollected;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin Cobros',
            'email' => 'admin_cobros@cauchosalfa.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->statusCreated = OrderStatus::firstOrCreate(['slug' => 'pedido-creado'], ['name' => 'Pedido Creado', 'color' => '#333']);
        $this->statusCollected = OrderStatus::firstOrCreate(['slug' => 'pedido-cobrado'], ['name' => 'Pedido Cobrado', 'color' => '#28a745']);
    }

    public function test_admin_can_view_cartera_index_and_metrics(): void
    {
        $client = Client::create(['name' => 'Cliente Cartera 1', 'is_active' => true]);

        // 1 orden vencida
        Order::create([
            'code' => 'PED-VENCIDO-1',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusCreated->id,
            'billing_type' => 'remision',
            'payment_term' => 'credito',
            'payment_due_date' => now()->subDays(5),
            'paid_at' => null,
        ]);

        // 1 orden al día pendiente
        Order::create([
            'code' => 'PED-ALDIA-1',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusCreated->id,
            'billing_type' => 'factura_electronica',
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(10),
            'paid_at' => null,
        ]);

        // 1 orden cobrada
        Order::create([
            'code' => 'PED-COBRADO-1',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusCollected->id,
            'billing_type' => 'remision',
            'payment_term' => 'credito',
            'payment_due_date' => now()->subDays(2),
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('cartera.index'));

        $response->assertStatus(200);
        $response->assertSee('PED-VENCIDO-1');
        $response->assertSee('PED-ALDIA-1');
        $response->assertSee('Vencida hace 5 días');
    }

    public function test_admin_can_filter_vencidas(): void
    {
        $client = Client::create(['name' => 'Cliente Cartera 2', 'is_active' => true]);

        Order::create([
            'code' => 'PED-FILTRO-VENCIDO',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusCreated->id,
            'billing_type' => 'remision',
            'payment_term' => 'credito',
            'payment_due_date' => now()->subDays(3),
            'paid_at' => null,
        ]);

        Order::create([
            'code' => 'PED-FILTRO-ALDIA',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusCreated->id,
            'billing_type' => 'remision',
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(5),
            'paid_at' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('cartera.index', ['filter' => 'vencidas']));

        $response->assertStatus(200);
        $response->assertSee('PED-FILTRO-VENCIDO');
        $response->assertDontSee('PED-FILTRO-ALDIA');
    }

    public function test_admin_can_register_payment_with_support_file(): void
    {
        $client = Client::create(['name' => 'Cliente Pago', 'is_active' => true]);

        $order = Order::create([
            'code' => 'PED-PAGO-01',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusCreated->id,
            'billing_type' => 'factura_electronica',
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(5),
            'paid_at' => null,
        ]);

        $file = UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('cartera.payment', $order), [
            'payment_date' => now()->toDateString(),
            'payment_responsible_id' => $this->admin->id,
            'payment_support' => $file,
            'observation' => 'Pago por transferencia bancaria.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertEquals($this->admin->id, $order->payment_responsible_id);
        $this->assertNotNull($order->payment_support_path);
        $this->assertEquals($this->statusCollected->id, $order->order_status_id);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'new_status_id' => $this->statusCollected->id,
        ]);
    }

    public function test_admin_can_export_cartera_to_csv(): void
    {
        $client = Client::create(['name' => 'Cliente Export Cartera', 'is_active' => true]);

        Order::create([
            'code' => 'PED-EXP-CARTERA',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusCreated->id,
            'billing_type' => 'remision',
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(2),
        ]);

        $response = $this->actingAs($this->admin)->get(route('cartera.export'));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('PED-EXP-CARTERA', $content);
        $this->assertStringContainsString('Cliente Export Cartera', $content);
    }
}
