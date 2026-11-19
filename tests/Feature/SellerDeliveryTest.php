<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class SellerDeliveryTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    private User $sellerA;

    private User $sellerB;

    private User $admin;

    private Client $client;

    private OrderStatus $creadoStatus;

    private OrderStatus $despachadoStatus;

    private OrderStatus $entregadoStatus;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->createRoles();

        $this->sellerA = $this->createSeller([
            'name' => 'Vendedor Alfa',
            'email' => 'sellerA@test.com',
            'seller_code' => 'VEND-A',
        ]);

        $this->sellerB = $this->createSeller([
            'name' => 'Vendedor Beta',
            'email' => 'sellerB@test.com',
            'seller_code' => 'VEND-B',
        ]);

        $this->admin = $this->createAdmin([
            'name' => 'Administrador',
            'email' => 'admin@test.com',
        ]);

        $this->client = $this->createClient([
            'name' => 'Cliente Mayorista',
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
    }

    private function createOrderFor(User $creator, OrderStatus $status, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'code' => 'PED-'.strtoupper(uniqid()),
            'client_id' => $this->client->id,
            'created_by' => $creator->id,
            'order_status_id' => $status->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
            'payment_term' => 'contado',
        ], $attributes));
    }

    /**
     * Test 1: Seller can view their dispatched orders in the seller portal.
     */
    public function test_seller_can_view_dispatched_orders(): void
    {
        $order1 = $this->createOrderFor($this->sellerA, $this->despachadoStatus, ['code' => 'PED-DISPATCH-A']);
        $order2 = $this->createOrderFor($this->sellerA, $this->creadoStatus, ['code' => 'PED-CREATED-A']);
        $order3 = $this->createOrderFor($this->sellerB, $this->despachadoStatus, ['code' => 'PED-DISPATCH-B']);

        $response = $this->actingAs($this->sellerA)
            ->get(route('site.seller.orders'));

        $response->assertOk();
        $response->assertSee('PED-DISPATCH-A');
        $response->assertDontSee('PED-CREATED-A');
        $response->assertDontSee('PED-DISPATCH-B');
    }

    /**
     * Test 2: Unauthenticated guest is redirected to login.
     */
    public function test_guest_cannot_view_seller_dispatched_orders(): void
    {
        $response = $this->get(route('site.seller.orders'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test 3: Seller can register delivery with photo and coordinates.
     */
    public function test_seller_can_register_delivery_with_photo_and_coordinates(): void
    {
        $order = $this->createOrderFor($this->sellerA, $this->despachadoStatus);
        $photo = UploadedFile::fake()->create('entrega.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_photo' => $photo,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
                'client_location' => '4.609710, -74.081750 (Calle 100)',
                'observation' => 'Entrega recibida conforme',
            ]);

        $response->assertRedirect(route('site.seller.orders'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($this->entregadoStatus->id, $order->order_status_id);
        $this->assertNotNull($order->delivery_photo_path);
        Storage::disk('public')->assertExists($order->delivery_photo_path);

        $this->assertEqualsWithDelta(4.609710, $order->delivery_latitude, 0.0001);
        $this->assertEqualsWithDelta(-74.081750, $order->delivery_longitude, 0.0001);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'previous_status_id' => $this->despachadoStatus->id,
            'new_status_id' => $this->entregadoStatus->id,
            'user_id' => $this->sellerA->id,
        ]);
    }

    /**
     * Test 4: Delivery fails validation when photo is missing.
     */
    public function test_delivery_fails_validation_when_photo_is_missing(): void
    {
        $order = $this->createOrderFor($this->sellerA, $this->despachadoStatus);

        $response = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
            ]);

        $response->assertSessionHasErrors(['delivery_photo']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
    }

    /**
     * Test 5: Delivery fails validation when coordinates are missing.
     */
    public function test_delivery_fails_validation_when_coordinates_are_missing(): void
    {
        $order = $this->createOrderFor($this->sellerA, $this->despachadoStatus);
        $photo = UploadedFile::fake()->create('entrega.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_photo' => $photo,
            ]);

        $response->assertSessionHasErrors(['delivery_latitude']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
    }

    /**
     * Test 6: Seller CANNOT deliver another seller's order -> assert HTTP 403 Forbidden.
     */
    public function test_seller_cannot_deliver_another_sellers_order_returns_403(): void
    {
        $orderB = $this->createOrderFor($this->sellerB, $this->despachadoStatus);
        $photo = UploadedFile::fake()->create('entrega.jpg', 200, 'image/jpeg');

        // Attempt 1: via seller route
        $response = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $orderB), [
                'delivery_photo' => $photo,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
            ]);

        $response->assertForbidden();

        // Attempt 2: via pedidos.estado route
        $responseEstado = $this->actingAs($this->sellerA)
            ->patch(route('pedidos.estado', $orderB), [
                'order_status_id' => $this->entregadoStatus->id,
                'delivery_photo' => $photo,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
            ]);

        $responseEstado->assertForbidden();

        $orderB->refresh();
        $this->assertSame($this->despachadoStatus->id, $orderB->order_status_id);
    }

    /**
     * Test 7: Seller CANNOT deliver admin-created unassigned order -> assert HTTP 403 Forbidden.
     */
    public function test_seller_cannot_deliver_admin_created_unassigned_order_returns_403(): void
    {
        $orderAdmin = $this->createOrderFor($this->admin, $this->despachadoStatus);
        $photo = UploadedFile::fake()->create('entrega.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $orderAdmin), [
                'delivery_photo' => $photo,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
            ]);

        $response->assertForbidden();
        $orderAdmin->refresh();
        $this->assertSame($this->despachadoStatus->id, $orderAdmin->order_status_id);
    }

    /**
     * Test 8: Delivery cannot be registered on non-dispatched order.
     */
    public function test_delivery_cannot_be_registered_on_non_dispatched_order(): void
    {
        $orderCreated = $this->createOrderFor($this->sellerA, $this->creadoStatus);
        $photo = UploadedFile::fake()->create('entrega.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $orderCreated), [
                'delivery_photo' => $photo,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
            ]);

        $response->assertSessionHasErrors(['order']);
        $orderCreated->refresh();
        $this->assertSame($this->creadoStatus->id, $orderCreated->order_status_id);
    }

    /**
     * Test 9: Admin retains ability to register delivery on seller order (R3 backup).
     */
    public function test_admin_retains_ability_to_register_delivery_on_seller_order(): void
    {
        $order = $this->createOrderFor($this->sellerA, $this->despachadoStatus);
        $photo = UploadedFile::fake()->create('entrega_admin.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_photo' => $photo,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
                'observation' => 'Entrega de respaldo por administrador',
            ]);

        $response->assertRedirect(route('site.seller.orders'));
        $order->refresh();
        $this->assertSame($this->entregadoStatus->id, $order->order_status_id);
        $this->assertNotNull($order->delivery_photo_path);
        Storage::disk('public')->assertExists($order->delivery_photo_path);
    }

    /**
     * Test 10: Admin delivery flow in admin panel remains intact.
     */
    public function test_admin_delivery_flow_in_admin_panel_remains_intact(): void
    {
        $order = $this->createOrderFor($this->sellerA, $this->despachadoStatus);
        $deliveryPhoto = UploadedFile::fake()->create('entrega_admin_panel.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->entregadoStatus->id,
                'delivery_photo' => $deliveryPhoto,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
                'client_location' => '4.609710, -74.081750 (Admin Show)',
                'observation' => 'Entrega confirmada desde panel administrativo',
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame($this->entregadoStatus->id, $order->order_status_id);
        $this->assertNotNull($order->delivery_photo_path);
        $this->assertEqualsWithDelta(4.609710, $order->delivery_latitude, 0.0001);
        $this->assertEqualsWithDelta(-74.081750, $order->delivery_longitude, 0.0001);
        $this->assertSame('4.609710, -74.081750 (Admin Show)', $order->client_location);
        Storage::disk('public')->assertExists($order->delivery_photo_path);
    }

    /**
     * Test 11: Delivery fails validation when photo is not an image (PDF or TXT).
     */
    public function test_delivery_fails_validation_when_photo_is_not_an_image(): void
    {
        $order = $this->createOrderFor($this->sellerA, $this->despachadoStatus);

        // 1. Upload a fake PDF file
        $pdfFile = UploadedFile::fake()->create('documento.pdf', 300, 'application/pdf');

        $responsePdf = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_photo' => $pdfFile,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
            ]);

        $responsePdf->assertSessionHasErrors(['delivery_photo']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
        $this->assertNull($order->delivery_photo_path);

        // 2. Upload a fake TXT file
        $txtFile = UploadedFile::fake()->create('notas.txt', 50, 'text/plain');

        $responseTxt = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_photo' => $txtFile,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
            ]);

        $responseTxt->assertSessionHasErrors(['delivery_photo']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
        $this->assertNull($order->delivery_photo_path);

        // 3. Also verify admin backup route rejects non-image delivery photo
        $responseAdminPdf = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->entregadoStatus->id,
                'delivery_photo' => $pdfFile,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => -74.081750,
            ]);

        $responseAdminPdf->assertSessionHasErrors(['delivery_photo']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
        $this->assertNull($order->delivery_photo_path);
    }

    /**
     * Test 12: Delivery fails validation when coordinates are out of bounds.
     */
    public function test_delivery_fails_validation_when_coordinates_are_out_of_bounds(): void
    {
        $order = $this->createOrderFor($this->sellerA, $this->despachadoStatus);
        $photo = UploadedFile::fake()->create('entrega.jpg', 200, 'image/jpeg');

        // 1. Submitting latitude out of bounds (150.0 > 90.0)
        $responseLat = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_photo' => $photo,
                'delivery_latitude' => 150.0,
                'delivery_longitude' => -74.081750,
            ]);

        $responseLat->assertSessionHasErrors(['delivery_latitude']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
        $this->assertNull($order->delivery_latitude);

        // 2. Submitting longitude out of bounds (200.0 > 180.0)
        $responseLng = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_photo' => $photo,
                'delivery_latitude' => 4.609710,
                'delivery_longitude' => 200.0,
            ]);

        $responseLng->assertSessionHasErrors(['delivery_longitude']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
        $this->assertNull($order->delivery_longitude);

        // 3. Submitting both coordinates out of bounds (-150.0 < -90.0 and 200.0 > 180.0)
        $responseBoth = $this->actingAs($this->sellerA)
            ->post(route('site.seller.orders.deliver', $order), [
                'delivery_photo' => $photo,
                'delivery_latitude' => -150.0,
                'delivery_longitude' => 200.0,
            ]);

        $responseBoth->assertSessionHasErrors(['delivery_latitude', 'delivery_longitude']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);

        // 4. Also verify admin backup route rejects coordinates out of bounds
        $responseAdminLat = $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->entregadoStatus->id,
                'delivery_photo' => $photo,
                'delivery_latitude' => 150.0,
                'delivery_longitude' => -74.081750,
            ]);

        $responseAdminLat->assertSessionHasErrors(['delivery_latitude']);
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);
        $this->assertNull($order->delivery_latitude);
    }

    /**
     * Test 13: Admin cannot deliver orders in terminal or already-delivered states via seller delivery endpoint.
     */
    public function test_admin_cannot_deliver_orders_in_terminal_or_already_delivered_states(): void
    {
        $blockedSlugs = [
            OrderStatus::SLUG_DEVUELTO,
            OrderStatus::SLUG_RECHAZADO,
            OrderStatus::SLUG_COBRADO,
            OrderStatus::SLUG_ENTREGADO,
        ];

        foreach ($blockedSlugs as $slug) {
            $status = OrderStatus::where('slug', $slug)->firstOrFail();
            $order = $this->createOrderFor($this->sellerA, $status);
            $photo = UploadedFile::fake()->create('entrega_bloqueada.jpg', 200, 'image/jpeg');

            $response = $this->actingAs($this->admin)
                ->post(route('site.seller.orders.deliver', $order), [
                    'delivery_photo' => $photo,
                    'delivery_latitude' => 4.609710,
                    'delivery_longitude' => -74.081750,
                ]);

            $response->assertSessionHasErrors('order_status_id');

            $order->refresh();
            $this->assertSame($status->id, $order->order_status_id);
            $this->assertNull($order->delivery_photo_path);
            $this->assertDatabaseMissing('order_status_histories', ['order_id' => $order->id]);
        }

        $this->assertEmpty(Storage::disk('public')->files('orders/deliveries'));
    }
}
