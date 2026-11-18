<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class SellerCatalogDownloadTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    protected User $seller;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRoles();
        $this->seller = $this->createSeller();
        $this->admin = $this->createAdmin();
    }

    /**
     * R1: Un vendedor autenticado puede solicitar y descargar el último archivo PDF de catálogo.
     */
    public function test_authenticated_seller_can_download_latest_active_catalog_pdf(): void
    {
        $category = $this->createCategory(['name' => 'Llantas']);
        $product = $this->createProduct($category, ['name' => 'Llanta Pistera 26x1.50']);

        $catalog = Catalog::create([
            'name' => 'Catálogo General 2026',
            'company_name' => 'Cauchos Alfa',
            'is_active' => true,
        ]);
        $catalog->products()->attach($product->id, ['sort_order' => 1]);

        $response = $this->actingAs($this->seller)
            ->get(route('site.seller.catalog.latest'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $disposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('catalogo-'.$catalog->id.'.pdf', $disposition);
    }

    /**
     * R1: Cuando existen múltiples catálogos, se descarga el último catálogo activo.
     */
    public function test_seller_download_serves_the_latest_catalog_when_multiple_exist(): void
    {
        $catalog1 = Catalog::create([
            'name' => 'Catálogo 2025',
            'company_name' => 'Cauchos Alfa',
            'is_active' => true,
            'created_at' => now()->subMonth(),
        ]);

        $catalog2 = Catalog::create([
            'name' => 'Catálogo 2026',
            'company_name' => 'Cauchos Alfa',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->seller)
            ->get(route('site.seller.catalog.latest'));

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('catalogo-'.$catalog2->id.'.pdf', $disposition);
    }

    /**
     * R1: Si el catálogo más reciente está inactivo pero existe uno previo activo, se prioriza el activo.
     */
    public function test_seller_download_prioritizes_active_catalog_over_inactive(): void
    {
        $activeCatalog = Catalog::create([
            'name' => 'Catálogo Activo',
            'company_name' => 'Cauchos Alfa',
            'is_active' => true,
        ]);

        $inactiveCatalog = Catalog::create([
            'name' => 'Catálogo Inactivo Reciente',
            'company_name' => 'Cauchos Alfa',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->seller)
            ->get(route('site.seller.catalog.latest'));

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('catalogo-'.$activeCatalog->id.'.pdf', $disposition);
    }

    /**
     * R1 / Edge case: Si no existe ningún catálogo creado, se maneja de forma controlada sin error 500.
     */
    public function test_empty_catalog_state_redirects_with_warning_and_no_500(): void
    {
        $this->assertSame(0, Catalog::count());

        $response = $this->actingAs($this->seller)
            ->get(route('site.seller.catalog.latest'));

        $this->assertNotEquals(500, $response->getStatusCode());
        $response->assertRedirect(route('site.catalog'));
        $response->assertSessionHas('warning', 'No hay ningún catálogo de productos disponible para descargar.');
    }

    /**
     * R2: Solicitudes de descarga sin autenticación son redirigidas a login (302).
     */
    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        Catalog::create([
            'name' => 'Catálogo General',
            'is_active' => true,
        ]);

        $response = $this->get(route('site.seller.catalog.latest'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * R2: Usuario autenticado sin rol vendedor o administrador es rechazado con 403 Forbidden.
     */
    public function test_non_seller_unauthorized_user_is_rejected_with_403(): void
    {
        Catalog::create([
            'name' => 'Catálogo Confidencial',
            'is_active' => true,
        ]);

        $unauthorizedUser = User::create([
            'role_id' => null,
            'name' => 'Usuario Regular',
            'email' => 'regular@test.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $response = $this->actingAs($unauthorizedUser)
            ->get(route('site.seller.catalog.latest'));

        $response->assertStatus(403);
    }

    /**
     * R1 / R2: Administrador autenticado también puede descargar el catálogo.
     */
    public function test_authenticated_admin_can_download_latest_catalog(): void
    {
        $catalog = Catalog::create([
            'name' => 'Catálogo Admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('site.seller.catalog.latest'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    /**
     * R1 / UI: La barra de navegación muestra el enlace de descarga para el vendedor autenticado.
     */
    public function test_navbar_renders_download_link_for_authenticated_seller(): void
    {
        $response = $this->actingAs($this->seller)
            ->get(route('site.catalog'));

        $response->assertOk();
        $response->assertSee(route('site.seller.catalog.latest'), false);
        $response->assertSee('Catálogo PDF');
    }

    /**
     * R2 / UI: La barra de navegación no muestra el enlace de descarga para invitados.
     */
    public function test_navbar_does_not_render_download_link_for_guests(): void
    {
        $response = $this->get(route('site.catalog'));

        $response->assertOk();
        $response->assertDontSee(route('site.seller.catalog.latest'), false);
    }

    /**
     * R1 / UI: La vista de catálogo muestra el botón de descarga en el hero para el vendedor autenticado.
     */
    public function test_catalog_view_hero_renders_download_button_for_authenticated_seller(): void
    {
        $response = $this->actingAs($this->seller)
            ->get(route('site.catalog'));

        $response->assertOk();
        $response->assertSee(route('site.seller.catalog.latest'), false);
        $response->assertSee('Descargar último catálogo (PDF)');
    }

    /**
     * R2 / UI: La vista de catálogo no muestra el botón de descarga para invitados.
     */
    public function test_catalog_view_hero_does_not_render_download_button_for_guests(): void
    {
        $response = $this->get(route('site.catalog'));

        $response->assertOk();
        $response->assertDontSee('Descargar último catálogo (PDF)');
    }

    public function test_seller_download_falls_back_gracefully_for_legacy_removed_template(): void
    {
        $category = $this->createCategory(['name' => 'Repuestos']);
        $product = $this->createProduct($category, ['name' => 'Producto Legacy Vendedor']);

        $catalog = Catalog::create([
            'name' => 'Catálogo Legacy',
            'template' => 'classic',
            'is_active' => true,
        ]);
        $catalog->products()->attach($product->id, ['sort_order' => 1]);

        $response = $this->actingAs($this->seller)
            ->get(route('site.seller.catalog.latest'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_seller_download_respects_custom_category_and_product_order(): void
    {
        $cat1 = $this->createCategory(['name' => 'CatSellerUno']);
        $cat2 = $this->createCategory(['name' => 'CatSellerDos']);
        $p1 = $this->createProduct($cat1, ['name' => 'SELLER_P1']);
        $p2 = $this->createProduct($cat2, ['name' => 'SELLER_P2']);

        $catalog = Catalog::create([
            'name' => 'Catálogo Vendedor Ordenado',
            'template' => 'catalogo-web',
            'category_order' => [(string) $cat2->id, (string) $cat1->id],
            'is_active' => true,
        ]);
        $catalog->products()->sync([
            $p2->id => ['sort_order' => 1],
            $p1->id => ['sort_order' => 2],
        ]);

        $response = $this->actingAs($this->seller)
            ->get(route('site.seller.catalog.latest'));

        $response->assertOk();
        $pdfBytes = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfBytes);

        // Extract uncompressed streams from Helvetica PDF to confirm SELLER_P2 precedes SELLER_P1
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfBytes, $matches);
        $allText = '';
        foreach ($matches[1] as $raw) {
            $dec = @gzuncompress($raw);
            $allText .= ($dec !== false ? $dec : $raw);
        }
        $posP2 = strpos($allText, 'SELLER_P2');
        $posP1 = strpos($allText, 'SELLER_P1');
        $this->assertNotFalse($posP2);
        $this->assertNotFalse($posP1);
        $this->assertLessThan($posP1, $posP2);
    }
}
