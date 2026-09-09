<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppMessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrador', 'slug' => 'administrador']);
        $this->admin = User::create([
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_and_list_a_message_template(): void
    {
        $response = $this->actingAs($this->admin)->post(route('whatsapp.templates.store'), [
            'name' => 'Catalogo mensual',
            'message' => 'Hola, te comparto nuestro catalogo actualizado.',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('whatsapp.templates.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'name' => 'Catalogo mensual',
            'message' => 'Hola, te comparto nuestro catalogo actualizado.',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('whatsapp.index'))
            ->assertOk()
            ->assertSee('message-template')
            ->assertSee('Catalogo mensual')
            ->assertSee('Hola, te comparto nuestro catalogo actualizado.');
    }

    public function test_admin_can_toggle_a_template_without_deleting_it(): void
    {
        $template = \App\Models\WhatsAppMessageTemplate::create([
            'name' => 'Promocion',
            'message' => 'Mensaje promocional',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('whatsapp.templates.destroy', $template))
            ->assertRedirect();

        $this->assertFalse($template->refresh()->is_active);
        $this->assertDatabaseHas('whatsapp_message_templates', ['id' => $template->id]);
    }
}
