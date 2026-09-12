<?php

namespace Tests\Unit\Models;

use App\Models\Catalog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    public function test_pertenece_a_una_categoria(): void
    {
        $product = $this->createProduct();

        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertEquals($product->category_id, $product->category->id);
    }

    public function test_tiene_muchas_imagenes_ordenadas_por_sort_order(): void
    {
        $product = $this->createProduct();

        $product->images()->create(['path' => 'products/b.jpg', 'sort_order' => 2]);
        $product->images()->create(['path' => 'products/a.jpg', 'sort_order' => 1]);

        $paths = $product->images()->get()->map(fn(ProductImage $img) => $img->path)->all();

        $this->assertSame(['products/a.jpg', 'products/b.jpg'], $paths);
    }

    public function test_image_urls_combina_galeria_e_imagen_principal(): void
    {
        $product = $this->createProduct(null, ['image_path' => 'products/main.jpg']);
        $product->images()->create(['path' => 'products/gallery.jpg', 'sort_order' => 1]);

        $urls = $product->imageUrls();

        $this->assertCount(2, $urls);
        $this->assertStringContainsString('products/gallery.jpg', $urls[0]);
        $this->assertStringContainsString('products/main.jpg', $urls[1]);
    }

    public function test_image_urls_sin_imagenes_devuelve_arreglo_vacio(): void
    {
        $product = $this->createProduct(null, ['image_path' => null]);

        $this->assertSame([], $product->imageUrls());
    }

    public function test_image_urls_solo_con_imagen_principal_antigua(): void
    {
        $product = $this->createProduct(null, ['image_path' => 'products/legacy.jpg']);

        $urls = $product->imageUrls();

        $this->assertCount(1, $urls);
        $this->assertStringContainsString('products/legacy.jpg', $urls[0]);
    }

    public function test_el_cast_de_is_active_convierte_a_booleano(): void
    {
        $product = $this->createProduct(null, ['is_active' => 1]);

        $this->assertTrue($product->is_active);

        $product->update(['is_active' => false]);

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_puede_asociarse_a_catalogos_con_pivot(): void
    {
        $product = $this->createProduct();
        $catalog = Catalog::create(['name' => 'Catálogo 2026', 'is_active' => true]);

        $catalog->products()->attach($product->id, ['sort_order' => 3]);

        $this->assertTrue($catalog->products->contains($product->id));
        $this->assertEquals(3, $catalog->products->first()->pivot->sort_order);
    }

    public function test_puede_tener_muchos_items_de_pedido(): void
    {
        $product = $this->createProduct();

        $this->assertInstanceOf(OrderItem::class, $product->orderItems()->create([
            'order_id' => Order::create([
                'code' => 'PED-1',
                'client_id' => $this->createClient()->id,
                'created_by' => $this->createAdmin()->id,
                'order_status_id' => \App\Models\OrderStatus::create(['name' => 'Pendiente', 'slug' => 'pendiente', 'sort_order' => 1, 'is_active' => true])->id,
            ])->id,
            'quantity' => 2,
        ]));

        $this->assertCount(1, $product->orderItems);
    }
}
