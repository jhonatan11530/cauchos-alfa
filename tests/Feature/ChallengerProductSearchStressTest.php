<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class ChallengerProductSearchStressTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin();
    }

    /**
     * Requirement: Unauthenticated users and seller users cannot bypass route protection.
     */
    public function test_route_protection_unauthenticated_and_seller_users(): void
    {
        // 1. Unauthenticated guest accessing productos.index
        $guestResponse = $this->get(route('productos.index'));
        $guestResponse->assertRedirect(route('login'));
        $this->assertGuest();

        // 1b. Unauthenticated guest with query parameters
        $guestQueryResponse = $this->get(route('productos.index', ['q' => 'alfa', 'category_id' => 1]));
        $guestQueryResponse->assertRedirect(route('login'));

        // 2. Seller user accessing productos.index
        $seller = $this->createSeller();
        $sellerResponse = $this->actingAs($seller)->get(route('productos.index'));
        $sellerResponse->assertRedirect(route('site.catalog'));

        // 2b. Seller user with query parameters
        $sellerQueryResponse = $this->actingAs($seller)->get(route('productos.index', ['q' => 'alfa', 'category_id' => 1]));
        $sellerQueryResponse->assertRedirect(route('site.catalog'));
    }

    /**
     * Requirement: Admin accessing route('productos.index') with multiple pages of results:
     * verify that page 2 link contains both q=... and category_id=...
     */
    public function test_admin_pagination_links_contain_both_q_and_category_id(): void
    {
        $cat = $this->createCategory(['name' => 'Empaquetaduras']);

        // Create 25 matching products to generate 3 pages (10 per page)
        for ($i = 1; $i <= 25; $i++) {
            $this->createProduct($cat, [
                'name' => sprintf('Empaque Valvula %02d', $i),
                'reference' => sprintf('EMP-%03d', $i),
            ]);
        }

        // Query page 1 with both filters
        $response = $this->actingAs($this->admin)->get(route('productos.index', [
            'q' => 'Valvula',
            'category_id' => $cat->id,
        ]));

        $response->assertOk();

        /** @var LengthAwarePaginator $paginator */
        $paginator = $response->viewData('products');
        $this->assertEquals(25, $paginator->total());
        $this->assertEquals(3, $paginator->lastPage());
        $this->assertEquals(1, $paginator->currentPage());

        // Verify next page URL in paginator object
        $nextPageUrl = $paginator->nextPageUrl();
        $this->assertNotNull($nextPageUrl);
        $this->assertStringContainsString('page=2', $nextPageUrl);
        $this->assertStringContainsString('q=Valvula', $nextPageUrl);
        $this->assertStringContainsString('category_id=' . $cat->id, $nextPageUrl);

        // Verify HTML output contains page 2 link with preserved query params
        $content = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/href="[^"]*page=2[^"]*category_id=' . $cat->id . '[^"]*q=Valvula[^"]*"|href="[^"]*page=2[^"]*q=Valvula[^"]*category_id=' . $cat->id . '[^"]*"|href="[^"]*category_id=' . $cat->id . '[^"]*q=Valvula[^"]*page=2[^"]*"|href="[^"]*q=Valvula[^"]*category_id=' . $cat->id . '[^"]*page=2[^"]*"/',
            $content,
            'Page 2 pagination link in HTML must preserve both q and category_id query parameters.'
        );

        // Query page 2 directly and verify page 1 link also preserves both params
        $page2Response = $this->actingAs($this->admin)->get(route('productos.index', [
            'q' => 'Valvula',
            'category_id' => $cat->id,
            'page' => 2,
        ]));

        $page2Response->assertOk();
        $page2Content = $page2Response->getContent();
        $this->assertMatchesRegularExpression(
            '/href="[^"]*page=1[^"]*category_id=' . $cat->id . '[^"]*q=Valvula[^"]*"|href="[^"]*page=1[^"]*q=Valvula[^"]*category_id=' . $cat->id . '[^"]*"|href="[^"]*category_id=' . $cat->id . '[^"]*q=Valvula[^"]*page=1[^"]*"|href="[^"]*q=Valvula[^"]*category_id=' . $cat->id . '[^"]*page=1[^"]*"/',
            $page2Content,
            'Page 1 pagination link in HTML on page 2 must preserve both q and category_id query parameters.'
        );
    }

    /**
     * Requirement: Admin accessing route('clientes.index') with search query q=test:
     * verify HTTP 200, search results returned, and verify HTML output does NOT contain <select name="category_id">.
     */
    public function test_client_index_search_returns_200_and_has_no_category_select(): void
    {
        $matchClient = $this->createClient([
            'name' => 'Transportes Test S.A.S.',
            'phone' => '3110001122',
            'city' => 'Medellin',
        ]);
        $otherClient = $this->createClient([
            'name' => 'Distribuciones Bogota Ltda.',
            'phone' => '3229998877',
            'city' => 'Bogota',
        ]);

        $response = $this->actingAs($this->admin)->get(route('clientes.index', ['q' => 'Test']));

        $response->assertOk();

        // Verify search results
        $response->assertSee('Transportes Test S.A.S.');
        $response->assertDontSee('Distribuciones Bogota Ltda.');

        // Verify HTML does NOT contain category select element or options
        $response->assertDontSee('<select name="category_id"', false);
        $response->assertDontSee('name="category_id"', false);
        $response->assertDontSee('Todas las categorías');
    }

    /**
     * Requirement: Admin accessing route('productos.index') with selected category_id:
     * verify HTML contains <option value="..." selected>.
     */
    public function test_product_index_renders_selected_category_option(): void
    {
        $cat1 = $this->createCategory(['name' => 'Bujes']);
        $cat2 = $this->createCategory(['name' => 'Soportes']);

        $response = $this->actingAs($this->admin)->get(route('productos.index', [
            'category_id' => $cat2->id,
        ]));

        $response->assertOk();

        $content = $response->getContent();
        // Assert selected option in HTML
        $expectedOptionSnippet = 'value="' . $cat2->id . '" selected';
        $this->assertStringContainsString($expectedOptionSnippet, $content);

        // Ensure cat1 is NOT selected
        $unselectedSnippet = 'value="' . $cat1->id . '" selected';
        $this->assertStringNotContainsString($unselectedSnippet, $content);
    }

    /**
     * Requirement: Verify onchange="this.form.submit()" attribute is present in HTML of product search.
     */
    public function test_onchange_attribute_present_in_product_search_html(): void
    {
        $this->createCategory(['name' => 'Retenedores']);

        $response = $this->actingAs($this->admin)->get(route('productos.index'));

        $response->assertOk();
        $response->assertSee('onchange="this.form.submit()"', false);

        // Specifically check the <select> tag has this attribute
        $content = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<select[^>]*name="category_id"[^>]*onchange="this\.form\.submit\(\)"|<select[^>]*onchange="this\.form\.submit\(\)"[^>]*name="category_id"/',
            $content
        );
    }

    /**
     * Requirement: Verify "Limpiar" button link points to current URL without query parameters.
     */
    public function test_limpiar_button_points_to_current_url_without_query_parameters(): void
    {
        $cat = $this->createCategory(['name' => 'Fuelles']);

        // 1. In productos.index
        $prodResponse = $this->actingAs($this->admin)->get(route('productos.index', [
            'q' => 'Fuelle Delantero',
            'category_id' => $cat->id,
        ]));

        $prodResponse->assertOk();
        $prodContent = $prodResponse->getContent();

        // Expected URL for "Limpiar" button is route('productos.index') without query parameters
        $cleanProductUrl = route('productos.index');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="' . preg_quote($cleanProductUrl, '/') . '"[^>]*class="[^"]*btn-secondary[^"]*"[^>]*>\s*Limpiar\s*<\/a>/',
            $prodContent,
            'Limpiar button in productos.index must link to the clean route URL without query parameters.'
        );

        // 2. In clientes.index
        $clientResponse = $this->actingAs($this->admin)->get(route('clientes.index', [
            'q' => 'FiltroCliente',
        ]));

        $clientResponse->assertOk();
        $clientContent = $clientResponse->getContent();

        $cleanClientUrl = route('clientes.index');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="' . preg_quote($cleanClientUrl, '/') . '"[^>]*class="[^"]*btn-secondary[^"]*"[^>]*>\s*Limpiar\s*<\/a>/',
            $clientContent,
            'Limpiar button in clientes.index must link to the clean route URL without query parameters.'
        );
    }

    /**
     * Adversarial Stress-Test: Special search characters and non-existent category_id
     */
    public function test_adversarial_inputs_and_edge_cases(): void
    {
        $cat = $this->createCategory(['name' => 'Tornillos & Tuercas']);
        $prod = $this->createProduct($cat, [
            'name' => 'Tornillo 100% Goma',
            'reference' => 'REF_100%',
            'description' => "Pieza con comillas 'especiales' y caracteres #1",
        ]);

        // Percent sign search
        $responsePercent = $this->actingAs($this->admin)->get(route('productos.index', ['q' => '%']));
        $responsePercent->assertOk();

        // SQL injection probe in q
        $responseInjection = $this->actingAs($this->admin)->get(route('productos.index', ['q' => "' OR '1'='1"]));
        $responseInjection->assertOk();

        // Non-existent category_id
        $responseNonExistent = $this->actingAs($this->admin)->get(route('productos.index', ['category_id' => 999999]));
        $responseNonExistent->assertOk();
        $responseNonExistent->assertSee('No hay productos registrados.');

        // Empty category_id string (Todas las categorias)
        $responseAll = $this->actingAs($this->admin)->get(route('productos.index', ['category_id' => '']));
        $responseAll->assertOk();
        $responseAll->assertSee('Tornillo 100% Goma');
    }
}
