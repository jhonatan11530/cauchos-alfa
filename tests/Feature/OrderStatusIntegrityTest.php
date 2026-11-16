<?php

namespace Tests\Feature;

use App\Models\OrderStatus;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class OrderStatusIntegrityTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    public function test_critical_slugs_contains_all_eleven_expected_slugs(): void
    {
        $expected = [
            'pedido-creado',
            'pedido-recibido',
            'pedido-en-preparacion',
            'pedido-en-proceso',
            'pedido-despachado',
            'pedido-entregado',
            'pedido-cancelado',
            'pedido-devuelto',
            'pedido-rechazado',
            'pedido-cobrado',
            'cobro',
        ];

        $this->assertCount(11, OrderStatus::CRITICAL_SLUGS);
        foreach ($expected as $slug) {
            $this->assertContains($slug, OrderStatus::CRITICAL_SLUGS);
        }
    }

    public function test_is_critical_returns_true_for_critical_and_false_for_custom(): void
    {
        $critical = OrderStatus::create([
            'name' => 'Pedido despachado',
            'slug' => OrderStatus::SLUG_DESPACHADO,
            'color' => '#31ce36',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $custom = OrderStatus::create([
            'name' => 'En revision manual',
            'slug' => 'en-revision-manual',
            'color' => '#888888',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $this->assertTrue($critical->isCritical());
        $this->assertFalse($custom->isCritical());
    }

    public function test_booted_hook_prevents_slug_mutation_on_critical_order_status(): void
    {
        $status = OrderStatus::create([
            'name' => 'Pedido entregado',
            'slug' => OrderStatus::SLUG_ENTREGADO,
            'color' => '#2bb930',
            'sort_order' => 6,
            'is_final' => true,
            'is_active' => true,
        ]);

        $status->update(['slug' => 'entrega-modificada']);
        $status->refresh();

        $this->assertSame(OrderStatus::SLUG_ENTREGADO, $status->slug);
    }

    public function test_order_status_controller_update_preserves_critical_slug(): void
    {
        $admin = $this->createAdmin();

        $status = OrderStatus::create([
            'name' => 'Pedido despachado',
            'slug' => OrderStatus::SLUG_DESPACHADO,
            'color' => '#31ce36',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('estados-pedido.update', $status), [
                'name' => 'Despacho Express Modificado',
                'color' => '#00ff00',
                'sort_order' => 5,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('estados-pedido.index'))
            ->assertSessionHas('success');

        $status->refresh();
        $this->assertSame('Despacho Express Modificado', $status->name);
        $this->assertSame(OrderStatus::SLUG_DESPACHADO, $status->slug);
    }

    public function test_order_status_controller_rejects_reserved_slug_on_non_critical_update(): void
    {
        $admin = $this->createAdmin();

        $custom = OrderStatus::create([
            'name' => 'Personalizado',
            'slug' => 'personalizado',
            'color' => '#999999',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('estados-pedido.edit', $custom))
            ->put(route('estados-pedido.update', $custom), [
                'name' => 'Pedido entregado', // Generates reserved slug 'pedido-entregado'
                'color' => '#999999',
                'sort_order' => 20,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('estados-pedido.edit', $custom))
            ->assertSessionHas('error');

        $custom->refresh();
        $this->assertSame('personalizado', $custom->slug);
    }

    public function test_order_status_controller_rejects_reserved_slug_on_store(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->from(route('estados-pedido.create'))
            ->post(route('estados-pedido.store'), [
                'name' => 'Pedido cancelado', // Generates reserved slug 'pedido-cancelado'
                'color' => '#f25961',
                'sort_order' => 7,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('estados-pedido.create'))
            ->assertSessionHas('error');
    }

    public function test_order_status_seeder_seeds_all_eleven_statuses(): void
    {
        $this->seed(OrderStatusSeeder::class);

        $this->assertSame(11, OrderStatus::count());

        foreach (OrderStatus::CRITICAL_SLUGS as $slug) {
            $this->assertTrue(OrderStatus::where('slug', $slug)->exists(), "Status {$slug} must exist in database");
        }

        $delivered = OrderStatus::where('slug', OrderStatus::SLUG_ENTREGADO)->first();
        $this->assertTrue($delivered->is_final);
        $this->assertFalse($delivered->is_cancelled);

        $cancelled = OrderStatus::where('slug', OrderStatus::SLUG_CANCELADO)->first();
        $this->assertTrue($cancelled->is_final);
        $this->assertTrue($cancelled->is_cancelled);

        $devuelto = OrderStatus::where('slug', OrderStatus::SLUG_DEVUELTO)->first();
        $this->assertTrue($devuelto->is_final);
        $this->assertFalse($devuelto->is_cancelled);
    }
}
