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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

/**
 * Tier 1: Feature Coverage & Contract Validation for Administrator and Auth Forms.
 *
 * Evaluates:
 * - Form structure, presence of form IDs/classes, method spoofing, and CSRF protection
 * - Client-side validation attributes (required, type="email", minlength, maxlength, min, range)
 * - Required field rejection and session validation error contracts
 * - Global jQuery Validate configuration and Spanish translation contracts
 */
class Tier1_AdminFormsValidationTest extends TestCase
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
    // 1. Admin Login Form Tests
    // =========================================================================

    public function test_admin_login_view_renders_form_with_admin_login_form_id(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('id="admin-login-form"', false);
    }

    public function test_admin_login_form_action_is_login_store_with_post_method(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('action="' . route('login.store') . '"', false);
        $response->assertSee('method="POST"', false);
    }

    public function test_admin_login_form_includes_csrf_token_field(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_login_form_email_has_required_attribute_and_email_type(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('name="email"', false);
        $response->assertSee('type="email"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_login_form_password_has_required_attribute_and_password_type(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('name="password"', false);
        $response->assertSee('type="password"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_login_rejects_missing_credentials_with_session_errors(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), []);
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_admin_login_spanish_error_contract(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);

        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, escribe una dirección de correo válida.', $dictionary);
    }

    // =========================================================================
    // 2. Admin Category Form Tests
    // =========================================================================

    public function test_admin_category_create_form_renders_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('categoria.create'));
        $response->assertOk();
        $response->assertSee('action="' . route('categoria.store') . '"', false);
        $response->assertSee('method="POST"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_category_edit_form_renders_put_method_spoofing(): void
    {
        $category = $this->createCategory(['name' => 'Neumáticos Agrícolas']);
        $response = $this->actingAs($this->admin)->get(route('categoria.edit', $category));
        $response->assertOk();
        $response->assertSee('action="' . route('categoria.update', $category) . '"', false);
        $response->assertSee('name="_method"', false);
        $response->assertSee('value="PUT"', false);
    }

    public function test_admin_category_form_name_field_has_required_attribute(): void
    {
        $response = $this->actingAs($this->admin)->get(route('categoria.create'));
        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_category_form_description_and_is_active_fields_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('categoria.create'));
        $response->assertOk();
        $response->assertSee('name="description"', false);
        $response->assertSee('name="is_active"', false);
    }

    public function test_admin_category_rejects_empty_name_with_session_error(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('categoria.create'))
            ->post(route('categoria.store'), ['name' => '']);
        $response->assertRedirect(route('categoria.create'));
        $response->assertSessionHasErrors(['name']);
    }

    public function test_admin_category_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('categoria.create'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);

        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, no escribas más de {0} caracteres.', $dictionary);
    }

    // =========================================================================
    // 3. Admin Client Form Tests
    // =========================================================================

    public function test_admin_client_create_form_renders_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('clientes.create'));
        $response->assertOk();
        $response->assertSee('action="' . route('clientes.store') . '"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_client_edit_form_renders_put_method_spoofing(): void
    {
        $client = $this->createClient(['name' => 'Transportes Unidos S.A.']);
        $response = $this->actingAs($this->admin)->get(route('clientes.edit', $client));
        $response->assertOk();
        $response->assertSee('action="' . route('clientes.update', $client) . '"', false);
        $response->assertSee('value="PUT"', false);
    }

    public function test_admin_client_form_name_is_required(): void
    {
        $response = $this->actingAs($this->admin)->get(route('clientes.create'));
        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_client_form_email_has_email_type(): void
    {
        $response = $this->actingAs($this->admin)->get(route('clientes.create'));
        $response->assertOk();
        $response->assertSee('name="email"', false);
        $response->assertSee('type="email"', false);
    }

    public function test_admin_client_form_document_phone_address_fields_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('clientes.create'));
        $response->assertOk();
        $response->assertSee('name="document"', false);
        $response->assertSee('name="phone"', false);
        $response->assertSee('name="address"', false);
        $response->assertSee('name="city"', false);
    }

    public function test_admin_client_rejects_empty_name_with_session_error(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('clientes.create'))
            ->post(route('clientes.store'), ['name' => '']);
        $response->assertRedirect(route('clientes.create'));
        $response->assertSessionHasErrors(['name']);
    }

    public function test_admin_client_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('clientes.create'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);

        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, escribe una dirección de correo válida.', $dictionary);
        $this->assertStringContainsString('Por favor, no escribas más de {0} caracteres.', $dictionary);
    }

    // =========================================================================
    // 4. Admin Product Form Tests
    // =========================================================================

    public function test_admin_product_create_form_renders_multipart_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('productos.create'));
        $response->assertOk();
        $response->assertSee('action="' . route('productos.store') . '"', false);
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_product_edit_form_renders_put_method_and_current_data(): void
    {
        $product = $this->createProduct(null, ['name' => 'Llanta Radial 295/80 R22.5']);
        $response = $this->actingAs($this->admin)->get(route('productos.edit', $product));
        $response->assertOk();
        $response->assertSee('action="' . route('productos.update', $product) . '"', false);
        $response->assertSee('value="PUT"', false);
        $response->assertSee('Llanta Radial 295/80 R22.5', false);
    }

    public function test_admin_product_form_name_field_is_required(): void
    {
        $response = $this->actingAs($this->admin)->get(route('productos.create'));
        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_product_form_availability_options_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('productos.create'));
        $response->assertOk();
        $response->assertSee('name="availability"', false);
        $response->assertSee('value="disponible"', false);
        $response->assertSee('value="agotado"', false);
        $response->assertSee('value="bajo pedido"', false);
    }

    public function test_admin_product_form_file_inputs_accept_images(): void
    {
        $response = $this->actingAs($this->admin)->get(route('productos.create'));
        $response->assertOk();
        $response->assertSee('name="image"', false);
        $response->assertSee('name="images[]"', false);
        $response->assertSee('accept="image/*"', false);
    }

    public function test_admin_product_rejects_empty_name_and_availability(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('productos.create'))
            ->post(route('productos.store'), [
                'name' => '',
                'availability' => '',
            ]);
        $response->assertRedirect(route('productos.create'));
        $response->assertSessionHasErrors(['name', 'availability']);
    }

    public function test_admin_product_ai_background_processing_guard_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('productos.create'));
        $response->assertOk();
        $response->assertSee('activeProcessCount', false);
        $response->assertSee('Por favor espera a que finalice el procesamiento con IA antes de guardar.', false);
        $response->assertSee('e.preventDefault()', false);
    }

    public function test_admin_product_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('productos.create'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);

        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, no escribas más de {0} caracteres.', $dictionary);
    }

    // =========================================================================
    // 5. Admin Catalog Form Tests
    // =========================================================================

    public function test_admin_catalog_create_form_renders_multipart_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('catalogos.create'));
        $response->assertOk();
        $response->assertSee('action="' . route('catalogos.store') . '"', false);
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_catalog_edit_form_renders_put_method(): void
    {
        $catalog = Catalog::create([
            'name' => 'Catálogo General 2026',
            'template' => 'default',
            'is_active' => true,
        ]);
        $response = $this->actingAs($this->admin)->get(route('catalogos.edit', $catalog));
        $response->assertOk();
        $response->assertSee('action="' . route('catalogos.update', $catalog) . '"', false);
        $response->assertSee('value="PUT"', false);
    }

    public function test_admin_catalog_form_name_is_required(): void
    {
        $response = $this->actingAs($this->admin)->get(route('catalogos.create'));
        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_catalog_form_template_options_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('catalogos.create'));
        $response->assertOk();
        $response->assertSee('name="template"', false);
        $response->assertSee('value="default"', false);
        $response->assertSee('value="modern"', false);
        $response->assertSee('value="classic"', false);
        $response->assertSee('value="catalogo-web"', false);
    }

    public function test_admin_catalog_form_contact_email_has_email_type(): void
    {
        $response = $this->actingAs($this->admin)->get(route('catalogos.create'));
        $response->assertOk();
        $response->assertSee('name="contact_email"', false);
        $response->assertSee('type="email"', false);
    }

    public function test_admin_catalog_rejects_missing_name_and_template(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('catalogos.create'))
            ->post(route('catalogos.store'), [
                'name' => '',
                'template' => '',
            ]);
        $response->assertRedirect(route('catalogos.create'));
        $response->assertSessionHasErrors(['name', 'template']);
    }

    public function test_admin_catalog_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('catalogos.create'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);

        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, escribe una dirección de correo válida.', $dictionary);
    }

    // =========================================================================
    // 6. Admin Order Form Tests
    // =========================================================================

    public function test_admin_order_create_form_renders_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pedidos.create'));
        $response->assertOk();
        $response->assertSee('action="' . route('pedidos.store') . '"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_order_edit_form_renders_put_method(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TEST-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('pedidos.edit', $order));
        $response->assertOk();
        $response->assertSee('action="' . route('pedidos.update', $order) . '"', false);
        $response->assertSee('value="PUT"', false);
    }

    public function test_admin_order_form_client_select2_field_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pedidos.create'));
        $response->assertOk();
        $response->assertSee('name="client_id"', false);
        $response->assertSee('id="client-select"', false);
    }

    public function test_admin_order_form_billing_type_options_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pedidos.create'));
        $response->assertOk();
        $response->assertSee('name="billing_type"', false);
        $response->assertSee('value="remision"', false);
        $response->assertSee('value="factura_electronica"', false);
    }

    public function test_admin_order_form_items_table_structure_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pedidos.create'));
        $response->assertOk();
        $response->assertSee('id="items-table"', false);
    }

    public function test_admin_order_rejects_submission_without_client_or_items(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('pedidos.create'))
            ->post(route('pedidos.store'), [
                'client_id' => '',
                'billing_type' => '',
                'items' => [],
            ]);
        $response->assertRedirect(route('pedidos.create'));
        $response->assertSessionHasErrors(['client_id', 'billing_type', 'items']);
    }

    public function test_admin_order_empty_items_guard_contract(): void
    {
        $client = $this->createClient();
        $response = $this->actingAs($this->admin)->get(route('pedidos.create'));
        $response->assertOk();
        $response->assertSee('id="items-table"', false);
        $response->assertSee('item-row', false);

        $postResponse = $this->actingAs($this->admin)
            ->from(route('pedidos.create'))
            ->post(route('pedidos.store'), [
                'client_id' => $client->id,
                'billing_type' => 'remision',
                'items' => [],
            ]);
        $postResponse->assertSessionHasErrors('items');
    }

    public function test_admin_order_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pedidos.create'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);

        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, escribe un valor mayor o igual a {0}.', $dictionary);
    }

    // =========================================================================
    // 7. Admin Order Status Transition Form Tests
    // =========================================================================

    public function test_admin_order_status_transition_form_renders_patch_action_and_csrf(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TRANS-001',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('pedidos.show', $order));
        $response->assertOk();
        $response->assertSee('action="' . route('pedidos.estado', $order) . '"', false);
        $response->assertSee('value="PATCH"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_order_status_transition_form_status_select_present(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TRANS-002',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('pedidos.show', $order));
        $response->assertOk();
        $response->assertSee('name="order_status_id"', false);
    }

    public function test_admin_order_status_dispatch_section_requires_photo_and_payment_term(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TRANS-003',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->despachadoStatus->id,
            ]);
        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHasErrors(['dispatch_photo', 'payment_term']);
    }

    public function test_admin_order_status_delivery_section_requires_photo_and_coordinates(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TRANS-004',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->entregadoStatus->id,
            ]);
        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHasErrors(['delivery_photo', 'delivery_latitude', 'delivery_longitude']);
    }

    public function test_admin_order_status_cobro_section_requires_responsible(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TRANS-005',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->entregadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->cobradoStatus->id,
                'payment_support' => UploadedFile::fake()->create('recibo.pdf', 100),
            ]);
        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHasErrors(['payment_responsible_id']);
    }

    public function test_admin_order_status_rejection_section_requires_reason(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-TRANS-006',
            'client_id' => $client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->creadoStatus->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->from(route('pedidos.show', $order))
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $this->rechazadoStatus->id,
                'rejection_reason' => '',
            ]);
        $response->assertRedirect(route('pedidos.show', $order));
        $response->assertSessionHasErrors(['rejection_reason']);
    }

    public function test_admin_order_status_spanish_messages_contract(): void
    {
        $client = $this->createClient();
        $order = Order::create([
            'code' => 'PED-SPANISH-001',
            'client_id' => $client->id,
            'order_status_id' => $this->creadoStatus->id,
            'billing_type' => 'remision',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('pedidos.show', $order));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);
        $response->assertSee('/js/form-validation.js', false);

        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, no escribas menos de {0} caracteres.', $dictionary);

        $validationEngine = file_get_contents(public_path('js/form-validation.js'));
        $this->assertNotFalse($validationEngine);
        $this->assertStringContainsString('Por favor, ingrese una latitud válida (-90 a 90).', $validationEngine);
        $this->assertStringContainsString('Por favor, ingrese una longitud válida (-180 a 180).', $validationEngine);
    }

    // =========================================================================
    // 8. Admin Seller Form Tests
    // =========================================================================

    public function test_admin_seller_create_form_renders_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('vendedores.create'));
        $response->assertOk();
        $response->assertSee('action="' . route('vendedores.store') . '"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_seller_edit_form_renders_put_method(): void
    {
        $seller = $this->createSeller(['name' => 'Vendedor Regional', 'seller_code' => 'V-REG-01']);
        $response = $this->actingAs($this->admin)->get(route('vendedores.edit', $seller));
        $response->assertOk();
        $response->assertSee('action="' . route('vendedores.update', $seller) . '"', false);
        $response->assertSee('value="PUT"', false);
    }

    public function test_admin_seller_form_name_is_required(): void
    {
        $response = $this->actingAs($this->admin)->get(route('vendedores.create'));
        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_seller_form_seller_code_is_required_with_minlength(): void
    {
        $response = $this->actingAs($this->admin)->get(route('vendedores.create'));
        $response->assertOk();
        $response->assertSee('name="seller_code"', false);
    }

    public function test_admin_seller_rejects_missing_name_or_code(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('vendedores.create'))
            ->post(route('vendedores.store'), [
                'name' => '',
                'seller_code' => '',
            ]);
        $response->assertRedirect(route('vendedores.create'));
        $response->assertSessionHasErrors(['name', 'seller_code']);
    }

    public function test_admin_seller_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('vendedores.create'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);

        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, no escribas menos de {0} caracteres.', $dictionary);
    }

    // =========================================================================
    // 9. Admin User Form Tests
    // =========================================================================

    public function test_admin_user_create_form_renders_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('usuarios.create'));
        $response->assertOk();
        $response->assertSee('action="' . route('usuarios.store') . '"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_user_edit_form_allows_optional_password(): void
    {
        $user = User::create([
            'name' => 'Usuario Edicion',
            'email' => 'edicion@cauchosalfa.com',
            'password' => 'secret1234',
            'is_active' => true,
        ]);
        $response = $this->actingAs($this->admin)->get(route('usuarios.edit', $user));
        $response->assertOk();
        $response->assertSee('action="' . route('usuarios.update', $user) . '"', false);
        $response->assertSee('value="PUT"', false);
    }

    public function test_admin_user_form_name_and_email_are_required(): void
    {
        $response = $this->actingAs($this->admin)->get(route('usuarios.create'));
        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('name="email"', false);
    }

    public function test_admin_user_form_email_has_email_type(): void
    {
        $response = $this->actingAs($this->admin)->get(route('usuarios.create'));
        $response->assertOk();
        $response->assertSee('type="email"', false);
    }

    public function test_admin_user_create_requires_password_minlength_8(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('usuarios.create'))
            ->post(route('usuarios.store'), [
                'name' => 'Carlos Usuario',
                'email' => 'carlos@cauchosalfa.com',
                'password' => 'short',
            ]);
        $response->assertRedirect(route('usuarios.create'));
        $response->assertSessionHasErrors(['password']);
    }

    public function test_admin_user_rejects_missing_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('usuarios.create'))
            ->post(route('usuarios.store'), [
                'name' => '',
                'email' => '',
                'password' => '',
            ]);
        $response->assertRedirect(route('usuarios.create'));
        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_admin_user_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('usuarios.create'));
        $response->assertOk();
        $response->assertSee('id="user-form"', false);
        $response->assertSee('Este campo es obligatorio.', false);
        $response->assertSee('Por favor, escribe una dirección de correo válida.', false);
        $response->assertSee('Por favor, no escribas menos de 8 caracteres.', false);
    }

    // =========================================================================
    // 10. Admin Order Statuses CRUD Form Tests
    // =========================================================================

    public function test_admin_order_status_crud_create_form_renders_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('estados-pedido.create'));
        $response->assertOk();
        $response->assertSee('action="' . route('estados-pedido.store') . '"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_order_status_crud_edit_form_renders_put_method(): void
    {
        $status = OrderStatus::create([
            'name' => 'En Preparación',
            'slug' => 'en-preparacion',
            'color' => '#f39c12',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $response = $this->actingAs($this->admin)->get(route('estados-pedido.edit', $status));
        $response->assertOk();
        $response->assertSee('action="' . route('estados-pedido.update', $status) . '"', false);
        $response->assertSee('value="PUT"', false);
    }

    public function test_admin_order_status_crud_form_name_is_required(): void
    {
        $response = $this->actingAs($this->admin)->get(route('estados-pedido.create'));
        $response->assertOk();
        $response->assertSee('name="name"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_order_status_crud_form_color_is_required(): void
    {
        $response = $this->actingAs($this->admin)->get(route('estados-pedido.create'));
        $response->assertOk();
        $response->assertSee('name="color"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_order_status_crud_form_sort_order_requires_non_negative_integer(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('estados-pedido.create'))
            ->post(route('estados-pedido.store'), [
                'name' => 'Estado Negativo',
                'color' => '#000000',
                'sort_order' => -5,
            ]);
        $response->assertRedirect(route('estados-pedido.create'));
        $response->assertSessionHasErrors(['sort_order']);
    }

    public function test_admin_order_status_crud_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('estados-pedido.create'));
        $response->assertOk();
        $response->assertSee('id="order-status-crud-form"', false);
        $response->assertSee('Este campo es obligatorio.', false);
        $response->assertSee('Por favor, escribe un valor mayor o igual a 0.', false);
    }

    // =========================================================================
    // 11. Admin Settings Prompt Form Tests
    // =========================================================================

    public function test_admin_settings_prompt_form_renders_put_action_and_csrf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('action="' . route('settings.update') . '"', false);
        $response->assertSee('value="PUT"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_settings_prompt_form_has_promptform_identifier(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('id="promptForm"', false);
    }

    public function test_admin_settings_prompt_field_is_required(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('name="prompt_gemini"', false);
        $response->assertSee('required', false);
    }

    public function test_admin_settings_prompt_minlength_is_10(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('settings.index'))
            ->put(route('settings.update'), [
                'prompt_gemini' => '123456789',
            ]);
        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHasErrors(['prompt_gemini']);
    }

    public function test_admin_settings_prompt_rejects_empty_or_short_prompt(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('settings.index'))
            ->put(route('settings.update'), [
                'prompt_gemini' => '',
            ]);
        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHasErrors(['prompt_gemini']);
    }

    public function test_admin_settings_prompt_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('El prompt de Gemini no puede estar vacío.', false);
        $response->assertSee('El prompt debe tener al menos 10 caracteres.', false);
    }

    // =========================================================================
    // 12. Asset & Global Validation Engine Contract Tests
    // =========================================================================

    public function test_admin_layout_asset_contract_for_jquery_validate(): void
    {
        $this->assertFileExists(public_path('js/jquery.validate.min.js'));
        $response = $this->actingAs($this->admin)->get(route('categoria.create'));
        $response->assertOk();
        $response->assertSee('/js/jquery.validate.min.js', false);
    }

    public function test_admin_layout_asset_contract_for_messages_es(): void
    {
        $this->assertFileExists(public_path('js/messages_es.min.js'));
        $response = $this->actingAs($this->admin)->get(route('categoria.create'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);
    }

    public function test_admin_layout_asset_contract_for_form_validation_js(): void
    {
        $this->assertFileExists(public_path('js/form-validation.js'));
        $response = $this->actingAs($this->admin)->get(route('categoria.create'));
        $response->assertOk();
        $response->assertSee('/js/form-validation.js', false);
    }

    public function test_validation_engine_configures_error_element_as_span(): void
    {
        $engine = file_get_contents(public_path('js/form-validation.js'));
        $this->assertNotFalse($engine);
        $this->assertStringContainsString("errorElement: 'span'", $engine);
    }

    public function test_validation_engine_configures_invalid_feedback_dblock_class(): void
    {
        $engine = file_get_contents(public_path('js/form-validation.js'));
        $this->assertNotFalse($engine);
        $this->assertStringContainsString("errorClass: 'invalid-feedback d-block'", $engine);
    }

    public function test_validation_engine_configures_is_invalid_highlight_behavior(): void
    {
        $engine = file_get_contents(public_path('js/form-validation.js'));
        $this->assertNotFalse($engine);
        $this->assertStringContainsString("addClass('is-invalid')", $engine);
        $this->assertStringContainsString("removeClass('is-invalid')", $engine);
    }

    public function test_validation_engine_configures_select2_border_danger_behavior(): void
    {
        $engine = file_get_contents(public_path('js/form-validation.js'));
        $this->assertNotFalse($engine);
        $this->assertStringContainsString("find('.select2-selection').addClass('border-danger')", $engine);
        $this->assertStringContainsString("find('.select2-selection').removeClass('border-danger')", $engine);
    }

    public function test_validation_engine_spanish_dictionary_contract_completeness(): void
    {
        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);

        $expectedPhrases = [
            'Este campo es obligatorio.',
            'Por favor, rellena este campo.',
            'Por favor, escribe una dirección de correo válida.',
            'Por favor, escribe una URL válida.',
            'Por favor, escribe una fecha válida.',
            'Por favor, escribe un número válido.',
            'Por favor, escribe sólo dígitos.',
            'Por favor, escribe un número de tarjeta válido.',
            'Por favor, escribe el mismo valor de nuevo.',
            'Por favor, no escribas más de {0} caracteres.',
            'Por favor, no escribas menos de {0} caracteres.',
            'Por favor, escribe un valor entre {0} y {1}.',
            'Por favor, escribe un valor menor o igual a {0}.',
            'Por favor, escribe un valor mayor o igual a {0}.',
        ];

        foreach ($expectedPhrases as $phrase) {
            $this->assertStringContainsString($phrase, $dictionary);
        }
    }
}
