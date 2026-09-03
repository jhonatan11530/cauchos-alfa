<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    private Product $product;

    private OrderStatus $status;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Administrador', 'slug' => 'administrador']);
        $sellerRole = Role::create(['name' => 'Vendedor', 'slug' => 'vendedor']);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->seller = User::create([
            'role_id' => $sellerRole->id,
            'name' => 'Vendedor',
            'email' => 'seller@test.com',
            'seller_code' => 'VEND001',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Cliente Prueba',
            'email' => 'cliente@test.com',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Llanta 185/65 R15',
            'is_active' => true,
        ]);

        $this->status = OrderStatus::create([
            'name' => 'Pedido creado',
            'slug' => 'pedido-creado',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'ordered_at' => now()->toDateString(),
            'notes' => 'Pedido de prueba',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
        ], $overrides);
    }

    public function test_store_creates_order_with_items_only(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('pedidos.store'), $this->validPayload());

        $response->assertRedirect(route('pedidos.index'))
            ->assertSessionHas('success');

        $order = \App\Models\Order::where('client_id', $this->client->id)->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'client_id' => $this->client->id,
            'order_status_id' => $this->status->id,
        ]);

        $item = $order->items()->first();
        $this->assertNotNull($item);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_store_keeps_multiple_items_without_price_data(): void
    {
        $productB = Product::create([
            'name' => 'Llanta 195/55 R16',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('pedidos.store'), $this->validPayload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 2],
                    ['product_id' => $productB->id, 'quantity' => 1],
                ],
            ]));

        $order = \App\Models\Order::where('client_id', $this->client->id)->firstOrFail();
        $this->assertSame(2, $order->items()->count());
    }

    public function test_store_persists_order_status_history(): void
    {
        $this->actingAs($this->admin)
            ->post(route('pedidos.store'), $this->validPayload());

        $order = \App\Models\Order::where('client_id', $this->client->id)->firstOrFail();

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'previous_status_id' => null,
            'new_status_id' => $this->status->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_store_fails_when_no_items_are_sent(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('pedidos.store'), $this->validPayload(['items' => []]));

        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseMissing('orders', ['client_id' => $this->client->id]);
    }

    public function test_store_rolls_back_order_when_item_fails(): void
    {
        // Duplicate client-side item with an invalid product id: the transaction
        // must roll back the whole order creation, leaving no partial data.
        $response = $this->actingAs($this->admin)
            ->post(route('pedidos.store'), $this->validPayload([
                'items' => [
                    ['product_id' => 999999, 'quantity' => 1, 'unit_price' => 10],
                ],
            ]));

        $response->assertSessionHasErrors();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_store_from_seller_site_clears_cart_and_redirects(): void
    {
        $response = $this->actingAs($this->seller)
            ->withSession(['seller_cart' => [$this->product->id => 2]])
            ->post(route('pedidos.store'), $this->validPayload([
                'from_seller_site' => true,
            ]));

        $order = \App\Models\Order::where('client_id', $this->client->id)->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
        ]);
        $this->assertNull(session('seller_cart'));

        $response->assertRedirect(route('site.catalog'))
            ->assertSessionHas('success', 'Pedido '.$order->code.' confirmado correctamente.');
    }

    public function test_update_replaces_items_without_price_fields(): void
    {
        $order = \App\Models\Order::create([
            'code' => 'PED-TEST-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->status->id,
            'ordered_at' => now()->toDateString(),
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $productB = Product::create([
            'name' => 'Llanta 195/55 R16',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('pedidos.update', $order), $this->validPayload([
                'code' => 'PED-TEST-001',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                    ['product_id' => $productB->id, 'quantity' => 3],
                ],
            ]));

        $response->assertRedirect(route('pedidos.index'))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(2, $order->items()->count());
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'quantity' => 5,
        ]);
    }

    public function test_update_requires_valid_data(): void
    {
        $order = \App\Models\Order::create([
            'code' => 'PED-TEST-002',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->status->id,
            'total' => 0,
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('pedidos.update', $order), $this->validPayload([
                'client_id' => null,
            ]));

        $response->assertSessionHasErrors(['client_id']);
        $order->refresh();
        $this->assertSame($this->client->id, $order->client_id);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_complete_seller_flow_places_order_and_empties_cart(): void
    {
        $productB = Product::create(['name' => 'Llanta 195/55 R16', 'is_active' => true]);

        // 1. El vendedor agrega dos productos desde el catalogo
        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), ['product_id' => $this->product->id, 'quantity' => 2])
            ->assertRedirect();

        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), ['product_id' => $productB->id, 'quantity' => 1])
            ->assertRedirect();

        // 2. La vista del pedido muestra los productos, sin mensaje de carrito vacio
        $this->actingAs($this->seller)
            ->get(route('site.seller.order'))
            ->assertOk()
            ->assertViewHas('cartProducts', fn ($cart) => $cart->count() === 2)
            ->assertSessionMissing('warning');

        // 3. El vendedor confirma el pedido escogiendo el cliente
        $response = $this->actingAs($this->seller)
            ->post(route('pedidos.store'), $this->validPayload([
                'from_seller_site' => '1',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 2],
                    ['product_id' => $productB->id, 'quantity' => 1],
                ],
            ]));

        // 4. El pedido se crea con sus items y el carrito queda limpio
        $order = \App\Models\Order::where('client_id', $this->client->id)->firstOrFail();
        $this->assertSame(2, $order->items()->count());
        $this->assertNull(session('seller_cart'));

        // 5. Redirige al catalogo con la confirmacion, NO a la pagina del pedido
        $response->assertRedirect(route('site.catalog'))
            ->assertSessionHas('success', 'Pedido '.$order->code.' confirmado correctamente.');

        // 6. Si el vendedor revisa "Mi pedido" despues, ve aviso de carrito vacio (warning, no success)
        $this->actingAs($this->seller)
            ->get(route('site.seller.order'))
            ->assertRedirect(route('site.catalog'))
            ->assertSessionHas('warning');
    }
}
