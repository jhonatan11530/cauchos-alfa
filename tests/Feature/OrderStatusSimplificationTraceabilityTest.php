<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class OrderStatusSimplificationTraceabilityTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRoles();
        $this->admin = $this->createAdmin();
        $this->client = $this->createClient(['name' => 'Cliente Trazabilidad']);
    }

    public function test_migration_remaps_surplus_statuses_preserves_all_histories_and_is_idempotent(): void
    {
        $creado = OrderStatus::where('slug', OrderStatus::SLUG_CREADO)->firstOrFail();
        $proceso = OrderStatus::where('slug', OrderStatus::SLUG_EN_PROCESO)->firstOrFail();
        $entregado = OrderStatus::where('slug', OrderStatus::SLUG_ENTREGADO)->firstOrFail();
        $rechazado = OrderStatus::where('slug', OrderStatus::SLUG_RECHAZADO)->firstOrFail();
        $cobrado = OrderStatus::where('slug', OrderStatus::SLUG_COBRADO)->firstOrFail();

        // Simulate legacy/production surplus statuses in DB before simplification migration runs.
        $prepId = DB::table('order_statuses')->insertGetId([
            'name' => 'Pedido en preparacion',
            'slug' => 'pedido-en-preparacion',
            'color' => '#ffad46',
            'sort_order' => 3,
            'is_final' => false,
            'is_cancelled' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cancelId = DB::table('order_statuses')->insertGetId([
            'name' => 'Pedido cancelado',
            'slug' => 'pedido-cancelado',
            'color' => '#f25961',
            'sort_order' => 7,
            'is_final' => true,
            'is_cancelled' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cobroId = DB::table('order_statuses')->insertGetId([
            'name' => 'Cobro',
            'slug' => 'cobro',
            'color' => '#17a2b8',
            'sort_order' => 11,
            'is_final' => true,
            'is_cancelled' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customId = DB::table('order_statuses')->insertGetId([
            'name' => 'Revision Bodega Extra',
            'slug' => 'revision-bodega-extra',
            'color' => '#999999',
            'sort_order' => 4,
            'is_final' => false,
            'is_cancelled' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Orders currently sitting in surplus statuses
        $orderPrep = Order::create([
            'code' => 'PED-LEG-PREP',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $prepId,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $orderCancel = Order::create([
            'code' => 'PED-LEG-CANC',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $cancelId,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $orderCobro = Order::create([
            'code' => 'PED-LEG-COBRO',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $cobroId,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $orderCustom = Order::create([
            'code' => 'PED-LEG-CUST',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $customId,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        // Multi-step histories referencing surplus statuses as both previous_status_id and new_status_id
        $h1 = OrderStatusHistory::create([
            'order_id' => $orderPrep->id,
            'previous_status_id' => null,
            'new_status_id' => $creado->id,
            'user_id' => $this->admin->id,
            'observation' => 'Pedido creado.',
            'changed_at' => now()->subHours(3),
        ]);

        $h2 = OrderStatusHistory::create([
            'order_id' => $orderPrep->id,
            'previous_status_id' => $creado->id,
            'new_status_id' => $prepId,
            'user_id' => $this->admin->id,
            'observation' => 'Alistando llantas en bodega.',
            'changed_at' => now()->subHours(2),
        ]);

        // Transition out of surplus status into canonical status (tests previous_status_id preservation)
        $h3 = OrderStatusHistory::create([
            'order_id' => $orderCancel->id,
            'previous_status_id' => $prepId,
            'new_status_id' => $cancelId,
            'user_id' => $this->admin->id,
            'observation' => '',
            'changed_at' => now()->subHour(),
        ]);

        $h4 = OrderStatusHistory::create([
            'order_id' => $orderCobro->id,
            'previous_status_id' => $entregado->id,
            'new_status_id' => $cobroId,
            'user_id' => $this->admin->id,
            'observation' => 'Pago verificado.',
            'changed_at' => now()->subMinutes(30),
        ]);

        $h5 = OrderStatusHistory::create([
            'order_id' => $orderCustom->id,
            'previous_status_id' => $creado->id,
            'new_status_id' => $customId,
            'user_id' => $this->admin->id,
            'observation' => 'Revisión especial.',
            'changed_at' => now()->subMinutes(15),
        ]);

        $migration = require database_path('migrations/2026_11_19_000001_simplify_order_statuses_to_eight_states.php');

        // Execute migration twice to prove strict idempotency
        $migration->up();
        $migration->up();

        // 1. Exact 8 statuses remain
        $this->assertSame(8, OrderStatus::count());
        $this->assertSame(
            OrderStatus::ALLOWED_SLUGS,
            OrderStatus::orderBy('sort_order')->pluck('slug')->all()
        );

        // 2. All orders remapped cleanly with zero broken status links
        $this->assertSame($proceso->id, $orderPrep->fresh()->order_status_id);
        $this->assertSame($rechazado->id, $orderCancel->fresh()->order_status_id);
        $this->assertSame($cobrado->id, $orderCobro->fresh()->order_status_id);
        $this->assertSame($proceso->id, $orderCustom->fresh()->order_status_id);

        // 3. 100% of history rows preserved without nulling previous_status_id
        $this->assertSame(5, OrderStatusHistory::count());

        $h1->refresh();
        $h2->refresh();
        $h3->refresh();
        $h4->refresh();
        $h5->refresh();

        $this->assertNull($h1->previous_status_id);
        $this->assertSame($creado->id, $h1->new_status_id);

        $this->assertSame($creado->id, $h2->previous_status_id);
        $this->assertSame($proceso->id, $h2->new_status_id);
        $this->assertSame('Alistando llantas en bodega. [Estado original: Pedido en preparacion]', $h2->observation);
        // Verify tag was NOT duplicated on second migration run
        $this->assertSame(1, substr_count($h2->observation, '[Estado original:'));

        $this->assertSame($proceso->id, $h3->previous_status_id);
        $this->assertSame($rechazado->id, $h3->new_status_id);
        $this->assertStringContainsString('[Estado original: Pedido cancelado]', $h3->observation);
        $this->assertStringContainsString('[Estado previo original: Pedido en preparacion]', $h3->observation);
        $this->assertSame(1, substr_count($h3->observation, '[Estado previo original:'));

        $this->assertSame($entregado->id, $h4->previous_status_id);
        $this->assertSame($cobrado->id, $h4->new_status_id);
        $this->assertStringContainsString('[Estado original: Cobro]', $h4->observation);

        $this->assertSame($creado->id, $h5->previous_status_id);
        $this->assertSame($proceso->id, $h5->new_status_id);
        $this->assertStringContainsString('[Estado original: Revision Bodega Extra]', $h5->observation);

        // 4. Views render HTTP 200 with intact traceability and only the 8 official statuses
        $this->actingAs($this->admin)
            ->get(route('pedidos.index'))
            ->assertOk()
            ->assertSee('PED-LEG-PREP')
            ->assertSee('PED-LEG-CANC')
            ->assertSee('PED-LEG-COBRO')
            ->assertDontSee('Pedido en preparacion')
            ->assertDontSee('Pedido cancelado');

        $this->actingAs($this->admin)
            ->get(route('pedidos.show', $orderCancel))
            ->assertOk()
            ->assertSee('Pedido rechazado')
            ->assertSee('[Estado original: Pedido cancelado]')
            ->assertDontSee('pedido-en-preparacion')
            ->assertDontSee('pedido-cancelado');
    }

    public function test_terminal_states_block_invalid_transitions_while_allowing_valid_workflow(): void
    {
        $creado = OrderStatus::where('slug', OrderStatus::SLUG_CREADO)->firstOrFail();
        $enProceso = OrderStatus::where('slug', OrderStatus::SLUG_EN_PROCESO)->firstOrFail();
        $cobrado = OrderStatus::where('slug', OrderStatus::SLUG_COBRADO)->firstOrFail();
        $rechazado = OrderStatus::where('slug', OrderStatus::SLUG_RECHAZADO)->firstOrFail();
        $devuelto = OrderStatus::where('slug', OrderStatus::SLUG_DEVUELTO)->firstOrFail();

        // Valid transition: creado -> en-proceso
        $order = Order::create([
            'code' => 'PED-RULE-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $creado->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $enProceso->id,
                'observation' => 'Pasando a proceso',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($enProceso->id, $order->fresh()->order_status_id);

        // Terminal states reject transitioning to other statuses
        foreach ([$cobrado, $rechazado, $devuelto] as $terminal) {
            $terminalOrder = Order::create([
                'code' => 'PED-TERM-'.$terminal->id,
                'client_id' => $this->client->id,
                'created_by' => $this->admin->id,
                'order_status_id' => $terminal->id,
                'billing_type' => 'remision',
                'ordered_at' => now()->toDateString(),
            ]);

            $this->actingAs($this->admin)
                ->patch(route('pedidos.estado', $terminalOrder), [
                    'order_status_id' => $creado->id,
                ])
                ->assertSessionHasErrors('order_status_id');

            $this->assertSame($terminal->id, $terminalOrder->fresh()->order_status_id);
        }

        // Same-status standalone payment_term update remains permitted
        $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $enProceso->id,
                'payment_term' => 'contado',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('contado', $order->fresh()->payment_term);
    }

    public function test_transition_to_inactive_status_is_rejected(): void
    {
        $creado = OrderStatus::where('slug', OrderStatus::SLUG_CREADO)->firstOrFail();
        $enProceso = OrderStatus::where('slug', OrderStatus::SLUG_EN_PROCESO)->firstOrFail();

        // Simulate an inactive status directly in DB
        DB::table('order_statuses')->where('id', $enProceso->id)->update(['is_active' => false]);

        $order = Order::create([
            'code' => 'PED-INACTIVE-001',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $creado->id,
            'billing_type' => 'remision',
            'ordered_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('pedidos.estado', $order), [
                'order_status_id' => $enProceso->id,
            ])
            ->assertSessionHasErrors('order_status_id');

        $this->assertSame($creado->id, $order->fresh()->order_status_id);
    }
}
