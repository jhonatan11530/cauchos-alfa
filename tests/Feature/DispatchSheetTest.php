<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchSheetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected OrderStatus $statusDispatched;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin Despachos',
            'email' => 'admin_despachos@cauchosalfa.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->statusDispatched = OrderStatus::firstOrCreate(['slug' => 'pedido-despachado'], ['name' => 'Pedido Despachado', 'color' => '#31ce36']);
    }

    public function test_admin_can_view_dispatch_index(): void
    {
        $client = Client::create([
            'name' => 'Taller Ruta 1',
            'address' => 'Carrera 1 # 10-20',
            'city' => 'Cali',
            'phone' => '3001234567',
            'is_active' => true,
        ]);

        $product = Product::create(['name' => 'Llanta Moto 18', 'is_active' => true]);

        $order = Order::create([
            'code' => 'PED-DESP-001',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusDispatched->id,
            'billing_type' => 'remision',
            'ordered_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dispatch.index', ['date' => now()->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('PED-DESP-001');
        $response->assertSee('Taller Ruta 1');
        $response->assertSee('Llanta Moto 18');
        $response->assertSee('WhatsApp');
    }

    public function test_admin_can_view_printable_manifest(): void
    {
        $client = Client::create([
            'name' => 'Cliente Manifiesto',
            'address' => 'Calle 50 # 25-10',
            'city' => 'Cali',
            'is_active' => true,
        ]);

        $order = Order::create([
            'code' => 'PED-PRINT-001',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->statusDispatched->id,
            'billing_type' => 'remision',
            'ordered_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('dispatch.print', ['date' => now()->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('CAUCHOS ALFA — MANIFIESTO DE ENTREGA Y DESPACHO');
        $response->assertSee('PED-PRINT-001');
        $response->assertSee('Cliente Manifiesto');
        $response->assertSee('Firma / C.C.');
    }
}
