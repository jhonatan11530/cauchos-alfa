<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendedorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Administrador', 'slug' => 'administrador']);
        $sellerRole = Role::create(['name' => 'Vendedor', 'slug' => 'vendedor']);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
    }

    public function test_store_creates_seller_with_automatic_email_and_password(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('vendedores.store'), [
                'name' => 'Juan Pérez',
                'phone' => '3001234567',
                'seller_code' => 'V-0001',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('vendedores.index'))
            ->assertSessionHas('success');

        $seller = User::where('seller_code', 'V-0001')->firstOrFail();

        $this->assertSame('juan.perez@cauchosalfa.com', $seller->email);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('V-0001-' . now()->format('Ymd'), $seller->password));
        $this->assertSame('3001234567', $seller->phone);
    }

    public function test_edit_page_shows_seller_data(): void
    {
        $seller = User::create([
            'role_id' => Role::where('slug', 'vendedor')->first()->id,
            'name' => 'Maria Lopez',
            'email' => 'maria.lopez@cauchosalfa.com',
            'seller_code' => 'V-0002',
            'phone' => '3109876543',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('vendedores.edit', $seller));

        $response->assertOk()
            ->assertSee('Maria Lopez')
            ->assertSee('V-0002')
            ->assertSee('3109876543');
    }

    public function test_update_changes_seller_data(): void
    {
        $seller = User::create([
            'role_id' => Role::where('slug', 'vendedor')->first()->id,
            'name' => 'Maria Lopez',
            'email' => 'maria.lopez@cauchosalfa.com',
            'seller_code' => 'V-0002',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('vendedores.update', $seller), [
                'name' => 'Maria Lopez Actualizada',
                'phone' => '3111111111',
                'seller_code' => 'V-0002',
                'is_active' => true,
            ])
            ->assertRedirect(route('vendedores.index'))
            ->assertSessionHas('success');

        $seller->refresh();
        $this->assertSame('Maria Lopez Actualizada', $seller->name);
        $this->assertSame('3111111111', $seller->phone);
        $this->assertTrue($seller->is_active);
    }

    public function test_destroy_toggles_seller_active_state(): void
    {
        $seller = User::create([
            'role_id' => Role::where('slug', 'vendedor')->first()->id,
            'name' => 'Maria Lopez',
            'email' => 'maria.lopez@cauchosalfa.com',
            'seller_code' => 'V-0002',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('vendedores.destroy', $seller))
            ->assertRedirect();

        $this->assertFalse($seller->refresh()->is_active);

        $this->actingAs($this->admin)
            ->delete(route('vendedores.destroy', $seller))
            ->assertRedirect();

        $this->assertTrue($seller->refresh()->is_active);
    }

    public function test_index_lists_sellers(): void
    {
        $seller = User::create([
            'role_id' => Role::where('slug', 'vendedor')->first()->id,
            'name' => 'Maria Lopez',
            'email' => 'maria.lopez@cauchosalfa.com',
            'seller_code' => 'V-0002',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('vendedores.index'))
            ->assertOk()
            ->assertSee('Maria Lopez')
            ->assertSee('V-0002');
    }
}
