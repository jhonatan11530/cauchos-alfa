<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    /** Caso positivo: la vista de login carga para invitados */
    public function test_muestra_el_formulario_de_login(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Panel administrativo');
    }

    /** Caso positivo: login correcto redirige al dashboard */
    public function test_login_exitoso_redirige_al_dashboard(): void
    {
        $admin = $this->createAdmin(['email' => 'admin@test.com']);

        $this->post(route('login.store'), [
            'email' => 'admin@test.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /** Caso positivo: usuario ya autenticado es redirigido al dashboard */
    public function test_usuario_autenticado_es_redirigido_al_dashboard(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    /** Caso negativo: credenciales incorrectas */
    public function test_credenciales_incorrectas_rechazan_el_login(): void
    {
        $this->createAdmin(['email' => 'admin@test.com']);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'admin@test.com',
                'password' => 'wrong-password',
            ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** Caso negativo: usuario inactivo no puede iniciar sesión */
    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $this->createAdmin(['email' => 'inactivo@test.com', 'is_active' => false]);

        $this->post(route('login.store'), [
            'email' => 'inactivo@test.com',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** Caso negativo: validación de campos requeridos */
    public function test_valida_campos_requeridos(): void
    {
        $this->post(route('login.store'), [
            'email' => 'no-valido',
            'password' => '',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    /** Caso positivo: logout cierra la sesión */
    public function test_logout_cierra_la_sesion(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** Caso negativo: invitado no accede al dashboard */
    public function test_invitado_no_accede_al_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /** Caso positivo: con recaptcha configurado y token válido permite el login */
    public function test_login_con_recaptcha_valido_permite_acceso(): void
    {
        config([
            'recaptchav3.sitekey' => 'fake-site-key',
            'recaptchav3.secret' => 'fake-secret',
        ]);

        \Lunaweb\RecaptchaV3\Facades\RecaptchaV3::shouldReceive('verify')
            ->once()
            ->with('token-valido', 'login')
            ->andReturn(0.9);

        $admin = $this->createAdmin(['email' => 'admin-captcha@test.com']);

        $this->post(route('login.store'), [
            'email' => 'admin-captcha@test.com',
            'password' => 'secret-password',
            'g-recaptcha-response' => 'token-valido',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /** Caso negativo: con recaptcha configurado y score bajo o token inválido rechaza el login */
    public function test_login_con_recaptcha_invalido_es_rechazado(): void
    {
        config([
            'recaptchav3.sitekey' => 'fake-site-key',
            'recaptchav3.secret' => 'fake-secret',
        ]);

        \Lunaweb\RecaptchaV3\Facades\RecaptchaV3::shouldReceive('verify')
            ->once()
            ->with('token-invalido', 'login')
            ->andReturn(0.2);

        $this->createAdmin(['email' => 'admin-captcha@test.com']);

        $this->post(route('login.store'), [
            'email' => 'admin-captcha@test.com',
            'password' => 'secret-password',
            'g-recaptcha-response' => 'token-invalido',
        ])->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
    }
}

class SellerAccessRestrictionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    /** Caso negativo: un vendedor no puede entrar al dashboard administrativo */
    public function test_vendedor_no_accede_al_dashboard(): void
    {
        $seller = $this->createSeller();

        $this->actingAs($seller)->get(route('dashboard'))
            ->assertRedirect(route('site.catalog'));
    }

    /** Caso negativo: un vendedor no gestiona usuarios administrativos */
    public function test_vendedor_no_gestiona_usuarios(): void
    {
        $seller = $this->createSeller();

        $this->actingAs($seller)->get(route('usuarios.index'))
            ->assertRedirect(route('site.catalog'));

        $this->actingAs($seller)->post(route('usuarios.store'), ['name' => 'x'])
            ->assertRedirect(route('site.catalog'));
    }
}
