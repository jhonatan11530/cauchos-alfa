<?php

namespace Tests\Feature\Validation;

use App\Models\Catalog;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiPromptService;
use App\Support\SellerCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

/**
 * Tier 4: Real-World Application Scenarios Test Suite.
 *
 * Verifies complete multi-step end-to-end user workflows:
 * - Scenario 1: Admin Catalog & Inventory Management Workflow
 * - Scenario 2: Admin Order Processing Lifecycle
 * - Scenario 3: Seller Authentication & Order Placement Workflow
 * - Scenario 4: Seller Dispatched Order Delivery Workflow
 * - Scenario 5: Multi-Form Error Recovery & Re-submission Flow
 * - Scenario 6: Prompt & System Configuration Workflow
 */
class Tier4_ApplicationScenariosTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    private User $admin;
    private OrderStatus $creadoStatus;
    private OrderStatus $despachadoStatus;
    private OrderStatus $entregadoStatus;
    private OrderStatus $cobradoStatus;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->createRoles();
        $this->admin = $this->createAdmin();

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
            ['name' => 'Pedido cobrado', 'color' => '#59d05d', 'sort_order' => 7, 'is_final' => true, 'is_active' => true]
        );
    }

    /**
     * Scenario 1: Administrator Catalog & Inventory Management Workflow
     * Flow: Admin logs in -> Creates category -> Creates product -> Updates product -> Creates catalog linking product -> Verifies catalog view and relations.
     */
    public function test_scenario_1_admin_catalog_and_inventory_management_workflow(): void
    {
        // 1. Admin login
        $loginRes = $this->post(route('login.store'), [
            'email' => $this->admin->email,
            'password' => 'secret-password',
        ]);
        $loginRes->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);

        // 2. Admin creates category
        $catRes = $this->actingAs($this->admin)->post(route('categoria.store'), [
            'name' => 'Llantas de Carga Pesada',
            'description' => 'Categoría para vehículos comerciales de alto tonelaje',
            'is_active' => '1',
        ]);
        $catRes->assertRedirect(route('categoria.index'));
        $category = Category::where('name', 'Llantas de Carga Pesada')->firstOrFail();
        $this->assertTrue($category->is_active);

        // 3. Admin creates product in the category
        $prodRes = $this->actingAs($this->admin)->post(route('productos.store'), [
            'category_id' => $category->id,
            'name' => 'Llanta Radial 12R22.5 Reforzada',
            'reference' => 'RAD-12R225',
            'availability' => 'disponible',
            'description' => 'Llanta diseñada para ejes de tracción en carretera mixta.',
            'features' => '16 lonas, resistencia a cortes laterales.',
            'image' => UploadedFile::fake()->create('llanta_12r225.jpg', 120, 'image/jpeg'),
            'is_active' => '1',
        ]);
        $prodRes->assertRedirect(route('productos.index'));
        $product = Product::where('name', 'Llanta Radial 12R22.5 Reforzada')->firstOrFail();
        $this->assertSame($category->id, $product->category_id);

        // 4. Admin updates product
        $updRes = $this->actingAs($this->admin)->put(route('productos.update', $product), [
            'name' => 'Llanta Radial 12R22.5 Reforzada Plus',
            'reference' => 'RAD-12R225-PLUS',
            'availability' => 'disponible',
            'is_active' => '1',
        ]);
        $updRes->assertRedirect(route('productos.index'));
        $product->refresh();
        $this->assertSame('Llanta Radial 12R22.5 Reforzada Plus', $product->name);

        // 5. Admin creates catalog linking the product
        $catDocRes = $this->actingAs($this->admin)->post(route('catalogos.store'), [
            'name' => 'Catálogo Comercial Transporte 2026',
            'template' => 'catalogo-web',
            'company_name' => 'Cauchos Alfa Industrial',
            'contact_email' => 'transporte@cauchosalfa.com',
            'products' => [$product->id],
            'is_active' => '1',
        ]);
        $catDocRes->assertRedirect(route('catalogos.index'));

        // 6. Verification of catalog association
        $catalog = Catalog::where('name', 'Catálogo Comercial Transporte 2026')->firstOrFail();
        $this->assertTrue($catalog->products->contains('id', $product->id));
        $this->assertDatabaseHas('catalog_product', [
            'catalog_id' => $catalog->id,
            'product_id' => $product->id,
        ]);
    }

    /**
     * Scenario 2: Administrator Order Processing Lifecycle
     * Flow: Admin logs in -> Creates client -> Creates order with items -> Transitions: Creado -> Despachado -> Entregado -> Cobrado.
     */
    public function test_scenario_2_admin_order_processing_lifecycle(): void
    {
        // 1. Admin login
        $this->actingAs($this->admin);

        // 2. Admin creates client
        $clientRes = $this->post(route('clientes.store'), [
            'name' => 'Consorcio Vial del Pacífico',
            'document' => '901234888-9',
            'email' => 'compras@consorciopacifico.com',
            'phone' => '3189991122',
            'address' => 'Vía al Mar Km 15',
            'city' => 'Buenaventura',
            'is_active' => '1',
        ]);
        $clientRes->assertRedirect(route('clientes.index'));
        $client = Client::where('document', '901234888-9')->firstOrFail();

        // 3. Admin creates order with 2 products
        $p1 = $this->createProduct(null, ['name' => 'Llanta Camión 1']);
        $p2 = $this->createProduct(null, ['name' => 'Llanta Camión 2']);

        $orderRes = $this->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'notes' => 'Despacho prioritario para maquinaria en obra',
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 4],
                ['product_id' => $p2->id, 'quantity' => 2],
            ],
        ]);
        $orderRes->assertRedirect(route('pedidos.index'));
        $order = Order::where('client_id', $client->id)->firstOrFail();
        $this->assertSame($this->creadoStatus->id, $order->order_status_id);
        $this->assertCount(2, $order->items);

        // 4. Transition 1: Creado -> Despachado
        $dispRes = $this->from(route('pedidos.show', $order))->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->despachadoStatus->id,
            'dispatch_photo' => UploadedFile::fake()->create('foto_despacho_vial.jpg', 150, 'image/jpeg'),
            'signature_file' => UploadedFile::fake()->create('firma_autorizacion.png', 50, 'image/png'),
            'payment_term' => 'contado',
            'observation' => 'Cargamento inspeccionado y despachado en camión #4',
        ]);
        $dispRes->assertRedirect(route('pedidos.show', $order));
        $order->refresh();
        $this->assertSame($this->despachadoStatus->id, $order->order_status_id);

        // 5. Transition 2: Despachado -> Entregado
        $delivRes = $this->from(route('pedidos.show', $order))->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('foto_entrega_consorcio.jpg', 180, 'image/jpeg'),
            'delivery_latitude' => 3.8833,
            'delivery_longitude' => -77.0167,
            'client_location' => 'Buenaventura, Obra Muelle',
            'payment_term' => 'contado',
            'observation' => 'Entregado en sitio de obra a ingeniero encargado',
        ]);
        $delivRes->assertRedirect(route('pedidos.show', $order));
        $order->refresh();
        $this->assertSame($this->entregadoStatus->id, $order->order_status_id);
        $this->assertEquals(3.8833, $order->delivery_latitude);

        // 6. Transition 3: Entregado -> Cobrado
        $cobRes = $this->from(route('pedidos.show', $order))->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->cobradoStatus->id,
            'payment_support' => UploadedFile::fake()->create('recibo_consorcio.pdf', 120, 'application/pdf'),
            'payment_responsible_id' => $this->admin->id,
            'observation' => 'Transferencia bancaria confirmada con soporte fiscal.',
        ]);
        $cobRes->assertRedirect(route('pedidos.show', $order));
        $order->refresh();
        $this->assertSame($this->cobradoStatus->id, $order->order_status_id);

        // 7. Verify all transitions logged in OrderStatusHistory
        $histories = OrderStatusHistory::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(4, $histories); // Creado, Despachado, Entregado, Cobrado
        $this->assertSame($this->creadoStatus->id, $histories[0]->new_status_id);
        $this->assertSame($this->despachadoStatus->id, $histories[1]->new_status_id);
        $this->assertSame($this->entregadoStatus->id, $histories[2]->new_status_id);
        $this->assertSame($this->cobradoStatus->id, $histories[3]->new_status_id);
    }

    /**
     * Scenario 3: Seller Authentication & Order Placement Workflow
     * Flow: Admin registers seller -> Seller logs in -> Adds products to cart -> Checks out -> Order created -> Cart cleared.
     */
    public function test_scenario_3_seller_authentication_and_order_placement_workflow(): void
    {
        // 1. Admin creates seller
        $this->actingAs($this->admin)->post(route('vendedores.store'), [
            'name' => 'Santiago Rendón',
            'seller_code' => 'VEN-SR-100',
            'phone' => '3145556677',
            'is_active' => '1',
        ]);
        $seller = User::where('seller_code', 'VEN-SR-100')->firstOrFail();

        // 2. Seller authenticates via code
        $this->post(route('site.seller.auth'), [
            'code' => 'VEN-SR-100',
        ])->assertRedirect(route('site.catalog'));
        $this->assertAuthenticatedAs($seller);

        // 3. Products available
        $prodA = $this->createProduct(null, ['name' => 'Llanta MTB 29x2.25']);
        $prodB = $this->createProduct(null, ['name' => 'Llanta Ruta 700x25c']);

        // 4. Seller adds products to cart from catalog & detail
        $this->actingAs($seller)->post(route('site.seller.add'), [
            'product_id' => $prodA->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->actingAs($seller)->post(route('site.seller.add'), [
            'product_id' => $prodB->id,
            'quantity' => 4,
        ])->assertRedirect();

        $this->assertEquals([
            $prodA->id => 2,
            $prodB->id => 4,
        ], session('seller_cart'));

        // 5. Seller proceeds to checkout
        $client = $this->createClient(['name' => 'Taller Ciclo Valle']);
        $checkoutRes = $this->actingAs($seller)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'factura_electronica',
            'notes' => 'Despachar el viernes por la mañana',
            'items' => [
                ['product_id' => $prodA->id, 'quantity' => 2],
                ['product_id' => $prodB->id, 'quantity' => 4],
            ],
            'from_seller_site' => '1',
        ]);

        $checkoutRes->assertRedirect(route('site.catalog'));
        $checkoutRes->assertSessionHas('success');

        // 6. Verify order persisted and cart emptied
        $order = Order::where('client_id', $client->id)->where('created_by', $seller->id)->firstOrFail();
        $this->assertSame('factura_electronica', $order->billing_type);
        $this->assertCount(2, $order->items);
        $this->assertTrue(app(SellerCart::class)->isEmpty());
    }

    /**
     * Scenario 4: Seller Dispatched Order Delivery Workflow
     * Flow: Seller logs in -> Dispatched order exists -> Seller opens delivery modal -> Submits photo & GPS -> Status becomes entregado.
     */
    public function test_scenario_4_seller_dispatched_order_delivery_workflow(): void
    {
        $seller = $this->createSeller(['seller_code' => 'VEN-LOG-01']);
        $client = $this->createClient(['name' => 'Cliente Recepción Final']);

        $order = Order::create([
            'code' => 'PED-RUTA-DELIV-01',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $seller->id,
            'payment_term' => 'contado',
        ]);

        $this->actingAs($seller);

        // Seller accesses delivery modal and submits delivery
        $deliverRes = $this->post(route('site.seller.orders.deliver', $order), [
            'delivery_photo' => UploadedFile::fake()->create('comprobante_entrega_gps.jpg', 200, 'image/jpeg'),
            'delivery_latitude' => 4.60971,
            'delivery_longitude' => -74.08175,
            'client_location' => 'Bogotá, Bodega Central',
            'observation' => 'Entrega finalizada con éxito y recibida por portería.',
            'payment_term' => 'contado',
        ]);

        $deliverRes->assertRedirect(route('site.seller.orders'));
        $deliverRes->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($this->entregadoStatus->id, $order->order_status_id);
        $this->assertEquals(4.60971, $order->delivery_latitude);
        $this->assertEquals(-74.08175, $order->delivery_longitude);

        // Verify history
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'new_status_id' => $this->entregadoStatus->id,
            'user_id' => $seller->id,
        ]);
    }

    /**
     * Scenario 5: Multi-Form Error Recovery & Re-submission Flow
     * Flow: Form rejected with validation error -> User corrects fields -> Submits valid -> Successfully created.
     */
    public function test_scenario_5_multi_form_error_recovery_and_resubmission_flow(): void
    {
        $this->actingAs($this->admin);

        // 1. Client form rejected on bad email
        $badClientRes = $this->from(route('clientes.create'))->post(route('clientes.store'), [
            'name' => 'Cliente Con Error',
            'email' => 'invalid-email-address',
        ]);
        $badClientRes->assertRedirect(route('clientes.create'));
        $badClientRes->assertSessionHasErrors(['email']);
        $this->assertDatabaseMissing('clients', ['name' => 'Cliente Con Error']);

        // 2. Client form corrected and resubmitted
        $goodClientRes = $this->from(route('clientes.create'))->post(route('clientes.store'), [
            'name' => 'Cliente Con Error Corregido',
            'email' => 'cliente.corregido@cauchosalfa.com',
            'is_active' => '1',
        ]);
        $goodClientRes->assertRedirect(route('clientes.index'));
        $client = Client::where('email', 'cliente.corregido@cauchosalfa.com')->firstOrFail();

        // 3. Order form rejected with empty items
        $badOrderRes = $this->from(route('pedidos.create'))->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [],
        ]);
        $badOrderRes->assertRedirect(route('pedidos.create'));
        $badOrderRes->assertSessionHasErrors(['items']);

        // 4. Order form corrected with valid items and resubmitted
        $product = $this->createProduct();
        $goodOrderRes = $this->from(route('pedidos.create'))->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);
        $goodOrderRes->assertRedirect(route('pedidos.index'));
        $this->assertDatabaseHas('orders', ['client_id' => $client->id]);
    }

    /**
     * Scenario 6: Prompt & System Configuration Workflow
     * Flow: Admin visits settings -> Submits prompt < 10 chars (rejected) -> Submits valid prompt -> Persisted in DB and cache synchronized.
     */
    public function test_scenario_6_prompt_and_system_configuration_workflow(): void
    {
        $this->actingAs($this->admin);

        // 1. Settings view renders
        $viewRes = $this->get(route('settings.index'));
        $viewRes->assertOk();
        $viewRes->assertSee('id="promptForm"', false);

        // 2. Short prompt rejected (< 10 chars)
        $badPromptRes = $this->from(route('settings.index'))->put(route('settings.update'), [
            'prompt_gemini' => 'llanta',
        ]);
        $badPromptRes->assertRedirect(route('settings.index'));
        $badPromptRes->assertSessionHasErrors(['prompt_gemini']);

        // 3. Valid prompt accepted (>= 10 chars)
        $detailedPrompt = 'Fotografía de producto profesional con iluminación balanceada, fondo blanco sin reflejos molestos y máxima nitidez en bordes.';

        $goodPromptRes = $this->from(route('settings.index'))->put(route('settings.update'), [
            'prompt_gemini' => $detailedPrompt,
        ]);
        $goodPromptRes->assertRedirect(route('settings.index'));
        $goodPromptRes->assertSessionHas('success');

        // 4. Verify persisted in DB and cached
        $this->assertDatabaseHas('settings', [
            'key' => GeminiPromptService::PROMPT_KEY,
            'value' => $detailedPrompt,
        ]);
        $this->assertSame($detailedPrompt, GeminiPromptService::get(GeminiPromptService::PROMPT_KEY));
    }
}
