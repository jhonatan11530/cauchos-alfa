<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\SellerCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReorderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $seller1;

    protected User $seller2;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);
        $sellerRole = Role::firstOrCreate(['slug' => 'vendedor'], ['name' => 'Vendedor']);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin Reorder',
            'email' => 'admin_reorder@cauchosalfa.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->seller1 = User::create([
            'role_id' => $sellerRole->id,
            'name' => 'Seller Uno',
            'seller_code' => 'V-101',
            'email' => 'seller1_reorder@cauchosalfa.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->seller2 = User::create([
            'role_id' => $sellerRole->id,
            'name' => 'Seller Dos',
            'seller_code' => 'V-102',
            'email' => 'seller2_reorder@cauchosalfa.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_preload_repeat_order_in_form(): void
    {
        $status = OrderStatus::firstOrCreate(['slug' => 'pedido-creado'], ['name' => 'Pedido Creado', 'color' => '#333']);
        $client = Client::create(['name' => 'Cliente Repetir', 'is_active' => true]);
        $product = Product::create(['name' => 'Cadena 9 Velocidades', 'reference' => 'CAD-9V', 'is_active' => true]);

        $order = Order::create([
            'code' => 'PED-ORIG-01',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $status->id,
            'billing_type' => 'remision',
            'notes' => 'Notas originales',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($this->admin)->get(route('pedidos.create', ['repeat' => $order->id]));

        $response->assertStatus(200);
        $response->assertSee('Cliente Repetir');
        $response->assertSee('CAD-9V');
        $response->assertSee('value="5"', false);
    }

    public function test_seller_can_repeat_own_order_loading_items_into_cart(): void
    {
        $status = OrderStatus::firstOrCreate(['slug' => 'pedido-entregado'], ['name' => 'Pedido Entregado', 'color' => '#2bb930']);
        $client = Client::create(['name' => 'Cliente Vendedor', 'is_active' => true]);
        $productA = Product::create(['name' => 'Manubrio MTB', 'reference' => 'MNB-01', 'is_active' => true]);
        $productB = Product::create(['name' => 'Manzana Trasera', 'reference' => 'MNZ-02', 'is_active' => true]);

        $order = Order::create([
            'code' => 'PED-SELLER-1',
            'client_id' => $client->id,
            'created_by' => $this->seller1->id,
            'order_status_id' => $status->id,
            'billing_type' => 'remision',
        ]);

        $order->items()->create(['product_id' => $productA->id, 'quantity' => 2]);
        $order->items()->create(['product_id' => $productB->id, 'quantity' => 3]);

        $response = $this->actingAs($this->seller1)->post(route('site.seller.orders.repeat', $order));

        $response->assertRedirect(route('site.seller.order'));
        $response->assertSessionHas('success');

        $cart = app(SellerCart::class)->get();
        $this->assertEquals(2, $cart[$productA->id]);
        $this->assertEquals(3, $cart[$productB->id]);
    }

    public function test_seller_cannot_repeat_another_seller_order(): void
    {
        $status = OrderStatus::firstOrCreate(['slug' => 'pedido-creado'], ['name' => 'Pedido Creado', 'color' => '#333']);
        $client = Client::create(['name' => 'Cliente Ajeno', 'is_active' => true]);

        $order = Order::create([
            'code' => 'PED-SELLER-2',
            'client_id' => $client->id,
            'created_by' => $this->seller2->id,
            'order_status_id' => $status->id,
            'billing_type' => 'remision',
        ]);

        $response = $this->actingAs($this->seller1)->post(route('site.seller.orders.repeat', $order));
        $response->assertStatus(403);
    }
}
