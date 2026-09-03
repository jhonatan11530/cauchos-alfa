<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $roleName, string $roleSlug, string $email): void
    {
        $role = Role::create(['name' => $roleName, 'slug' => $roleSlug]);
        $user = User::create([
            'role_id' => $role->id,
            'name' => $roleName,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->actingAs($user);
    }

    private function actingAsAdmin(): void
    {
        $this->actingAsRole('Administrador', 'administrador', 'admin@test.com');
    }

    private function createProductWithImage(string $fileName): array
    {
        $product = Product::create(['name' => 'Llanta 185/65 R15', 'availability' => 'disponible', 'is_active' => true]);
        $image = $product->images()->create(['path' => 'products/' . $fileName, 'sort_order' => 1]);
        Storage::disk('public')->put('products/' . $fileName, 'contenido');

        return [$product, $image];
    }

    public function test_admin_can_delete_a_product_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        [$product, $image] = $this->createProductWithImage('test.jpg');

        $response = $this->delete(route('productos.images.destroy', [$product, $image]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('products/test.jpg');
    }

    public function test_non_admin_cannot_delete_a_product_image(): void
    {
        Storage::fake('public');
        $this->actingAsRole('Cliente', 'cliente', 'cliente@test.com');

        [$product, $image] = $this->createProductWithImage('test-forbidden.jpg');

        $response = $this->delete(route('productos.images.destroy', [$product, $image]));

        $response->assertForbidden();
        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertExists('products/test-forbidden.jpg');
    }

    public function test_image_of_another_product_returns_404(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        [$productA, $imageA] = $this->createProductWithImage('product-a.jpg');
        $productB = Product::create(['name' => 'Llanta 195/60 R15', 'availability' => 'disponible', 'is_active' => true]);

        $response = $this->delete(route('productos.images.destroy', [$productB, $imageA]));

        $response->assertNotFound();
        $this->assertDatabaseHas('product_images', ['id' => $imageA->id]);
        Storage::disk('public')->assertExists('products/product-a.jpg');
    }
}
