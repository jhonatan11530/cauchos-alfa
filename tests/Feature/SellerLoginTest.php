<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class SellerLoginTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('seller-login:vend001|127.0.0.1');
    }

    /** Caso positivo: la vista del formulario carga */
    public function test_muestra_el_formulario_de_acceso(): void
    {
        $this->get(route('site.seller.login'))
            ->assertOk()
            ->assertSee('Vendedor');
    }

    /** Caso positivo: login correcto redirige al catálogo con saludo */
    public function test_login_exitoso_redirige_al_catalogo(): void
    {
        $seller = $this->createSeller(['seller_code' => 'VEND001']);

        $this->post(route('site.seller.auth'), ['code' => 'vend001']) // minúsculas también funcionan
            ->assertRedirect(route('site.catalog'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($seller);
    }

    /** Caso positivo: ya autenticado como vendedor lo redirige al catálogo */
    public function test_vendedor_autenticado_es_redirigido_al_catalogo(): void
    {
        $seller = $this->createSeller();

        $this->actingAs($seller)->get(route('site.seller.login'))
            ->assertRedirect(route('site.catalog'));
    }

    /** Caso negativo: código inválido */
    public function test_codigo_invalido_muestra_error(): void
    {
        $this->createSeller(['seller_code' => 'VEND001']);

        $this->from(route('site.seller.login'))
            ->post(route('site.seller.auth'), ['code' => 'NOEXISTE'])
            ->assertRedirect(route('site.seller.login'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    /** Caso negativo: código vacío o campo requerido */
    public function test_codigo_vacio_es_rechazado_por_validacion(): void
    {
        $this->post(route('site.seller.auth'), ['code' => ''])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    /** Caso negativo: código de usuario inactivo */
    public function test_vendedor_inactivo_no_puede_ingresar(): void
    {
        $this->createSeller(['seller_code' => 'VEND001', 'is_active' => false]);

        $this->post(route('site.seller.auth'), ['code' => 'VEND001'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    /** Caso negativo: un código válido de usuario NO vendedor es rechazado */
    public function test_codigo_de_administrador_es_rechazado(): void
    {
        $this->createAdmin(['seller_code' => 'ADMIN01']);

        $this->post(route('site.seller.auth'), ['code' => 'ADMIN01'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    /** Caso límite: después de 5 intentos fallidos se aplica throttling */
    public function test_limita_los_intentos_fallidos(): void
    {
        $seller = $this->createSeller(['seller_code' => 'VEND001', 'is_active' => false]);

        foreach (range(1, 5) as $i) {
            $this->post(route('site.seller.auth'), ['code' => 'VEND001']);
        }

        // El vendedor se activa, pero el bloqueo temporal ya esta activo
        $seller->update(['is_active' => true]);

        $response = $this->post(route('site.seller.auth'), ['code' => 'VEND001']);

        $response->assertSessionHasErrors('code');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('code'));
        $this->assertGuest();
    }

    /** Caso positivo: el logout cierra la sesión y redirige al inicio */
    public function test_logout_cierra_la_sesion(): void
    {
        $seller = $this->createSeller();
        $this->actingAs($seller);

        $this->post(route('site.seller.logout'))
            ->assertRedirect(route('site.home'));

        $this->assertGuest();
    }

    /** Caso positivo: vendedor con recaptcha configurado y token válido puede iniciar sesión */
    public function test_vendedor_con_recaptcha_valido_permite_acceso(): void
    {
        config([
            'recaptchav3.sitekey' => 'fake-site-key',
            'recaptchav3.secret' => 'fake-secret',
        ]);

        \Lunaweb\RecaptchaV3\Facades\RecaptchaV3::shouldReceive('verify')
            ->once()
            ->with('token-valido', 'seller_login')
            ->andReturn(0.8);

        $seller = $this->createSeller(['seller_code' => 'VEND002']);

        $this->post(route('site.seller.auth'), [
            'code' => 'VEND002',
            'g-recaptcha-response' => 'token-valido',
        ])->assertRedirect(route('site.catalog'))
          ->assertSessionHas('success');

        $this->assertAuthenticatedAs($seller);
    }

    /** Caso negativo: vendedor con recaptcha configurado y score bajo es rechazado */
    public function test_vendedor_con_recaptcha_invalido_es_rechazado(): void
    {
        config([
            'recaptchav3.sitekey' => 'fake-site-key',
            'recaptchav3.secret' => 'fake-secret',
        ]);

        \Lunaweb\RecaptchaV3\Facades\RecaptchaV3::shouldReceive('verify')
            ->once()
            ->with('token-invalido', 'seller_login')
            ->andReturn(0.2);

        $this->createSeller(['seller_code' => 'VEND003']);

        $this->post(route('site.seller.auth'), [
            'code' => 'VEND003',
            'g-recaptcha-response' => 'token-invalido',
        ])->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
    }
}

