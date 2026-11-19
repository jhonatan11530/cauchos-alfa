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

    public function test_critical_slugs_contains_all_eight_expected_slugs(): void
    {
        $expected = [
            'pedido-creado',
            'pedido-recibido',
            'pedido-en-proceso',
            'pedido-despachado',
            'pedido-entregado',
            'pedido-devuelto',
            'pedido-rechazado',
            'pedido-cobrado',
        ];

        $this->assertCount(8, OrderStatus::CRITICAL_SLUGS);
        $this->assertSame($expected, OrderStatus::CRITICAL_SLUGS);
        $this->assertSame($expected, OrderStatus::ALLOWED_SLUGS);
    }

    public function test_is_critical_returns_true_for_official_and_false_for_custom(): void
    {
        $critical = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_DESPACHADO],
            ['name' => 'Pedido despachado', 'color' => '#31ce36', 'sort_order' => 4, 'is_active' => true]
        );

        $custom = new OrderStatus([
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
        $status = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_ENTREGADO],
            ['name' => 'Pedido entregado', 'color' => '#2bb930', 'sort_order' => 5, 'is_final' => true, 'is_active' => true]
        );

        $status->update(['slug' => 'entrega-modificada']);
        $status->refresh();

        $this->assertSame(OrderStatus::SLUG_ENTREGADO, $status->slug);
    }

    public function test_order_status_controller_update_preserves_official_slug_name_flags_and_active_state(): void
    {
        $admin = $this->createAdmin();

        $status = OrderStatus::where('slug', OrderStatus::SLUG_DESPACHADO)->firstOrFail();

        $response = $this->actingAs($admin)
            ->put(route('estados-pedido.update', $status), [
                'name' => 'Despacho Express Modificado',
                'color' => '#00ff00',
                'sort_order' => 12,
                'is_final' => '1',
                'is_cancelled' => '1',
                'is_active' => '0',
            ]);

        $response->assertRedirect(route('estados-pedido.index'))
            ->assertSessionHas('success');

        $status->refresh();
        $this->assertSame('Pedido despachado', $status->name);
        $this->assertSame(OrderStatus::SLUG_DESPACHADO, $status->slug);
        $this->assertSame('#00ff00', $status->color);
        $this->assertSame(12, $status->sort_order);
        $this->assertFalse($status->is_final);
        $this->assertFalse($status->is_cancelled);
        $this->assertTrue($status->is_active);
    }

    public function test_order_status_controller_blocks_creating_additional_statuses(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('estados-pedido.create'))
            ->assertRedirect(route('estados-pedido.index'))
            ->assertSessionHas('error');

        $response = $this->actingAs($admin)
            ->post(route('estados-pedido.store'), [
                'name' => 'Estado Extra No Permitido',
                'color' => '#f25961',
                'sort_order' => 99,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('estados-pedido.index'))
            ->assertSessionHas('error');

        $this->assertSame(8, OrderStatus::count());
        $this->assertDatabaseMissing('order_statuses', ['slug' => 'estado-extra-no-permitido']);
    }

    public function test_order_status_controller_prevents_deactivating_official_status(): void
    {
        $admin = $this->createAdmin();
        $status = OrderStatus::where('slug', OrderStatus::SLUG_CREADO)->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('estados-pedido.index'))
            ->delete(route('estados-pedido.destroy', $status));

        $response->assertRedirect(route('estados-pedido.index'))
            ->assertSessionHas('error');

        $status->refresh();
        $this->assertTrue($status->is_active);
    }

    public function test_order_status_seeder_seeds_only_eight_statuses(): void
    {
        $this->seed(OrderStatusSeeder::class);

        $this->assertSame(8, OrderStatus::count());

        foreach (OrderStatus::CRITICAL_SLUGS as $slug) {
            $this->assertTrue(OrderStatus::where('slug', $slug)->exists(), "Status {$slug} must exist in database");
        }

        foreach (['pedido-en-preparacion', 'pedido-cancelado', 'cobro'] as $removedSlug) {
            $this->assertFalse(OrderStatus::where('slug', $removedSlug)->exists(), "Removed status {$removedSlug} must not exist");
        }

        $delivered = OrderStatus::where('slug', OrderStatus::SLUG_ENTREGADO)->firstOrFail();
        $this->assertTrue($delivered->is_final);
        $this->assertFalse($delivered->is_cancelled);

        $devuelto = OrderStatus::where('slug', OrderStatus::SLUG_DEVUELTO)->firstOrFail();
        $this->assertTrue($devuelto->is_final);
        $this->assertFalse($devuelto->is_cancelled);

        $rejected = OrderStatus::where('slug', OrderStatus::SLUG_RECHAZADO)->firstOrFail();
        $this->assertTrue($rejected->is_final);
        $this->assertTrue($rejected->is_cancelled);

        $collected = OrderStatus::where('slug', OrderStatus::SLUG_COBRADO)->firstOrFail();
        $this->assertTrue($collected->is_final);
        $this->assertFalse($collected->is_cancelled);
    }
}
