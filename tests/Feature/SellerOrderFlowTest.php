<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class SellerOrderFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    private User $seller;

    private \App\Models\Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = $this->createSeller();
        $this->product = $this->createProduct();
    }

    /** Caso negativo: requiere autenticación */
    public function test_agregar_al_pedido_requiere_autenticacion(): void
    {
        $this->post(route('site.seller.add'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])->assertRedirect(route('login'));

        $this->get(route('site.seller.order'))->assertRedirect(route('login'));
    }

    /** Caso negativo: validación de cantidad mínima */
    public function test_rechaza_cantidad_menor_a_uno(): void
    {
        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), [
                'product_id' => $this->product->id,
                'quantity' => 0,
            ])->assertSessionHasErrors('quantity');

        $this->assertSame([], session('seller_cart', []));
    }

    /** Caso negativo: producto inexistente es rechazado */
    public function test_rechaza_producto_inexistente(): void
    {
        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), [
                'product_id' => 99999,
                'quantity' => 1,
            ])->assertSessionHasErrors('product_id');
    }

    /** Caso negativo: producto inactivo no se puede agregar */
    public function test_rechaza_producto_inactivo(): void
    {
        $inactive = $this->createProduct(null, ['is_active' => false]);

        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), [
                'product_id' => $inactive->id,
                'quantity' => 1,
            ])->assertNotFound();

        $this->assertSame([], session('seller_cart', []));
    }

    /** Caso positivo: agregar producto y verlo en la vista del pedido */
    public function test_agregar_y_ver_el_pedido(): void
    {
        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame([$this->product->id => 2], session('seller_cart'));

        $response = $this->actingAs($this->seller)->get(route('site.seller.order'));
        $response->assertOk();
        $response->assertViewHas('cartProducts', fn($products) => $products->first()->cart_quantity === 2);
    }

    /** Caso límite: pedido vacío redirige al catálogo con advertencia */
    public function test_pedido_vacio_redirige_al_catalogo(): void
    {
        $this->actingAs($this->seller)
            ->get(route('site.seller.order'))
            ->assertRedirect(route('site.catalog'))
            ->assertSessionHas('warning');
    }

    /** Caso positivo: quitar un producto del pedido */
    public function test_quitar_producto_del_pedido(): void
    {
        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), ['product_id' => $this->product->id, 'quantity' => 1]);

        $this->actingAs($this->seller)
            ->post(route('site.seller.remove'), ['product_id' => $this->product->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame([], session('seller_cart'));
    }
}

