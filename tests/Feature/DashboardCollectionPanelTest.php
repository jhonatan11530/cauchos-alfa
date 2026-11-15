<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCollectionPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;
    private Client $client;
    private OrderStatus $creadoStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);
        $sellerRole = Role::firstOrCreate(['slug' => 'vendedor'], ['name' => 'Vendedor']);

        $this->admin = User::firstOrCreate(['email' => 'admin_dash@test.com'], [
            'role_id' => $adminRole->id,
            'name' => 'Admin Dashboard',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->seller = User::firstOrCreate(['email' => 'seller_dash@test.com'], [
            'role_id' => $sellerRole->id,
            'name' => 'Vendedor Test',
            'seller_code' => 'VEND_DASH',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Empresa Andina S.A.S.',
            'email' => 'andina@test.com',
            'is_active' => true,
        ]);

        $this->creadoStatus = OrderStatus::firstOrCreate(
            ['slug' => OrderStatus::SLUG_CREADO],
            ['name' => 'Pedido creado', 'color' => '#1572e8', 'sort_order' => 1, 'is_active' => true]
        );
    }

    private function createCreditOrder(string $code, string $dueDate, ?string $paidAt = null): Order
    {
        return Order::create([
            'code' => $code,
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->creadoStatus->id,
            'billing_type' => 'factura_electronica',
            'payment_term' => 'credito',
            'payment_due_date' => $dueDate,
            'paid_at' => $paidAt,
            'ordered_at' => '2026-11-01',
        ]);
    }

    public function test_dashboard_displays_pending_credit_invoices_ordered_by_due_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $orderA = $this->createCreditOrder('FACT-001', '2026-11-20'); // In 5 days
        $orderB = $this->createCreditOrder('FACT-002', '2026-11-10'); // Overdue by 5 days
        $orderC = $this->createCreditOrder('FACT-003', '2026-11-25'); // In 10 days

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('pendingInvoices');
        $response->assertSee('Facturas por cobrar según vencimiento');

        $pending = $response->viewData('pendingInvoices');
        $this->assertCount(3, $pending);
        $this->assertSame($orderB->id, $pending[0]->id);
        $this->assertSame($orderA->id, $pending[1]->id);
        $this->assertSame($orderC->id, $pending[2]->id);

        $response->assertSeeInOrder([
            'FACT-002',
            'FACT-001',
            'FACT-003',
        ]);

        $this->travelBack();
    }

    public function test_dashboard_highlights_overdue_and_upcoming_invoices(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $this->createCreditOrder('FACT-OVERDUE', '2026-11-10'); // 5 days past due
        $this->createCreditOrder('FACT-TODAY', '2026-11-15');   // Due today
        $this->createCreditOrder('FACT-FUTURE', '2026-11-19');  // Due in 4 days

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Vencida hace 5 días');
        $response->assertSee('Vence hoy');
        $response->assertSee('Vence en 4 días');
        $response->assertSee('badge-danger');
        $response->assertSee('badge-warning');

        $this->travelBack();
    }

    public function test_dashboard_excludes_contado_and_already_collected_orders(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        // 1. Pending credit order (should appear)
        $creditPending = $this->createCreditOrder('FACT-PENDING', '2026-11-20');

        // 2. Contado order (should NOT appear)
        $contadoOrder = Order::create([
            'code' => 'FACT-CONTADO',
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $this->creadoStatus->id,
            'billing_type' => 'remision',
            'payment_term' => 'contado',
            'ordered_at' => '2026-11-01',
        ]);

        // 3. Paid credit order (should NOT appear)
        $creditPaid = $this->createCreditOrder('FACT-PAID', '2026-11-10', '2026-11-12 14:00:00');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $pending = $response->viewData('pendingInvoices');

        $this->assertCount(1, $pending);
        $this->assertSame($creditPending->id, $pending->first()->id);
        $this->assertFalse($pending->contains('id', $contadoOrder->id));
        $this->assertFalse($pending->contains('id', $creditPaid->id));

        $this->travelBack();
    }

    public function test_seller_is_redirected_away_from_dashboard(): void
    {
        $response = $this->actingAs($this->seller)->get(route('dashboard'));

        $response->assertRedirect(route('site.catalog'));
    }
}
