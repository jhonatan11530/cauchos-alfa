<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    public function test_guest_cannot_access_user_management(): void
    {
        $response = $this->get(route('usuarios.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_cannot_deactivate_self_via_destroy(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->from(route('usuarios.index'))
            ->delete(route('usuarios.destroy', $admin));

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('error', 'No puedes desactivar o eliminar tu propia cuenta activa.');

        $admin->refresh();
        $this->assertTrue($admin->is_active);
    }

    public function test_admin_cannot_deactivate_self_via_update(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->from(route('usuarios.edit', $admin))
            ->put(route('usuarios.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role_id' => $admin->role_id,
                'is_active' => '0',
            ]);

        $response->assertRedirect(route('usuarios.edit', $admin));
        $response->assertSessionHas('error', 'No puedes desactivar tu propia cuenta activa.');

        $admin->refresh();
        $this->assertTrue($admin->is_active);
    }

    public function test_admin_can_deactivate_another_user(): void
    {
        $admin = $this->createAdmin();
        $otherUser = $this->createSeller(['email' => 'other@test.com', 'seller_code' => 'V-9999']);

        $response = $this->actingAs($admin)
            ->delete(route('usuarios.destroy', $otherUser));

        $response->assertSessionHas('success');

        $otherUser->refresh();
        $this->assertFalse($otherUser->is_active);
    }

    public function test_admin_can_update_another_user(): void
    {
        $admin = $this->createAdmin();
        $otherUser = $this->createSeller(['email' => 'other@test.com', 'seller_code' => 'V-9999']);

        $response = $this->actingAs($admin)
            ->put(route('usuarios.update', $otherUser), [
                'name' => 'Updated Name',
                'email' => 'other@test.com',
                'phone' => '3001112233',
                'role_id' => $otherUser->role_id,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success');

        $otherUser->refresh();
        $this->assertSame('Updated Name', $otherUser->name);
        $this->assertSame('3001112233', $otherUser->phone);
    }
}
