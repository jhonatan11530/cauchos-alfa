<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\GeminiPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    protected function setUp(): void
    {
        parent::setUp();
        GeminiPromptService::clearCache();
    }

    public function test_invitados_no_pueden_acceder_a_configuracion(): void
    {
        $this->get(route('settings.index'))
            ->assertRedirect(route('login'));

        $this->put(route('settings.update'), ['prompt_gemini' => 'Nuevo prompt de prueba'])
            ->assertRedirect(route('login'));
    }

    public function test_vendedor_no_puede_acceder_a_configuracion(): void
    {
        $seller = $this->createSeller();

        $this->actingAs($seller)
            ->get(route('settings.index'))
            ->assertRedirect(route('site.catalog'));

        $this->actingAs($seller)
            ->put(route('settings.update'), ['prompt_gemini' => 'Nuevo prompt de prueba'])
            ->assertRedirect(route('site.catalog'));
    }

    public function test_usuario_no_administrador_recibe_403(): void
    {
        $user = \App\Models\User::create([
            'role_id' => null,
            'name' => 'Usuario Normal',
            'email' => 'normal@test.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('settings.update'), ['prompt_gemini' => 'Nuevo prompt de prueba'])
            ->assertForbidden();
    }

    public function test_admin_puede_ver_pantalla_de_configuracion_con_prompt_activo(): void
    {
        $admin = $this->createAdmin();

        Setting::set(
            GeminiPromptService::PROMPT_KEY,
            'Prompt personalizado de prueba para la vista de configuración'
        );

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk()
            ->assertSee('Configuración del Sistema')
            ->assertSee('Prompt de Gemini (Estudio Fotográfico)')
            ->assertSee('Prompt personalizado de prueba para la vista de configuración');
    }

    public function test_admin_puede_actualizar_prompt_exitosamente(): void
    {
        $admin = $this->createAdmin();

        $nuevoPrompt = 'Instrucción renovada: Foto frontal con iluminación balanceada, fondo blanco y 0 grados de rotación.';

        $response = $this->actingAs($admin)
            ->put(route('settings.update'), [
                'prompt_gemini' => $nuevoPrompt,
            ]);

        $response->assertRedirect(route('settings.index'))
            ->assertSessionHas('success');

        // Verificar persistencia en base de datos
        $this->assertDatabaseHas('settings', [
            'key' => GeminiPromptService::PROMPT_KEY,
            'value' => $nuevoPrompt,
        ]);

        // Verificar que el servicio y la caché entregan el nuevo prompt
        $this->assertSame($nuevoPrompt, GeminiPromptService::get(GeminiPromptService::PROMPT_KEY));
    }

    public function test_validacion_falla_si_prompt_esta_vacio_o_es_muy_corto(): void
    {
        $admin = $this->createAdmin();

        $promptInicial = 'Prompt inicial válido de más de diez caracteres';
        Setting::set(GeminiPromptService::PROMPT_KEY, $promptInicial);

        $response = $this->actingAs($admin)
            ->from(route('settings.index'))
            ->put(route('settings.update'), [
                'prompt_gemini' => '   ',
            ]);

        $response->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors(['prompt_gemini']);

        // Base de datos no debe cambiar
        $this->assertSame($promptInicial, Setting::get(GeminiPromptService::PROMPT_KEY));
    }

    public function test_admin_puede_restablecer_prompt_a_valor_canonico(): void
    {
        $admin = $this->createAdmin();

        Setting::set(GeminiPromptService::PROMPT_KEY, 'Un prompt temporal que será restablecido');

        $response = $this->actingAs($admin)
            ->post(route('settings.reset'));

        $response->assertRedirect(route('settings.index'))
            ->assertSessionHas('info');

        $this->assertSame(
            GeminiPromptService::CANONICAL_FALLBACK_PROMPT,
            Setting::get(GeminiPromptService::PROMPT_KEY)
        );
        $this->assertSame(
            GeminiPromptService::CANONICAL_FALLBACK_PROMPT,
            GeminiPromptService::get()
        );
    }

    public function test_menu_lateral_muestra_enlace_configuracion_solo_a_administradores(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('settings.index'))
            ->assertSee('Configuración');

        $seller = $this->createSeller();
        // Los vendedores son redirigidos al catálogo desde zonas administrativas
        $this->actingAs($seller)
            ->get(route('dashboard'))
            ->assertRedirect(route('site.catalog'));
    }
}
