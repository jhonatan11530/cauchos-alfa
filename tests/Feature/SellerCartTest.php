<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerCartTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $sellerRole = Role::create(['name' => 'Vendedor', 'slug' => 'vendedor']);

        $this->seller = User::create([
            'role_id' => $sellerRole->id,
            'name' => 'Vendedor',
            'email' => 'seller@test.com',
            'seller_code' => 'VEND001',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Llantas', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Llanta 185/65 R15',
            'is_active' => true,
        ]);
    }

    public function test_add_to_cart_merges_quantities_for_same_product(): void
    {
        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($this->seller)
            ->post(route('site.seller.add'), [
                'product_id' => $this->product->id,
                'quantity' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(5, session('seller_cart')[$this->product->id]);
    }

    public function test_add_to_cart_rejects_inactive_product(): void
    {
        $this->product->update(['is_active' => false]);

        $response = $this->actingAs($this->seller)
            ->post(route('site.seller.add'), [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $response->assertNotFound();
        $this->assertEmpty(session('seller_cart', []));
    }

    public function test_remove_cart_item_deletes_entry_from_session(): void
    {
        $this->actingAs($this->seller)
            ->withSession(['seller_cart' => [$this->product->id => 2]])
            ->post(route('site.seller.remove'), ['product_id' => $this->product->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertArrayNotHasKey($this->product->id, session('seller_cart', []));
    }

    public function test_show_seller_order_renders_cart_products_with_quantities(): void
    {
        $client = Client::create(['name' => 'Cliente Prueba', 'is_active' => true]);

        $response = $this->actingAs($this->seller)
            ->withSession(['seller_cart' => [$this->product->id => 4]])
            ->get(route('site.seller.order'));

        $response->assertOk()
            ->assertViewIs('site.seller-order')
            ->assertViewHas('cartProducts', function ($cartProducts) {
                return $cartProducts->count() === 1
                    && $cartProducts->first()->id === $this->product->id
                    && $cartProducts->first()->cart_quantity === 4;
            })
            ->assertViewHas('clients', fn($clients) => $clients->contains($client));
    }

    public function test_show_seller_order_redirects_when_cart_is_empty(): void
    {
        $this->actingAs($this->seller)
            ->get(route('site.seller.order'))
            ->assertRedirect(route('site.catalog'))
            ->assertSessionHas('warning');
    }

    public function test_show_seller_order_drops_products_no_longer_active(): void
    {
        $this->product->update(['is_active' => false]);

        $this->actingAs($this->seller)
            ->withSession(['seller_cart' => [$this->product->id => 2]])
            ->get(route('site.seller.order'))
            ->assertViewHas('cartProducts', fn($cartProducts) => $cartProducts->isEmpty());
    }

    public function test_guest_cannot_access_cart_routes(): void
    {
        $this->post(route('site.seller.add'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertRedirect(route('login'));

        $this->get(route('site.seller.order'))
            ->assertRedirect(route('login'));
    }

    public function test_seller_login_with_only_code(): void
    {
        $response = $this->post(route('site.seller.auth'), [
            'code' => 'VEND001',
        ]);

        $response->assertRedirect(route('site.catalog'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($this->seller);
    }

    public function test_seller_login_fails_with_invalid_code(): void
    {
        $response = $this->post(route('site.seller.auth'), [
            'code' => 'INEXISTENTE',
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertGuest();
    }

    public function test_seller_login_is_throttled_after_too_many_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('site.seller.auth'), ['code' => 'ERRADO']);
        }

        $response = $this->post(route('site.seller.auth'), ['code' => 'ERRADO']);
        $response->assertSessionHasErrors(['code']);
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('code'));
    }
}
