<?php

namespace Tests\Feature\Validation;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\User;
use App\Support\SellerCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

/**
 * Tier 1: Feature Coverage & Contract Validation for Seller Panel Forms.
 *
 * Evaluates:
 * - Form structure, presence of form IDs/classes, method spoofing, and CSRF protection in Seller panel
 * - Client-side validation attributes (required, minlength, digits, min, range)
 * - Required field rejection and session validation error contracts
 * - Seller Layout jQuery Validate inclusion and Spanish translation contracts
 */
class Tier1_SellerFormsValidationTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    private User $seller;

    private Product $product;

    private Client $client;

    private OrderStatus $creadoStatus;

    private OrderStatus $despachadoStatus;

    private OrderStatus $entregadoStatus;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->createRoles();
        $this->seller = $this->createSeller();
        $this->product = $this->createProduct();
        $this->client = $this->createClient();

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

    // =========================================================================
    // 1. Seller Login Form Tests
    // =========================================================================

    public function test_seller_login_view_renders_form_with_seller_login_form_id(): void
    {
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('id="seller-login-form"', false);
    }

    public function test_seller_login_form_action_is_seller_auth_with_post_method(): void
    {
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('action="'.route('site.seller.auth').'"', false);
        $response->assertSee('method="POST"', false);
    }

    public function test_seller_login_form_includes_csrf_token(): void
    {
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('name="_token"', false);
    }

    public function test_seller_login_code_field_is_required(): void
    {
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('name="code"', false);
        $response->assertSee('required', false);
    }

    public function test_seller_login_code_field_has_minlength_2_constraint(): void
    {
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('minlength: 2', false);
    }

    public function test_seller_login_rejects_empty_code_with_session_error(): void
    {
        $response = $this->from(route('site.seller.login'))
            ->post(route('site.seller.auth'), ['code' => '']);
        $response->assertRedirect(route('site.seller.login'));
        $response->assertSessionHasErrors(['code']);
    }

    public function test_seller_login_spanish_messages_contract(): void
    {
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('Por favor ingresa tu código de vendedor.', false);
        $response->assertSee('El código debe tener al menos {0} caracteres.', false);
    }

    // =========================================================================
    // 2. Seller Catalog Quick Add-to-Cart Form Tests
    // =========================================================================

    public function test_seller_catalog_quick_add_form_rendered_for_each_product(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.catalog'));
        $response->assertOk();
        $response->assertSee('action="'.route('site.seller.add').'"', false);
    }

    public function test_seller_catalog_quick_add_form_action_is_seller_add_post(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.catalog'));
        $response->assertOk();
        $response->assertSee('action="'.route('site.seller.add').'"', false);
        $response->assertSee('method="POST"', false);
    }

    public function test_seller_catalog_quick_add_product_id_is_hidden_and_required(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.catalog'));
        $response->assertOk();
        $response->assertSee('name="product_id"', false);
        $response->assertSee('type="hidden"', false);
    }

    public function test_seller_catalog_quick_add_quantity_is_required_and_digits(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.catalog'));
        $response->assertOk();
        $response->assertSee('name="quantity"', false);
    }

    public function test_seller_catalog_quick_add_quantity_minimum_is_one(): void
    {
        $response = $this->actingAs($this->seller)
            ->from(route('site.catalog'))
            ->post(route('site.seller.add'), [
                'product_id' => $this->product->id,
                'quantity' => 0,
            ]);
        $response->assertRedirect(route('site.catalog'));
        $response->assertSessionHasErrors(['quantity']);
    }

    public function test_seller_catalog_quick_add_input_group_error_placement_contract(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.catalog'));
        $response->assertOk();
        $response->assertSee('closest(\'.input-group\')', false);
    }

    public function test_seller_catalog_quick_add_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.catalog'));
        $response->assertOk();
        $response->assertSee('Ingresa la cantidad.', false);
        $response->assertSee('Solo se permiten números enteros.', false);
        $response->assertSee('La cantidad mínima debe ser al menos {0}.', false);
    }

    // =========================================================================
    // 3. Seller Product Detail Add-to-Cart Form Tests
    // =========================================================================

    public function test_seller_product_detail_add_form_renders_action_and_csrf(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.product', ['id' => $this->product->id, 'slug' => 'llanta-test']));
        $response->assertOk();
        $response->assertSee('action="'.route('site.seller.add').'"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_seller_product_detail_product_id_is_hidden_and_required(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.product', ['id' => $this->product->id, 'slug' => 'llanta-test']));
        $response->assertOk();
        $response->assertSee('name="product_id"', false);
        $response->assertSee('type="hidden"', false);
        $response->assertSee('value="'.$this->product->id.'"', false);
    }

    public function test_seller_product_detail_quantity_is_required_and_digits(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.product', ['id' => $this->product->id, 'slug' => 'llanta-test']));
        $response->assertOk();
        $response->assertSee('name="quantity"', false);
    }

    public function test_seller_product_detail_quantity_minimum_is_one(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.product', ['id' => $this->product->id, 'slug' => 'llanta-test']));
        $response->assertOk();
        $response->assertSee('min="1"', false);
        $response->assertSee('min: 1', false);
    }

    public function test_seller_product_detail_rejects_missing_or_zero_quantity(): void
    {
        $response = $this->actingAs($this->seller)
            ->from(route('site.product', ['id' => $this->product->id, 'slug' => 'llanta-test']))
            ->post(route('site.seller.add'), [
                'product_id' => $this->product->id,
                'quantity' => -1,
            ]);
        $response->assertRedirect(route('site.product', ['id' => $this->product->id, 'slug' => 'llanta-test']));
        $response->assertSessionHasErrors(['quantity']);
    }

    public function test_seller_product_detail_spanish_messages_contract(): void
    {
        $response = $this->actingAs($this->seller)->get(route('site.product', ['id' => $this->product->id, 'slug' => 'llanta-test']));
        $response->assertOk();
        $response->assertSee('Ingresa la cantidad.', false);
        $response->assertSee('Solo se permiten números enteros.', false);
        $response->assertSee('La cantidad mínima debe ser al menos {0}.', false);
    }

    // =========================================================================
    // 4. Seller Checkout Order Form Tests
    // =========================================================================

    public function test_seller_checkout_form_renders_action_to_pedidos_store(): void
    {
        // Populate cart first so checkout view does not redirect
        app(SellerCart::class)->add($this->product->id, 2);

        $response = $this->actingAs($this->seller)->get(route('site.seller.order'));
        $response->assertOk();
        $response->assertSee('action="'.route('pedidos.store').'"', false);
        $response->assertSee('method="POST"', false);
    }

    public function test_seller_checkout_form_includes_csrf_token(): void
    {
        app(SellerCart::class)->add($this->product->id, 2);

        $response = $this->actingAs($this->seller)->get(route('site.seller.order'));
        $response->assertOk();
        $response->assertSee('name="_token"', false);
    }

    public function test_seller_checkout_client_select_is_required(): void
    {
        app(SellerCart::class)->add($this->product->id, 2);

        $response = $this->actingAs($this->seller)->get(route('site.seller.order'));
        $response->assertOk();
        $response->assertSee('name="client_id"', false);
    }

    public function test_seller_checkout_billing_type_is_required_with_expected_options(): void
    {
        app(SellerCart::class)->add($this->product->id, 2);

        $response = $this->actingAs($this->seller)->get(route('site.seller.order'));
        $response->assertOk();
        $response->assertSee('name="billing_type"', false);
        $response->assertSee('value="remision"', false);
        $response->assertSee('value="factura_electronica"', false);
    }

    public function test_seller_checkout_submits_cart_items_inputs(): void
    {
        app(SellerCart::class)->add($this->product->id, 2);

        $response = $this->actingAs($this->seller)->get(route('site.seller.order'));
        $response->assertOk();
        $response->assertSee('name="items[0][product_id]"', false);
        $response->assertSee('name="items[0][quantity]"', false);
    }

    public function test_seller_checkout_spanish_messages_contract(): void
    {
        app(SellerCart::class)->add($this->product->id, 2);
        $response = $this->actingAs($this->seller)->get(route('site.seller.order'));
        $response->assertOk();
        $response->assertSee('Por favor selecciona un cliente para el pedido.', false);
        $response->assertSee('Por favor selecciona el tipo de facturación.', false);
        $response->assertSee('Las observaciones no pueden superar los 1000 caracteres.', false);
    }

    // =========================================================================
    // 5. Seller Delivery Modal Form Tests
    // =========================================================================

    public function test_seller_delivery_modal_form_renders_multipart_action_for_dispatched_order(): void
    {
        $order = Order::create([
            'code' => 'PED-DELIV-001',
            'client_id' => $this->client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->seller)->get(route('site.seller.orders'));
        $response->assertOk();
        $response->assertSee(route('site.seller.orders.deliver', $order), false);
        $response->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_seller_delivery_photo_is_required_file(): void
    {
        $order = Order::create([
            'code' => 'PED-DELIV-002',
            'client_id' => $this->client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->seller)->get(route('site.seller.orders'));
        $response->assertOk();
        $response->assertSee('name="delivery_photo"', false);
        $response->assertSee('type="file"', false);
    }

    public function test_seller_delivery_latitude_is_required_and_bounded_minus_90_to_90(): void
    {
        $order = Order::create([
            'code' => 'PED-DELIV-003',
            'client_id' => $this->client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->seller)->get(route('site.seller.orders'));
        $response->assertOk();
        $response->assertSee('name="delivery_latitude"', false);
    }

    public function test_seller_delivery_longitude_is_required_and_bounded_minus_180_to_180(): void
    {
        $order = Order::create([
            'code' => 'PED-DELIV-004',
            'client_id' => $this->client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->seller)->get(route('site.seller.orders'));
        $response->assertOk();
        $response->assertSee('name="delivery_longitude"', false);
    }

    public function test_seller_delivery_observation_and_client_location_fields_present(): void
    {
        $order = Order::create([
            'code' => 'PED-DELIV-005',
            'client_id' => $this->client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->seller)->get(route('site.seller.orders'));
        $response->assertOk();
        $response->assertSee('name="observation"', false);
        $response->assertSee('name="client_location"', false);
    }

    public function test_seller_delivery_custom_coord_methods_contract(): void
    {
        $engine = file_get_contents(public_path('js/form-validation.js'));
        $this->assertNotFalse($engine);
        $this->assertStringContainsString('coordLatitude', $engine);
        $this->assertStringContainsString('coordLongitude', $engine);
        $this->assertStringContainsString('Por favor, ingrese una latitud válida (-90 a 90).', $engine);
        $this->assertStringContainsString('Por favor, ingrese una longitud válida (-180 a 180).', $engine);
    }

    public function test_seller_delivery_spanish_messages_contract(): void
    {
        $order = Order::create([
            'code' => 'PED-DELIV-006',
            'client_id' => $this->client->id,
            'billing_type' => 'remision',
            'order_status_id' => $this->despachadoStatus->id,
            'created_by' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->seller)->get(route('site.seller.orders'));
        $response->assertOk();
        $response->assertSee('La fotografía de entrega es obligatoria.', false);
        $response->assertSee('Las coordenadas de latitud son obligatorias.', false);
        $response->assertSee('La latitud debe estar comprendida entre -90 y 90 grados.', false);
        $response->assertSee('Las coordenadas de longitud son obligatorias.', false);
        $response->assertSee('La longitud debe estar comprendida entre -180 y 180 grados.', false);
    }

    // =========================================================================
    // 6. Seller Layout Asset Integration Contract Tests
    // =========================================================================

    public function test_seller_layout_asset_contract_for_jquery_validate(): void
    {
        $this->assertFileExists(public_path('js/jquery.validate.min.js'));
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('/js/jquery.validate.min.js', false);
    }

    public function test_seller_layout_asset_contract_for_messages_es(): void
    {
        $this->assertFileExists(public_path('js/messages_es.min.js'));
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('/js/messages_es.min.js', false);
    }

    public function test_seller_layout_asset_contract_for_form_validation_js(): void
    {
        $this->assertFileExists(public_path('js/form-validation.js'));
        $response = $this->get(route('site.seller.login'));
        $response->assertOk();
        $response->assertSee('/js/form-validation.js', false);
    }

    public function test_seller_layout_configures_error_placement_after_input_group(): void
    {
        $engine = file_get_contents(public_path('js/form-validation.js'));
        $this->assertNotFalse($engine);
        $this->assertStringContainsString("element.closest('.input-group')", $engine);
    }

    public function test_seller_layout_configures_select2_highlighting(): void
    {
        $engine = file_get_contents(public_path('js/form-validation.js'));
        $this->assertNotFalse($engine);
        $this->assertStringContainsString('select2-hidden-accessible', $engine);
        $this->assertStringContainsString('border-danger', $engine);
    }

    public function test_seller_layout_spanish_dictionary_contract(): void
    {
        $dictionary = file_get_contents(public_path('js/messages_es.min.js'));
        $this->assertNotFalse($dictionary);
        $this->assertStringContainsString('Este campo es obligatorio.', $dictionary);
        $this->assertStringContainsString('Por favor, escribe sólo dígitos.', $dictionary);
        $this->assertStringContainsString('Por favor, escribe un valor mayor o igual a {0}.', $dictionary);
        $this->assertStringContainsString('Por favor, escribe un valor entre {0} y {1}.', $dictionary);
    }
}
