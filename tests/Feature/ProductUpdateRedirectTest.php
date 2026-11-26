<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class ProductUpdateRedirectTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    public function test_updating_product_redirects_back_to_edit_page(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory(['name' => 'Calapiés']);
        $product = $this->createProduct($category, [
            'name' => 'Calapié Original',
            'availability' => 'disponible',
        ]);

        $response = $this->actingAs($admin)->put(route('productos.update', $product), [
            'category_id' => $category->id,
            'name' => 'Calapié Actualizado',
            'reference' => $product->reference,
            'availability' => 'disponible',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('productos.edit', $product));
        $response->assertSessionHas('success', 'Producto actualizado correctamente.');
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Calapié Actualizado',
        ]);
    }
}
