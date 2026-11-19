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

        $this->status = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_CREADO],
            ['name' => 'Pedido creado', 'sort_order' => 1, 'is_active' => true]
        );
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'billing_type' => 'remision',
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

        $order = Order::where('client_id', $this->client->id)->firstOrFail();

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

        $order = Order::where('client_id', $this->client->id)->firstOrFail();
        $this->assertSame(2, $order->items()->count());
    }

    public function test_store_persists_order_status_history(): void
    {
        $this->actingAs($this->admin)
            ->post(route('pedidos.store'), $this->validPayload());

        $order = Order::where('client_id', $this->client->id)->firstOrFail();

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

        $order = Order::where('client_id', $this->client->id)->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
        ]);
        $this->assertNull(session('seller_cart'));

        $response->assertRedirect(route('site.catalog'))
            ->assertSessionHas('success', 'Pedido '.$order->code.' confirmado correctamente.');
    }

    public function test_update_replaces_items_without_price_fields(): void
    {
        $order = Order::create([
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
        $order = Order::create([
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
        $order = Order::where('client_id', $this->client->id)->firstOrFail();
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

    public function test_orders_index_displays_create_order_button_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('pedidos.index'));

        $response->assertOk()
            ->assertSee('Crear pedido')
            ->assertSee(route('pedidos.create'), false);
    }

    public function test_admin_can_access_create_order_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('pedidos.create'));

        $response->assertOk()
            ->assertSee('Crear pedido');
    }

    public function test_store_respects_custom_code_and_ordered_at_when_provided(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('pedidos.store'), $this->validPayload([
                'code' => 'PED-MANUAL-100',
                'ordered_at' => '2026-10-15',
            ]));

        $response->assertRedirect(route('pedidos.index'))
            ->assertSessionHas('success');

        $order = Order::where('code', 'PED-MANUAL-100')->first();
        $this->assertNotNull($order);
        $this->assertSame('2026-10-15', $order->ordered_at->format('Y-m-d'));
    }

    public function test_create_order_form_renders_single_product_search_with_code_and_name(): void
    {
        $this->client->update(['document' => '900123456']);
        $productWithCode = Product::create([
            'name' => 'Soporte de Motor',
            'reference' => 'REF-CA-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('pedidos.create'));

        $response->assertOk()
            ->assertSee('id="product-search-select"', false)
            ->assertSee('[REF-CA-001] Soporte de Motor')
            ->assertSee($productWithCode->reference)
            ->assertSee('900123456');
    }

    public function test_edit_order_form_preloads_existing_items_into_table_with_single_search(): void
    {
        $productB = Product::create([
            'name' => 'Buje de Tijera',
            'reference' => 'REF-CA-002',
            'is_active' => true,
        ]);

        $order = Order::create([
            'code' => 'PED-EDIT-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->status->id,
            'ordered_at' => now()->toDateString(),
        ]);

        $order->items()->create(['product_id' => $this->product->id, 'quantity' => 3]);
        $order->items()->create(['product_id' => $productB->id, 'quantity' => 4]);

        $response = $this->actingAs($this->admin)
            ->get(route('pedidos.edit', $order));

        $response->assertOk()
            ->assertSee('id="product-search-select"', false)
            ->assertSee('name="items[0][product_id]"', false)
            ->assertSee('name="items[1][product_id]"', false)
            ->assertSee('Buje de Tijera')
            ->assertSee('REF-CA-002');
    }

    public function test_admin_can_view_order_details_and_table_header_contains_reference_column(): void
    {
        $order = Order::create([
            'code' => 'PED-SHOW-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->status->id,
            'ordered_at' => now()->toDateString(),
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('pedidos.show', $order));

        $response->assertOk()
            ->assertSee('Código / Ref.');
    }

    public function test_order_detail_displays_product_reference_when_present(): void
    {
        $productWithRef = Product::create([
            'name' => 'Banda de Distribución',
            'reference' => 'REF-001',
            'is_active' => true,
        ]);

        $order = Order::create([
            'code' => 'PED-SHOW-002',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->status->id,
            'ordered_at' => now()->toDateString(),
        ]);

        $order->items()->create([
            'product_id' => $productWithRef->id,
            'quantity' => 4,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('pedidos.show', $order));

        $response->assertOk()
            ->assertSee('REF-001')
            ->assertSee('<td>REF-001</td>', false)
            ->assertSee('Banda de Distribución');
    }

    public function test_order_detail_displays_hyphen_when_product_reference_is_null_or_empty(): void
    {
        $productNullRef = Product::create([
            'name' => 'Empaque de Culata',
            'reference' => null,
            'is_active' => true,
        ]);

        $productEmptyRef = Product::create([
            'name' => 'Retén de Cigüeñal',
            'reference' => '',
            'is_active' => true,
        ]);

        $order = Order::create([
            'code' => 'PED-SHOW-003',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->status->id,
            'ordered_at' => now()->toDateString(),
        ]);

        $order->items()->create([
            'product_id' => $productNullRef->id,
            'quantity' => 1,
        ]);

        $order->items()->create([
            'product_id' => $productEmptyRef->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('pedidos.show', $order));

        $response->assertOk()
            ->assertSee('Empaque de Culata')
            ->assertSee('Retén de Cigüeñal')
            ->assertSee('<td>-</td>', false);
    }

    public function test_destroy_transitions_order_to_pedido_rechazado_and_records_history(): void
    {
        $rejectedStatus = OrderStatus::where('slug', OrderStatus::SLUG_RECHAZADO)->firstOrFail();

        $order = Order::create([
            'code' => 'PED-DEL-REJ-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->status->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('pedidos.destroy', $order));

        $response->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($rejectedStatus->id, $order->order_status_id);
        $this->assertSame('Pedido rechazado desde el listado.', $order->rejection_reason);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'previous_status_id' => $this->status->id,
            'new_status_id' => $rejectedStatus->id,
            'user_id' => $this->admin->id,
            'observation' => 'Pedido rechazado desde el listado.',
        ]);
    }

    public function test_destroy_blocks_rejecting_an_order_already_in_terminal_state(): void
    {
        $collectedStatus = OrderStatus::where('slug', OrderStatus::SLUG_COBRADO)->firstOrFail();

        $order = Order::create([
            'code' => 'PED-DEL-TERM-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $collectedStatus->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('pedidos.destroy', $order));

        $response->assertRedirect()
            ->assertSessionHas('error');

        $order->refresh();
        $this->assertSame($collectedStatus->id, $order->order_status_id);
    }

    public function test_destroy_blocks_rejecting_delivered_and_all_terminal_orders(): void
    {
        $blockedSlugs = [
            OrderStatus::SLUG_ENTREGADO,
            OrderStatus::SLUG_DEVUELTO,
            OrderStatus::SLUG_RECHAZADO,
            OrderStatus::SLUG_COBRADO,
        ];

        foreach ($blockedSlugs as $slug) {
            $status = OrderStatus::where('slug', $slug)->firstOrFail();

            $order = Order::create([
                'code' => 'PED-DEL-BLK-'.strtoupper($slug),
                'client_id' => $this->client->id,
                'created_by' => $this->admin->id,
                'order_status_id' => $status->id,
                'billing_type' => 'remision',
                'ordered_at' => now()->toDateString(),
            ]);

            $response = $this->actingAs($this->admin)
                ->delete(route('pedidos.destroy', $order));

            $response->assertRedirect()
                ->assertSessionHas('error', 'No se puede rechazar un pedido en su estado actual.');

            $this->assertSame($status->id, $order->fresh()->order_status_id);
        }
    }

    public function test_orders_index_hides_rechazar_button_for_delivered_and_terminal_orders(): void
    {
        $creado = OrderStatus::where('slug', OrderStatus::SLUG_CREADO)->firstOrFail();
        $entregado = OrderStatus::where('slug', OrderStatus::SLUG_ENTREGADO)->firstOrFail();
        $rechazado = OrderStatus::where('slug', OrderStatus::SLUG_RECHAZADO)->firstOrFail();

        $activeOrder = Order::create([
            'code' => 'PED-IDX-ACT-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $creado->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $deliveredOrder = Order::create([
            'code' => 'PED-IDX-DEL-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $entregado->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $rejectedOrder = Order::create([
            'code' => 'PED-IDX-REJ-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $rechazado->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('pedidos.index'));

        $response->assertOk()
            ->assertSee('action="'.route('pedidos.destroy', $activeOrder).'"', false)
            ->assertDontSee('action="'.route('pedidos.destroy', $deliveredOrder).'"', false)
            ->assertDontSee('action="'.route('pedidos.destroy', $rejectedOrder).'"', false);

        $this->assertSame(1, substr_count($response->getContent(), '>Rechazar<'));
    }

    public function test_same_status_patch_is_blocked_on_terminal_orders(): void
    {
        $rechazado = OrderStatus::where('slug', OrderStatus::SLUG_RECHAZADO)->firstOrFail();
        $cobrado = OrderStatus::where('slug', OrderStatus::SLUG_COBRADO)->firstOrFail();
        $devuelto = OrderStatus::where('slug', OrderStatus::SLUG_DEVUELTO)->firstOrFail();

        $rejectedOrder = Order::create([
            'code' => 'PED-SAME-REJ-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $rechazado->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
            'rejection_reason' => 'Motivo original protegido',
            'payment_term' => 'contado',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $rejectedOrder), [
                'order_status_id' => $rechazado->id,
                'rejection_reason' => 'Intento de sobrescritura',
                'payment_term' => 'credito',
                'payment_due_date' => now()->addDays(10)->toDateString(),
            ])
            ->assertSessionHasErrors('order_status_id');

        $rejectedOrder->refresh();
        $this->assertSame('Motivo original protegido', $rejectedOrder->rejection_reason);
        $this->assertSame('contado', $rejectedOrder->payment_term);
        $this->assertDatabaseCount('order_status_histories', 0);

        $collectedOrder = Order::create([
            'code' => 'PED-SAME-COB-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $cobrado->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
            'payment_support_path' => 'orders/payments/original.pdf',
            'paid_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $collectedOrder), [
                'order_status_id' => $cobrado->id,
                'payment_responsible_id' => $this->admin->id,
            ])
            ->assertSessionHasErrors('order_status_id');

        $this->assertSame('orders/payments/original.pdf', $collectedOrder->fresh()->payment_support_path);

        $returnedOrder = Order::create([
            'code' => 'PED-SAME-DEV-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $devuelto->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
            'return_justification_path' => 'orders/returns/original.pdf',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $returnedOrder), [
                'order_status_id' => $devuelto->id,
                'payment_term' => 'contado',
            ])
            ->assertSessionHasErrors('order_status_id');

        $this->assertSame('orders/returns/original.pdf', $returnedOrder->fresh()->return_justification_path);
        $this->assertDatabaseCount('order_status_histories', 0);
    }
}
