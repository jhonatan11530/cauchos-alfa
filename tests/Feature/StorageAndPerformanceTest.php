<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class StorageAndPerformanceTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    public function test_product_get_storage_url_resolution(): void
    {
        // 1. Normal relative path
        $url = Product::getStorageUrl('products/test.jpg');
        $this->assertStringContainsString('products/test.jpg', $url);
        $this->assertStringNotContainsString('public/storage', $url);

        // 2. Leading slash
        $urlLeading = Product::getStorageUrl('/products/test.jpg');
        $this->assertSame($url, $urlLeading);

        // 3. Redundant storage/ prefix
        $urlPrefixed = Product::getStorageUrl('storage/products/test.jpg');
        $this->assertSame($url, $urlPrefixed);

        // 4. Redundant public/storage/ prefix
        $urlPublicPrefixed = Product::getStorageUrl('public/storage/products/test.jpg');
        $this->assertSame($url, $urlPublicPrefixed);

        // 5. External full URL
        $external = 'https://s3.amazonaws.com/bucket/products/test.jpg';
        $this->assertSame($external, Product::getStorageUrl($external));

        $externalHttp = 'http://example.com/products/test.jpg';
        $this->assertSame($externalHttp, Product::getStorageUrl($externalHttp));

        // 6. Null or empty string
        $this->assertSame('', Product::getStorageUrl(null));
        $this->assertSame('', Product::getStorageUrl(''));

        // 7. Inline Data URI
        $dataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $this->assertSame($dataUri, Product::getStorageUrl($dataUri));
    }

    public function test_product_update_deletes_old_image_file_from_storage(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();

        $oldPath = 'products/old.jpg';
        $flyerPath = 'flyers/flyer.jpg';
        Storage::disk('public')->put($oldPath, 'fake-old-image-binary');
        Storage::disk('public')->put($flyerPath, 'fake-flyer-binary');

        $product = Product::create([
            'name' => 'Neumático Alfa 29',
            'availability' => 'disponible',
            'image_path' => $oldPath,
            'flyer_path' => $flyerPath,
            'is_active' => true,
        ]);

        Storage::disk('public')->assertExists($oldPath);
        Storage::disk('public')->assertExists($flyerPath);

        $newFile = UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($admin)
            ->put(route('productos.update', $product), [
                'name' => 'Neumático Alfa 29 Pro',
                'availability' => 'disponible',
                'image' => $newFile,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('productos.index'))
            ->assertSessionHas('success');

        $product->refresh();

        // Old image and old flyer must be deleted from storage
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertMissing($flyerPath);
        $this->assertNull($product->flyer_path);

        // New image must exist
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_product_force_delete_cleans_up_all_storage_assets(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();

        $imagePath = 'products/main.jpg';
        $flyerPath = 'flyers/flyer.jpg';
        $galleryPath = 'products/gallery/gallery.jpg';

        Storage::disk('public')->put($imagePath, 'fake-main');
        Storage::disk('public')->put($flyerPath, 'fake-flyer');
        Storage::disk('public')->put($galleryPath, 'fake-gallery');

        $product = Product::create([
            'category_id' => null, // Allowed to forceDelete only without category
            'name' => 'Producto Descontinuado',
            'availability' => 'agotado',
            'image_path' => $imagePath,
            'flyer_path' => $flyerPath,
            'is_active' => false,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => $galleryPath,
            'sort_order' => 1,
        ]);

        Storage::disk('public')->assertExists($imagePath);
        Storage::disk('public')->assertExists($flyerPath);
        Storage::disk('public')->assertExists($galleryPath);

        $response = $this->actingAs($admin)
            ->delete(route('productos.forceDelete', $product));

        $response->assertSessionHas('success');

        Storage::disk('public')->assertMissing($imagePath);
        Storage::disk('public')->assertMissing($flyerPath);
        Storage::disk('public')->assertMissing($galleryPath);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_category_index_does_not_exhibit_n_plus_one_queries(): void
    {
        $admin = $this->createAdmin();

        // Create 15 categories with products to test pagination and eager count
        for ($i = 1; $i <= 15; $i++) {
            $cat = Category::create([
                'name' => "Categoría {$i}",
                'description' => "Descripción {$i}",
                'is_active' => true,
            ]);

            Product::create([
                'name' => "Producto {$i}",
                'category_id' => $cat->id,
                'availability' => 'disponible',
                'is_active' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('categoria.index'));
        $response->assertOk();

        $queries = DB::getQueryLog();

        // Without withCount, 1 pagination count + 1 select categories + 10 individual product count queries = 12 queries.
        // With withCount, it takes exactly 2 queries (1 count total + 1 select with products_count subquery).
        $this->assertLessThanOrEqual(3, count($queries), 'Category index query count exceeded limit, possible N+1 query detected');
    }
}
