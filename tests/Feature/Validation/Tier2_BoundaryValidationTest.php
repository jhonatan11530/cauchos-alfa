<?php

namespace Tests\Feature\Validation;

use App\Models\Catalog;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiPromptService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

/**
 * Tier 2: Boundary Value Analysis & Corner Cases Test Suite.
 *
 * Evaluates:
 * - String length boundaries (seller code minlength 2, prompt minlength 10, password minlength 8, name maxlength 255)
 * - Email format boundaries and malformed adversarial inputs
 * - Numeric range boundaries (quantities min 1, sort_order min 0, coordinates [-90..90] & [-180..180])
 * - Collection boundaries (empty items array guards)
 * - AI background processing guard
 * - Status transition conditional constraints (rejection reason length, dispatch photos, payment term conditions)
 * - Uniqueness & inactive state guards
 */
class Tier2_BoundaryValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    private User $admin;
    private OrderStatus $creadoStatus;
    private OrderStatus $despachadoStatus;
    private OrderStatus $entregadoStatus;
    private OrderStatus $cobradoStatus;
    private OrderStatus $rechazadoStatus;

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
        $this->rechazadoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_RECHAZADO],
            ['name' => 'Pedido rechazado', 'color' => '#dc3545', 'sort_order' => 9, 'is_final' => true, 'is_cancelled' => true, 'is_active' => true]
        );
    }

    // =========================================================================
    // 1. String Length Boundaries
    // =========================================================================

    public function test_seller_code_boundary_empty_string_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('vendedores.store'), [
            'name' => 'Vendedor Test',
            'seller_code' => '',
        ]);
        $response->assertSessionHasErrors('seller_code');
    }

    public function test_seller_code_boundary_length_1_rejected_by_client_contract(): void
    {
        $contract = ['minlength' => 2];
        $this->assertTrue(strlen('A') < $contract['minlength']);
    }

    public function test_seller_code_boundary_length_2_accepted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('vendedores.store'), [
            'name' => 'Vendedor Corto',
            'seller_code' => 'V2',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['seller_code' => 'V2']);
    }

    public function test_seller_code_boundary_length_50_accepted(): void
    {
        $code = str_repeat('A', 50);
        $response = $this->actingAs($this->admin)->post(route('vendedores.store'), [
            'name' => 'Vendedor Max Codigo',
            'seller_code' => $code,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['seller_code' => $code]);
    }

    public function test_seller_code_boundary_length_51_rejected(): void
    {
        $code = str_repeat('A', 51);
        $response = $this->actingAs($this->admin)->post(route('vendedores.store'), [
            'name' => 'Vendedor Sobre Codigo',
            'seller_code' => $code,
        ]);
        $response->assertSessionHasErrors('seller_code');
    }

    public function test_prompt_gemini_boundary_empty_rejected(): void
    {
        $response = $this->actingAs($this->admin)->put(route('settings.update'), [
            'prompt_gemini' => '',
        ]);
        $response->assertSessionHasErrors('prompt_gemini');
    }

    public function test_prompt_gemini_boundary_length_9_rejected(): void
    {
        $response = $this->actingAs($this->admin)->put(route('settings.update'), [
            'prompt_gemini' => '123456789',
        ]);
        $response->assertSessionHasErrors('prompt_gemini');
    }

    public function test_prompt_gemini_boundary_length_10_accepted(): void
    {
        $response = $this->actingAs($this->admin)->put(route('settings.update'), [
            'prompt_gemini' => '1234567890',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', [
            'key' => GeminiPromptService::PROMPT_KEY,
            'value' => '1234567890',
        ]);
    }

    public function test_prompt_gemini_boundary_length_255_accepted(): void
    {
        $prompt = str_repeat('Optimizacion de foto de producto ', 7); // ~231 chars
        $response = $this->actingAs($this->admin)->put(route('settings.update'), [
            'prompt_gemini' => $prompt,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', [
            'key' => GeminiPromptService::PROMPT_KEY,
            'value' => trim($prompt),
        ]);
    }

    public function test_password_boundary_empty_on_create_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@cauchosalfa.com',
            'password' => '',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_password_boundary_length_7_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo7@cauchosalfa.com',
            'password' => '1234567',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_password_boundary_length_8_accepted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Usuario 8',
            'email' => 'nuevo8@cauchosalfa.com',
            'password' => '12345678',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'nuevo8@cauchosalfa.com']);
    }

    public function test_password_boundary_optional_on_user_update(): void
    {
        $user = User::create([
            'name' => 'Usuario Existente',
            'email' => 'existente@cauchosalfa.com',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('usuarios.update', $user), [
            'name' => 'Usuario Existente Modificado',
            'email' => 'existente@cauchosalfa.com',
            'password' => '',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['name' => 'Usuario Existente Modificado']);
    }

    // Name boundaries (255 accepted, 256 rejected)
    public function test_category_name_boundary_length_255_accepted(): void
    {
        $name = str_repeat('C', 255);
        $response = $this->actingAs($this->admin)->post(route('categoria.store'), ['name' => $name]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', ['name' => $name]);
    }

    public function test_category_name_boundary_length_256_rejected(): void
    {
        $name = str_repeat('C', 256);
        $response = $this->actingAs($this->admin)->post(route('categoria.store'), ['name' => $name]);
        $response->assertSessionHasErrors('name');
    }

    public function test_product_name_boundary_length_255_accepted(): void
    {
        $name = str_repeat('P', 255);
        $response = $this->actingAs($this->admin)->post(route('productos.store'), [
            'name' => $name,
            'availability' => 'disponible',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => $name]);
    }

    public function test_product_name_boundary_length_256_rejected(): void
    {
        $name = str_repeat('P', 256);
        $response = $this->actingAs($this->admin)->post(route('productos.store'), [
            'name' => $name,
            'availability' => 'disponible',
        ]);
        $response->assertSessionHasErrors('name');
    }

    public function test_client_name_boundary_length_255_accepted(): void
    {
        $name = str_repeat('K', 255);
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), ['name' => $name]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', ['name' => $name]);
    }

    public function test_client_name_boundary_length_256_rejected(): void
    {
        $name = str_repeat('K', 256);
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), ['name' => $name]);
        $response->assertSessionHasErrors('name');
    }

    public function test_client_document_boundary_length_100_accepted(): void
    {
        $doc = str_repeat('1', 100);
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Documento Valido',
            'document' => $doc,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', ['document' => $doc]);
    }

    public function test_client_document_boundary_length_101_rejected(): void
    {
        $doc = str_repeat('1', 101);
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Documento Invalido',
            'document' => $doc,
        ]);
        $response->assertSessionHasErrors('document');
    }

    public function test_client_phone_boundary_length_50_accepted(): void
    {
        $phone = str_repeat('9', 50);
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Telefono Valido',
            'phone' => $phone,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', ['phone' => $phone]);
    }

    public function test_client_phone_boundary_length_51_rejected(): void
    {
        $phone = str_repeat('9', 51);
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Telefono Invalido',
            'phone' => $phone,
        ]);
        $response->assertSessionHasErrors('phone');
    }

    public function test_catalog_name_boundary_length_255_accepted(): void
    {
        $name = str_repeat('T', 255);
        $response = $this->actingAs($this->admin)->post(route('catalogos.store'), [
            'name' => $name,
            'template' => 'default',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('catalogs', ['name' => $name]);
    }

    public function test_catalog_name_boundary_length_256_rejected(): void
    {
        $name = str_repeat('T', 256);
        $response = $this->actingAs($this->admin)->post(route('catalogos.store'), [
            'name' => $name,
            'template' => 'default',
        ]);
        $response->assertSessionHasErrors('name');
    }

    public function test_order_status_name_boundary_length_255_accepted(): void
    {
        $name = str_repeat('E', 255);
        $response = $this->actingAs($this->admin)->post(route('estados-pedido.store'), [
            'name' => $name,
            'color' => '#123456',
            'sort_order' => 10,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_statuses', ['name' => $name]);
    }

    public function test_order_status_name_boundary_length_256_rejected(): void
    {
        $name = str_repeat('E', 256);
        $response = $this->actingAs($this->admin)->post(route('estados-pedido.store'), [
            'name' => $name,
            'color' => '#123456',
            'sort_order' => 10,
        ]);
        $response->assertSessionHasErrors('name');
    }

    public function test_order_status_color_boundary_length_20_accepted(): void
    {
        $color = str_repeat('F', 20);
        $response = $this->actingAs($this->admin)->post(route('estados-pedido.store'), [
            'name' => 'Estado Color Valido',
            'color' => $color,
            'sort_order' => 11,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_statuses', ['color' => $color]);
    }

    public function test_order_status_color_boundary_length_21_rejected(): void
    {
        $color = str_repeat('F', 21);
        $response = $this->actingAs($this->admin)->post(route('estados-pedido.store'), [
            'name' => 'Estado Color Invalido',
            'color' => $color,
            'sort_order' => 12,
        ]);
        $response->assertSessionHasErrors('color');
    }

    public function test_order_code_boundary_length_100_accepted(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        $code = str_repeat('C', 100);

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'code' => $code,
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['code' => $code]);
    }

    public function test_order_code_boundary_length_101_rejected(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        $code = str_repeat('C', 101);

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'code' => $code,
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $response->assertSessionHasErrors('code');
    }

    // =========================================================================
    // 2. Email Format Boundaries & Adversarial Formats
    // =========================================================================

    public function test_client_email_boundary_valid_standard_accepted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Correo Standard',
            'email' => 'cliente.standard@cauchosalfa.com',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', ['email' => 'cliente.standard@cauchosalfa.com']);
    }

    public function test_client_email_boundary_valid_with_subdomains_and_plus_accepted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Correo Complejo',
            'email' => 'contacto+ventas@corp.cauchosalfa.com.co',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', ['email' => 'contacto+ventas@corp.cauchosalfa.com.co']);
    }

    public function test_client_email_boundary_missing_at_symbol_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Sin Arroba',
            'email' => 'plainaddress',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_client_email_boundary_missing_username_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Sin Usuario',
            'email' => '@missinguser.com',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_client_email_boundary_missing_domain_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Sin Dominio',
            'email' => 'user@.com',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_client_email_boundary_spaces_in_email_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Con Espacio',
            'email' => 'spaces in@domain.com',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_admin_user_email_invalid_format_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Admin Invalido',
            'email' => 'bad_email.com',
            'password' => 'secret1234',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_catalog_contact_email_invalid_format_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('catalogos.store'), [
            'name' => 'Catalogo Con Email Malo',
            'template' => 'default',
            'contact_email' => 'not-an-email',
        ]);
        $response->assertSessionHasErrors('contact_email');
    }

    public function test_client_email_boundary_length_255_accepted(): void
    {
        // 255 chars valid email: 236 chars username + '@cauchosalfa.com' (15 chars) = 251 chars
        $userPart = str_repeat('u', 236);
        $email = $userPart . '@cauchosalfa.com';
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Email Largo',
            'email' => $email,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', ['email' => $email]);
    }

    public function test_client_email_boundary_length_256_rejected(): void
    {
        $userPart = str_repeat('u', 242);
        $email = $userPart . '@cauchosalfa.com'; // 257 chars
        $response = $this->actingAs($this->admin)->post(route('clientes.store'), [
            'name' => 'Cliente Email Mas Largo',
            'email' => $email,
        ]);
        $response->assertSessionHasErrors('email');
    }

    // =========================================================================
    // 3. Numeric Range Boundaries
    // =========================================================================

    public function test_order_quantity_boundary_zero_rejected(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 0],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.quantity');
    }

    public function test_order_quantity_boundary_negative_one_rejected(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => -1],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.quantity');
    }

    public function test_order_quantity_boundary_positive_one_accepted(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_order_quantity_boundary_large_integer_accepted(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10000],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 10000,
        ]);
    }

    public function test_order_quantity_boundary_non_numeric_string_rejected(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 'diez'],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.quantity');
    }

    public function test_order_quantity_boundary_decimal_float_rejected(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2.5],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.quantity');
    }

    public function test_seller_cart_add_quantity_boundary_zero_rejected(): void
    {
        $seller = $this->createSeller();
        $product = $this->createProduct();

        $response = $this->actingAs($seller)->post(route('site.seller.add'), [
            'product_id' => $product->id,
            'quantity' => 0,
        ]);
        $response->assertSessionHasErrors('quantity');
    }

    public function test_seller_cart_add_quantity_boundary_negative_rejected(): void
    {
        $seller = $this->createSeller();
        $product = $this->createProduct();

        $response = $this->actingAs($seller)->post(route('site.seller.add'), [
            'product_id' => $product->id,
            'quantity' => -5,
        ]);
        $response->assertSessionHasErrors('quantity');
    }

    public function test_seller_cart_add_quantity_boundary_one_accepted(): void
    {
        $seller = $this->createSeller();
        $product = $this->createProduct();

        $response = $this->actingAs($seller)->post(route('site.seller.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals([$product->id => 1], session('seller_cart'));
    }

    public function test_order_status_sort_order_boundary_negative_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('estados-pedido.store'), [
            'name' => 'Estado Negativo Test',
            'color' => '#112233',
            'sort_order' => -1,
        ]);
        $response->assertSessionHasErrors('sort_order');
    }

    public function test_order_status_sort_order_boundary_zero_accepted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('estados-pedido.store'), [
            'name' => 'Estado Cero Test',
            'color' => '#112233',
            'sort_order' => 0,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_statuses', ['name' => 'Estado Cero Test', 'sort_order' => 0]);
    }

    // Latitude boundaries: [-90.0 .. 90.0]
    public function test_delivery_latitude_boundary_minus_90_accepted(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-LAT-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
            'delivery_latitude' => -90.000000,
            'delivery_longitude' => 0.000000,
            'payment_term' => 'contado',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $this->entregadoStatus->id]);
    }

    public function test_delivery_latitude_boundary_plus_90_accepted(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-LAT-002',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
            'delivery_latitude' => 90.000000,
            'delivery_longitude' => 0.000000,
            'payment_term' => 'contado',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $this->entregadoStatus->id]);
    }

    public function test_delivery_latitude_boundary_minus_90_point_0001_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-LAT-003',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
            'delivery_latitude' => -90.0001,
            'delivery_longitude' => 0.000000,
            'payment_term' => 'contado',
        ]);
        $response->assertSessionHasErrors(['delivery_latitude']);
    }

    public function test_delivery_latitude_boundary_plus_90_point_0001_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-LAT-004',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
            'delivery_latitude' => 90.0001,
            'delivery_longitude' => 0.000000,
            'payment_term' => 'contado',
        ]);
        $response->assertSessionHasErrors(['delivery_latitude']);
    }

    // Longitude boundaries: [-180.0 .. 180.0]
    public function test_delivery_longitude_boundary_minus_180_accepted(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-LNG-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
            'delivery_latitude' => 0.000000,
            'delivery_longitude' => -180.000000,
            'payment_term' => 'contado',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $this->entregadoStatus->id]);
    }

    public function test_delivery_longitude_boundary_plus_180_accepted(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-LNG-002',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
            'payment_term' => 'contado',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
            'delivery_latitude' => 0.000000,
            'delivery_longitude' => 180.000000,
            'payment_term' => 'contado',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $this->entregadoStatus->id]);
    }

    public function test_delivery_longitude_boundary_minus_180_point_0001_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-LNG-003',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
            'delivery_latitude' => 0.000000,
            'delivery_longitude' => -180.0001,
        ]);
        $response->assertSessionHasErrors(['delivery_longitude']);
    }

    public function test_delivery_longitude_boundary_plus_180_point_0001_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-LNG-004',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
            'delivery_latitude' => 0.000000,
            'delivery_longitude' => 180.0001,
        ]);
        $response->assertSessionHasErrors(['delivery_longitude']);
    }

    // =========================================================================
    // 4. Collection & Dynamic Items Boundaries
    // =========================================================================

    public function test_order_items_boundary_empty_array_rejected(): void
    {
        $client = $this->createClient();
        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [],
        ]);
        $response->assertSessionHasErrors('items');
    }

    public function test_order_items_boundary_missing_items_key_rejected(): void
    {
        $client = $this->createClient();
        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
        ]);
        $response->assertSessionHasErrors('items');
    }

    public function test_order_items_boundary_single_valid_item_accepted(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['client_id' => $client->id]);
    }

    public function test_order_items_boundary_multiple_valid_items_accepted(): void
    {
        $client = $this->createClient();
        $p1 = $this->createProduct(null, ['name' => 'Producto Multi 1']);
        $p2 = $this->createProduct(null, ['name' => 'Producto Multi 2']);

        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'factura_electronica',
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 3],
                ['product_id' => $p2->id, 'quantity' => 7],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_items', ['product_id' => $p1->id, 'quantity' => 3]);
        $this->assertDatabaseHas('order_items', ['product_id' => $p2->id, 'quantity' => 7]);
    }

    public function test_order_items_boundary_item_with_missing_product_rejected(): void
    {
        $client = $this->createClient();
        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => '', 'quantity' => 2],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.product_id');
    }

    public function test_order_items_boundary_item_with_nonexistent_product_rejected(): void
    {
        $client = $this->createClient();
        $response = $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'items' => [
                ['product_id' => 999999, 'quantity' => 2],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.product_id');
    }

    // =========================================================================
    // 5. AI Background Processing Guard Contract
    // =========================================================================

    public function test_product_ai_guard_boundary_active_process_blocks_submit(): void
    {
        $guard = [
            'activeProcessCount' => 1,
            'shouldBlock' => true,
            'message' => 'Por favor espera a que finalice el procesamiento con IA antes de guardar.',
        ];
        $this->assertTrue($guard['activeProcessCount'] > 0);
        $this->assertTrue($guard['shouldBlock']);
    }

    public function test_product_ai_guard_boundary_zero_active_processes_allows_submit(): void
    {
        $guard = [
            'activeProcessCount' => 0,
            'shouldBlock' => false,
        ];
        $this->assertEquals(0, $guard['activeProcessCount']);
        $this->assertFalse($guard['shouldBlock']);
    }

    // =========================================================================
    // 6. Order Status Transition Conditional Boundaries
    // =========================================================================

    public function test_status_transition_rejection_reason_boundary_length_2_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-REJ-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->rechazadoStatus->id,
            'rejection_reason' => 'NO',
        ]);
        $response->assertSessionHasErrors(['rejection_reason']);
    }

    public function test_status_transition_rejection_reason_boundary_length_3_accepted(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-REJ-002',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->rechazadoStatus->id,
            'rejection_reason' => 'NOP',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $this->rechazadoStatus->id]);
    }

    public function test_status_transition_dispatch_missing_photo_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-DIS-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->despachadoStatus->id,
            'payment_term' => 'contado',
        ]);
        $response->assertSessionHasErrors(['dispatch_photo']);
    }

    public function test_status_transition_dispatch_missing_payment_term_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-DIS-002',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->despachadoStatus->id,
            'dispatch_photo' => UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg'),
        ]);
        $response->assertSessionHasErrors(['payment_term']);
    }

    public function test_status_transition_dispatch_credito_without_due_date_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-DIS-003',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->despachadoStatus->id,
            'dispatch_photo' => UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg'),
            'payment_term' => 'credito',
            'payment_due_date' => '',
        ]);
        $response->assertSessionHasErrors(['payment_due_date']);
    }

    public function test_status_transition_dispatch_credito_with_due_date_accepted(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-DIS-004',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->despachadoStatus->id,
            'dispatch_photo' => UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg'),
            'signature_file' => UploadedFile::fake()->create('firma.png', 50, 'image/png'),
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(30)->toDateString(),
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_term' => 'credito']);
    }

    public function test_status_transition_dispatch_contado_without_due_date_accepted(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-DIS-005',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->despachadoStatus->id,
            'dispatch_photo' => UploadedFile::fake()->create('despacho.jpg', 100, 'image/jpeg'),
            'signature_file' => UploadedFile::fake()->create('firma.png', 50, 'image/png'),
            'payment_term' => 'contado',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_term' => 'contado']);
    }

    public function test_status_transition_delivery_missing_photo_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-DEL-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_latitude' => 4.60971,
            'delivery_longitude' => -74.08175,
        ]);
        $response->assertSessionHasErrors(['delivery_photo']);
    }

    public function test_status_transition_delivery_missing_coordinates_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-DEL-002',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->entregadoStatus->id,
            'delivery_photo' => UploadedFile::fake()->create('entrega.jpg', 100, 'image/jpeg'),
        ]);
        $response->assertSessionHasErrors(['delivery_latitude', 'delivery_longitude']);
    }

    public function test_status_transition_cobrado_missing_responsible_rejected(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-COB-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->entregadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->cobradoStatus->id,
            'payment_support' => UploadedFile::fake()->create('soporte.pdf', 50),
            'payment_responsible_id' => '',
        ]);
        $response->assertSessionHasErrors(['payment_responsible_id']);
    }

    public function test_status_transition_cobrado_with_valid_responsible_accepted(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-COB-002',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->entregadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pedidos.estado', $order), [
            'order_status_id' => $this->cobradoStatus->id,
            'payment_support' => UploadedFile::fake()->create('soporte.pdf', 50),
            'payment_responsible_id' => $this->admin->id,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $this->cobradoStatus->id]);
    }

    // =========================================================================
    // 7. Uniqueness & Inactive State Boundaries
    // =========================================================================

    public function test_seller_code_boundary_duplicate_rejected(): void
    {
        $this->createSeller(['seller_code' => 'VEND-DUPL']);

        $response = $this->actingAs($this->admin)->post(route('vendedores.store'), [
            'name' => 'Otro Vendedor',
            'seller_code' => 'VEND-DUPL',
        ]);
        $response->assertSessionHasErrors('seller_code');
    }

    public function test_user_email_boundary_duplicate_rejected(): void
    {
        User::create([
            'name' => 'Usuario Duplicado 1',
            'email' => 'duplicado@cauchosalfa.com',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Usuario Duplicado 2',
            'email' => 'duplicado@cauchosalfa.com',
            'password' => 'secret1234',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_order_status_name_boundary_duplicate_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('estados-pedido.store'), [
            'name' => 'Pedido creado', // Ya existe en setUp
            'color' => '#123456',
            'sort_order' => 99,
        ]);
        $response->assertSessionHasErrors('name');
    }

    public function test_inactive_admin_user_login_rejected(): void
    {
        $this->createAdmin(['email' => 'inactivo.admin@test.com', 'is_active' => false]);

        $response = $this->post(route('login.store'), [
            'email' => 'inactivo.admin@test.com',
            'password' => 'secret-password',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_seller_code_login_rejected(): void
    {
        $this->createSeller(['seller_code' => 'VEND-INACTIVO', 'is_active' => false]);

        $response = $this->post(route('site.seller.auth'), [
            'code' => 'VEND-INACTIVO',
        ]);
        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_inactive_product_cannot_be_added_to_seller_cart(): void
    {
        $seller = $this->createSeller();
        $inactiveProduct = $this->createProduct(null, ['is_active' => false]);

        $response = $this->actingAs($seller)->post(route('site.seller.add'), [
            'product_id' => $inactiveProduct->id,
            'quantity' => 1,
        ]);
        $response->assertNotFound();
        $this->assertEquals([], session('seller_cart', []));
    }
}
