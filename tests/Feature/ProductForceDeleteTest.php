<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class ProductForceDeleteTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    public function test_admin_can_delete_product_even_when_assigned_to_a_category(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/main.jpg', 'main');
        Storage::disk('public')->put('products/flyers/flyer.jpg', 'flyer');
        Storage::disk('public')->put('products/gallery.jpg', 'gallery');

        $admin = $this->createAdmin();
        $category = $this->createCategory(['name' => 'Calapiés']);
        $product = $this->createProduct($category, [
            'image_path' => 'products/main.jpg',
            'flyer_path' => 'products/flyers/flyer.jpg',
        ]);
        $product->images()->create([
            'path' => 'products/gallery.jpg',
            'sort_order' => 1,
        ]);

        $indexResponse = $this->actingAs($admin)->get(route('productos.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee(route('productos.forceDelete', $product), false);

        $response = $this->actingAs($admin)
            ->from(route('productos.index'))
            ->delete(route('productos.forceDelete', $product));

        $response->assertRedirect(route('productos.index'));
        $response->assertSessionHas('success', 'Producto eliminado definitivamente.');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing('products/main.jpg');
        Storage::disk('public')->assertMissing('products/flyers/flyer.jpg');
        Storage::disk('public')->assertMissing('products/gallery.jpg');
    }

    public function test_admin_cannot_delete_product_with_registered_orders(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/main.jpg', 'main');

        $admin = $this->createAdmin();
        $category = $this->createCategory(['name' => 'Bujes']);
        $product = $this->createProduct($category, [
            'image_path' => 'products/main.jpg',
        ]);
        $client = $this->createClient();
        $status = OrderStatus::create(['name' => 'Pendiente', 'slug' => 'pendiente', 'is_active' => true]);
        $order = Order::create([
            'code' => 'ORD-TEST-001',
            'created_by' => $admin->id,
            'client_id' => $client->id,
            'order_status_id' => $status->id,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('productos.index'))
            ->delete(route('productos.forceDelete', $product));

        $response->assertRedirect(route('productos.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        Storage::disk('public')->assertExists('products/main.jpg');
    }
}
