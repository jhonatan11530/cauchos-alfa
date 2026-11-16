<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PricingRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_price_column_is_removed_and_product_form_has_no_price_fields(): void
    {
        $this->assertFalse(Schema::hasColumn('products', 'price'));

        $response = $this->actingAs($this->createAdminUser())
            ->get(route('productos.create'));

        $response->assertOk()
            ->assertDontSee('Precio')
            ->assertDontSee('price');
    }

    private function createAdminUser(): User
    {
        $role = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
