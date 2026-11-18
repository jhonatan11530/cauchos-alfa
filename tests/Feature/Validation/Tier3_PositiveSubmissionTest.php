<?php

namespace Tests\Feature\Validation;

use App\Models\Catalog;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\GeminiPromptService;
use App\Support\SellerCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

/**
 * Tier 3: Positive Submission & Backend E2E Test Suite.
 *
 * Verifies that valid form submissions across all 17 forms:
 * - Reach controller endpoints without validation error
 * - Return expected HTTP 302 redirect responses
 * - Set session flash messages (toastr success notifications)
 * - Persist mutations in SQLite database with full data integrity
 */
class Tier3_PositiveSubmissionTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private OrderStatus $creadoStatus;

    private OrderStatus $despachadoStatus;

    private OrderStatus $entregadoStatus;

    private OrderStatus $cobradoStatus;

    private OrderStatus $devueltoStatus;

    private OrderStatus $rechazadoStatus;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->createRoles();
        $this->admin = $this->createAdmin();
        $this->seller = $this->createSeller();

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
        $this->devueltoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_DEVUELTO],
            ['name' => 'Pedido devuelto', 'color' => '#e83e8c', 'sort_order' => 8, 'is_final' => true, 'is_active' => true]
        );
        $this->rechazadoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_RECHAZADO],
            ['name' => 'Pedido rechazado', 'color' => '#dc3545', 'sort_order' => 9, 'is_final' => true, 'is_cancelled' => true, 'is_active' => true]
        );
    }

    // =========================================================================
    // 1. Admin Login Positive Submission
    // =========================================================================

    public function test_admin_login_positive_submission(): void
    {
        $response = $this->post(route('login.store'), [
            'email' => 'admin@test.com',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);
    }

    // =========================================================================
    // 2. Category Store & Update Positive Submissions
    // =========================================================================

    public function test_category_store_positive_submission(): void
    {
        $response = $this->actingAs($this->admin)->post(route('categoria.store'), [
            'name' => 'Neumáticos de Competición',
            'description' => 'Línea de alto desempeño',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('categoria.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('categories', [
            'name' => 'Neumáticos de Competición',
            'is_active' => 1,
        ]);
    }

    public function test_category_update_positive_submission(): void
    {
        $category = $this->createCategory(['name' => 'Neumáticos Antiguos']);

        $response = $this->actingAs($this->admin)->put(route('categoria.update', $category), [
            'name' => 'Neumáticos Renovados',
            'description' => 'Descripción actualizada',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('categoria.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Neumáticos Renovados',
        ]);
    }

    // =========================================================================
    // 3. Client Store & Update Positive Submissions
    // =========================================================================

    public function test_client_store_positive_submission(): void
    {
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Distribuidora Automotriz del Valle',
            'document' => '900123456-7',
            'email' => 'ventas@distrivalle.com',
            'phone' => '3157894561',
            'address' => 'Calle 10 # 40-50',
            'city' => 'Cali',
            'notes' => 'Cliente VIP',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('clients', [
            'name' => 'Distribuidora Automotriz del Valle',
            'email' => 'ventas@distrivalle.com',
            'city' => 'Cali',
        ]);
    }

    public function test_client_update_positive_submission(): void
    {
        $client = $this->createClient(['name' => 'Cliente Inicial']);

        $response = $this->actingAs($this->admin)->put(route('clientes.update', $client), [
            'name' => 'Cliente Modificado S.A.',
            'document' => '800555444-1',
            'email' => 'contacto@modificado.com',
            'phone' => '3201112233',
            'address' => 'Avenida 6N # 20-30',
            'city' => 'Palmira',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Cliente Modificado S.A.',
            'city' => 'Palmira',
        ]);
    }

    // =========================================================================
    // 4. Product Store & Update Positive Submissions
    // =========================================================================

    public function test_product_store_positive_submission(): void
    {
        $category = $this->createCategory();

        $response = $this->actingAs($this->admin)->post(route('productos.store'), [
            'category_id' => $category->id,
            'name' => 'Llanta Todoterreno 265/70 R16',
            'reference' => 'TT-26570',
            'availability' => 'disponible',
            'description' => 'Ideal para terrenos difíciles y trocha.',
            'features' => 'Banda de rodadura reforzada',
            'image' => UploadedFile::fake()->create('llanta_principal.jpg', 200, 'image/jpeg'),
            'images' => [
                UploadedFile::fake()->create('galeria_1.jpg', 150, 'image/jpeg'),
                UploadedFile::fake()->create('galeria_2.jpg', 150, 'image/jpeg'),
            ],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('productos.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', [
            'name' => 'Llanta Todoterreno 265/70 R16',
            'reference' => 'TT-26570',
            'availability' => 'disponible',
        ]);
    }

    public function test_product_update_positive_submission(): void
    {
        $product = $this->createProduct(null, ['name' => 'Producto Antiguo']);

        $response = $this->actingAs($this->admin)->put(route('productos.update', $product), [
            'name' => 'Producto Actualizado 2026',
            'reference' => 'REF-ACT-01',
            'availability' => 'bajo pedido',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('productos.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Producto Actualizado 2026',
            'availability' => 'bajo pedido',
        ]);
    }

    // =========================================================================
    // 5. Catalog Store & Update Positive Submissions
    // =========================================================================

    public function test_catalog_store_positive_submission(): void
    {
        $p1 = $this->createProduct(null, ['name' => 'Prod Cat 1']);
        $p2 = $this->createProduct(null, ['name' => 'Prod Cat 2']);

        $response = $this->actingAs($this->admin)->post(route('catalogos.store'), [
            'name' => 'Catálogo Especial de Verano',
            'template' => 'default',
            'company_name' => 'Cauchos Alfa Colombia',
            'contact_email' => 'catalogo@cauchosalfa.com',
            'contact_phone' => '3009998877',
            'contact_address' => 'Zona Industrial Cali',
            'logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
            'products' => [$p1->id, $p2->id],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('catalogos.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('catalogs', [
            'name' => 'Catálogo Especial de Verano',
            'template' => 'default',
        ]);
        $catalog = Catalog::where('name', 'Catálogo Especial de Verano')->first();
        $this->assertCount(2, $catalog->products);
    }

    public function test_catalog_update_positive_submission(): void
    {
        $catalog = Catalog::create([
            'name' => 'Catálogo Base',
            'template' => 'default',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('catalogos.update', $catalog), [
            'name' => 'Catálogo Base Modificado',
            'template' => 'catalogo-web',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('catalogos.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('catalogs', [
            'id' => $catalog->id,
            'name' => 'Catálogo Base Modificado',
            'template' => 'catalogo-web',
        ]);
    }

    // =========================================================================
    // 6. Admin Order Store & Update Positive Submissions
    // =========================================================================

    public function test_admin_order_store_positive_submission(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'factura_electronica',
            'notes' => 'Entrega urgente en bodega norte',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertRedirect(route('pedidos.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'billing_type' => 'factura_electronica',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_admin_order_update_positive_submission(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        $order = Order::create([
            'code' => 'PED-UPD-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('pedidos.update', $order), [
            'code' => 'PED-UPD-001-MOD',
            'client_id' => $client->id,
            'billing_type' => 'factura_electronica',
            'ordered_at' => now()->toDateString(),
            'notes' => 'Notas modificadas',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 12],
            ],
        ]);

        $response->assertRedirect(route('pedidos.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'code' => 'PED-UPD-001-MOD',
            'billing_type' => 'factura_electronica',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'quantity' => 12,
        ]);
    }

    // =========================================================================
    // 7. Admin Order Status Transition Positive Submissions
    // =========================================================================

    public function test_admin_order_status_transition_despachado_positive_submission(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TR-DESP',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
                'dispatch_photo' => UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg'),
                'signature_file' => UploadedFile::fake()->create('firma.png', 50, 'image/png'),
                'payment_term' => 'contado',
            ]);

        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status_id' => $this->despachadoStatus->id,
            'payment_term' => 'contado',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'new_status_id' => $this->despachadoStatus->id,
        ]);
    }

    public function test_admin_order_status_transition_entregado_positive_submission(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TR-ENTR',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->entregadoStatus->id,
                'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
                'delivery_latitude' => 3.4516,
                'delivery_longitude' => -76.5320,
                'payment_term' => 'contado',
            ]);

        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_latitude' => 3.4516,
        ]);
    }

    public function test_admin_order_status_transition_cobrado_positive_submission(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TR-COBR',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->entregadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->cobradoStatus->id,
                'payment_support' => UploadedFile::fake()->create('soporte_pago.pdf', 100, 'application/pdf'),
                'payment_responsible_id' => $this->admin->id,
            ]);

        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status_id' => $this->cobradoStatus->id,
            'payment_responsible_id' => $this->admin->id,
        ]);
    }

    public function test_admin_order_status_transition_devuelto_positive_submission(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TR-DEV',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->devueltoStatus->id,
                'return_justification_file' => UploadedFile::fake()->create('justificacion.pdf', 80, 'application/pdf'),
            ]);

        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status_id' => $this->devueltoStatus->id,
        ]);
    }

    public function test_admin_order_status_transition_rechazado_positive_submission(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TR-RECH',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->rechazadoStatus->id,
                'rejection_reason' => 'Cliente canceló el pedido por falta de presupuesto.',
            ]);

        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status_id' => $this->rechazadoStatus->id,
        ]);
    }

    // =========================================================================
    // 8. Admin Seller Store & Update Positive Submissions
    // =========================================================================

    public function test_admin_seller_store_positive_submission(): void
    {
        $response = $this->actingAs($this->admin)->post(route('vendedores.store'), [
            'name' => 'Laura Marcela Vendedora',
            'seller_code' => 'VEN-LM-99',
            'phone' => '3114445566',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('vendedores.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'name' => 'Laura Marcela Vendedora',
            'seller_code' => 'VEN-LM-99',
            'is_active' => 1,
        ]);
    }

    public function test_admin_seller_update_positive_submission(): void
    {
        $seller = $this->createSeller([
            'name' => 'Vendedor Anterior',
            'email' => 'vendedor.anterior@test.com',
            'seller_code' => 'VEN-ANT-01',
        ]);

        $response = $this->actingAs($this->admin)->put(route('vendedores.update', $seller), [
            'name' => 'Vendedor Nombre Nuevo',
            'seller_code' => 'VEN-NUEVO-01',
            'phone' => '3129990011',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('vendedores.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $seller->id,
            'name' => 'Vendedor Nombre Nuevo',
            'seller_code' => 'VEN-NUEVO-01',
        ]);
    }

    // =========================================================================
    // 9. Admin User Store & Update Positive Submissions
    // =========================================================================

    public function test_admin_user_store_positive_submission(): void
    {
        $role = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);

        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'role_id' => $role->id,
            'name' => 'Supervisora Logística',
            'email' => 'supervisora@cauchosalfa.com',
            'phone' => '3001237890',
            'password' => 'seguraPassword123',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'email' => 'supervisora@cauchosalfa.com',
            'name' => 'Supervisora Logística',
        ]);
    }

    public function test_admin_user_update_positive_submission(): void
    {
        $user = User::create([
            'name' => 'Usuario Antes',
            'email' => 'usuario.antes@cauchosalfa.com',
            'password' => 'clave12345678',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('usuarios.update', $user), [
            'name' => 'Usuario Despues',
            'email' => 'usuario.despues@cauchosalfa.com',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Usuario Despues',
            'email' => 'usuario.despues@cauchosalfa.com',
        ]);
    }

    // =========================================================================
    // 10. Admin Order Statuses CRUD Positive Submissions
    // =========================================================================

    public function test_admin_order_status_crud_store_positive_submission(): void
    {
        $response = $this->actingAs($this->admin)->post(route('estados-pedido.store'), [
            'name' => 'Inspección de Calidad',
            'color' => '#17a2b8',
            'sort_order' => 3,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('estados-pedido.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('order_statuses', [
            'name' => 'Inspección de Calidad',
            'color' => '#17a2b8',
            'sort_order' => 3,
        ]);
    }

    public function test_admin_order_status_crud_update_positive_submission(): void
    {
        $status = OrderStatus::create([
            'name' => 'Estado Temporal',
            'slug' => 'estado-temporal',
            'color' => '#6c757d',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('estados-pedido.update', $status), [
            'name' => 'Estado Definitivo',
            'color' => '#28a745',
            'sort_order' => 4,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('estados-pedido.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('order_statuses', [
            'id' => $status->id,
            'name' => 'Estado Definitivo',
            'color' => '#28a745',
        ]);
    }

    // =========================================================================
    // 11. Admin Settings Gemini Prompt Positive Submission
    // =========================================================================

    public function test_admin_settings_gemini_prompt_positive_submission(): void
    {
        $newPrompt = 'Optimiza esta fotografía de llanta con fondo blanco puro de estudio, sombras naturales y alta nitidez.';

        $response = $this->actingAs($this->admin)->put(route('settings.update'), [
            'prompt_gemini' => $newPrompt,
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('settings', [
            'key' => GeminiPromptService::PROMPT_KEY,
            'value' => $newPrompt,
        ]);
    }

    // =========================================================================
    // 12. Seller Panel Positive Submissions
    // =========================================================================

    public function test_seller_login_positive_submission(): void
    {
        $response = $this->post(route('site.seller.auth'), [
            'code' => $this->seller->seller_code,
        ]);

        $response->assertRedirect(route('site.catalog'));
        $this->assertAuthenticatedAs($this->seller);
    }

    public function test_seller_catalog_add_to_cart_positive_submission(): void
    {
        $product = $this->createProduct();

        $response = $this->actingAs($this->seller)->post(route('site.seller.add'), [
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals([$product->id => 4], session('seller_cart'));
    }

    public function test_seller_product_detail_add_to_cart_positive_submission(): void
    {
        $product = $this->createProduct();

        $response = $this->actingAs($this->seller)
            ->from(route('site.product', ['id' => $product->id, 'slug' => 'producto-test']))
            ->post(route('site.seller.add'), [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals([$product->id => 2], session('seller_cart'));
    }

    public function test_seller_checkout_order_positive_submission(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        // Populate cart
        app(SellerCart::class)->add($product->id, 3);
        $this->assertFalse(app(SellerCart::class)->isEmpty());

        $response = $this->actingAs($this->seller)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'notes' => 'Pedido enviado desde panel de vendedor',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
            'from_seller_site' => '1',
        ]);

        $response->assertRedirect(route('site.catalog'));
        $response->assertSessionHas('success');

        // Order created in DB
        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'created_by' => $this->seller->id,
        ]);

        // Cart session emptied
        $this->assertTrue(app(SellerCart::class)->isEmpty());
    }

    public function test_seller_delivery_modal_positive_submission(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-SELLER-DELIV',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->seller->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->seller)->post(route('site.seller.orders.deliver', $order), [
            'delivery_photo' => UploadedFile::fake()->create('foto_entrega_vendedor.jpg', 150, 'image/jpeg'),
            'delivery_latitude' => 4.60971,
            'delivery_longitude' => -74.08175,
            'observation' => 'Entrega presencial confirmada con el cliente.',
            'payment_term' => 'contado',
        ]);

        $response->assertRedirect(route('site.seller.orders'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_latitude' => 4.60971,
            'delivery_longitude' => -74.08175,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'new_status_id' => $this->entregadoStatus->id,
            'user_id' => $this->seller->id,
        ]);
    }
}
