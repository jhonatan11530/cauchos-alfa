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

class AdminProductSearchTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin();
    }

    /** Caso negativo: invitado no accede al index de productos */
    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('productos.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** Caso negativo: vendedor es redirigido al catálogo público */
    public function test_vendedor_es_redirigido_al_catalogo(): void
    {
        $seller = $this->createSeller();

        $this->actingAs($seller)
            ->get(route('productos.index'))
            ->assertRedirect(route('site.catalog'));
    }

    /** Caso positivo: admin recibe categorías ordenadas alfabéticamente y vista renderiza el selector */
    public function test_admin_recibe_categorias_ordenadas_y_vista_renderiza_select(): void
    {
        $this->createCategory(['name' => 'Soportes']);
        $this->createCategory(['name' => 'Bujes']);
        $this->createCategory(['name' => 'Abrazaderas']);

        $response = $this->actingAs($this->admin)->get(route('productos.index'));

        $response->assertOk();
        $response->assertViewHas('categories', function ($categories) {
            return $categories->pluck('name')->all() === ['Abrazaderas', 'Bujes', 'Soportes'];
        });

        $response->assertSee('name="category_id"', false);
        $response->assertSee('Todas las categorías');
        $response->assertSee('onchange="this.form.submit()"', false);
    }

    /** Caso positivo: filtra productos por nombre en q */
    public function test_filtrado_por_nombre_q(): void
    {
        $cat = $this->createCategory(['name' => 'General']);
        $prod1 = $this->createProduct($cat, ['name' => 'Buje Tijera Delantero', 'reference' => 'REF-001']);
        $prod2 = $this->createProduct($cat, ['name' => 'Soporte Motor Derecho', 'reference' => 'REF-002']);

        $response = $this->actingAs($this->admin)->get(route('productos.index', ['q' => 'Tijera']));

        $response->assertOk();
        $response->assertViewHas('products', function (LengthAwarePaginator $products) use ($prod1, $prod2) {
            return $products->contains('id', $prod1->id) && ! $products->contains('id', $prod2->id);
        });

        $response->assertSee('value="Tijera"', false);
    }

    /** Caso positivo: filtra productos por referencia o descripción en q */
    public function test_filtrado_por_referencia_o_descripcion_en_q(): void
    {
        $cat = $this->createCategory(['name' => 'General']);
        $prod1 = $this->createProduct($cat, [
            'name' => 'Pieza Especial',
            'reference' => 'SPECIAL-REF-99',
            'description' => 'Caucho reforzado',
        ]);
        $prod2 = $this->createProduct($cat, [
            'name' => 'Pieza Estándar',
            'reference' => 'STD-01',
            'description' => 'Normal',
        ]);

        $response = $this->actingAs($this->admin)->get(route('productos.index', ['q' => 'SPECIAL-REF']));

        $response->assertOk();
        $response->assertViewHas('products', fn ($p) => $p->contains('id', $prod1->id) && ! $p->contains('id', $prod2->id));

        $responseDesc = $this->actingAs($this->admin)->get(route('productos.index', ['q' => 'reforzado']));
        $responseDesc->assertOk();
        $responseDesc->assertViewHas('products', fn ($p) => $p->contains('id', $prod1->id) && ! $p->contains('id', $prod2->id));
    }

    /** Caso positivo: filtra productos cuando q coincide con el nombre de la categoría relacionada */
    public function test_filtrado_por_nombre_de_categoria_relacionada_en_q(): void
    {
        $catAmort = $this->createCategory(['name' => 'Amortiguación']);
        $catFrenos = $this->createCategory(['name' => 'Frenos']);

        $prodAmort = $this->createProduct($catAmort, ['name' => 'Tope de Goma']);
        $prodFrenos = $this->createProduct($catFrenos, ['name' => 'Pastilla de Goma']);

        $response = $this->actingAs($this->admin)->get(route('productos.index', ['q' => 'Amortiguación']));

        $response->assertOk();
        $response->assertViewHas('products', fn ($p) => $p->contains('id', $prodAmort->id) && ! $p->contains('id', $prodFrenos->id));
    }

    /** Caso positivo: filtra productos por categoría seleccionada */
    public function test_filtrado_por_categoria_id(): void
    {
        $catLlantas = $this->createCategory(['name' => 'Llantas']);
        $catBujes = $this->createCategory(['name' => 'Bujes']);

        $prodLlantas1 = $this->createProduct($catLlantas, ['name' => 'Llanta Radial']);
        $prodLlantas2 = $this->createProduct($catLlantas, ['name' => 'Llanta Convencional']);
        $prodBuje = $this->createProduct($catBujes, ['name' => 'Buje Central']);

        $response = $this->actingAs($this->admin)->get(route('productos.index', ['category_id' => $catLlantas->id]));

        $response->assertOk();
        $response->assertViewHas('products', function (LengthAwarePaginator $products) use ($prodLlantas1, $prodLlantas2, $prodBuje) {
            return $products->contains('id', $prodLlantas1->id)
                && $products->contains('id', $prodLlantas2->id)
                && ! $products->contains('id', $prodBuje->id);
        });

        $response->assertSee('value="' . $catLlantas->id . '" selected', false);
    }

    /** Caso límite: category_id vacío (Todas las categorías) devuelve todos los productos */
    public function test_filtrado_con_todas_las_categorias_retorna_todos(): void
    {
        $cat1 = $this->createCategory(['name' => 'Cat 1']);
        $cat2 = $this->createCategory(['name' => 'Cat 2']);
        $prod1 = $this->createProduct($cat1, ['name' => 'Prod 1']);
        $prod2 = $this->createProduct($cat2, ['name' => 'Prod 2']);

        $response = $this->actingAs($this->admin)->get(route('productos.index', ['category_id' => '']));

        $response->assertOk();
        $response->assertViewHas('products', fn ($p) => $p->contains('id', $prod1->id) && $p->contains('id', $prod2->id));
    }

    /** Caso positivo: filtra combinando texto q y category_id */
    public function test_filtrado_combinado_q_y_categoria_id(): void
    {
        $catA = $this->createCategory(['name' => 'Soportes']);
        $catB = $this->createCategory(['name' => 'Bujes']);

        $matchBoth = $this->createProduct($catA, ['name' => 'Soporte Amortiguador']);
        $matchOnlyCategory = $this->createProduct($catA, ['name' => 'Soporte Motor']);
        $matchOnlyText = $this->createProduct($catB, ['name' => 'Buje Amortiguador']);
        $matchNeither = $this->createProduct($catB, ['name' => 'Buje Barra']);

        $response = $this->actingAs($this->admin)->get(route('productos.index', [
            'q' => 'Amortiguador',
            'category_id' => $catA->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('products', function (LengthAwarePaginator $products) use ($matchBoth, $matchOnlyCategory, $matchOnlyText, $matchNeither) {
            return $products->contains('id', $matchBoth->id)
                && ! $products->contains('id', $matchOnlyCategory->id)
                && ! $products->contains('id', $matchOnlyText->id)
                && ! $products->contains('id', $matchNeither->id);
        });

        $response->assertSee('value="Amortiguador"', false);
        $response->assertSee('value="' . $catA->id . '" selected', false);
    }

    /** Caso positivo: la paginación conserva los parámetros de búsqueda mediante withQueryString() */
    public function test_paginacion_conserva_parametros_de_busqueda_con_with_query_string(): void
    {
        $cat = $this->createCategory(['name' => 'Llantas']);

        // Creamos 15 productos que coincidan para forzar 2 páginas (10 por página)
        for ($i = 1; $i <= 15; $i++) {
            $this->createProduct($cat, ['name' => sprintf('Llanta Especial %02d', $i)]);
        }

        $response = $this->actingAs($this->admin)->get(route('productos.index', [
            'q' => 'Especial',
            'category_id' => $cat->id,
        ]));

        $response->assertOk();

        /** @var LengthAwarePaginator $products */
        $products = $response->viewData('products');
        $this->assertEquals(15, $products->total());
        $this->assertEquals(2, $products->lastPage());

        $nextPageUrl = $products->nextPageUrl();
        $this->assertNotNull($nextPageUrl);
        $this->assertStringContainsString('q=Especial', $nextPageUrl);
        $this->assertStringContainsString('category_id=' . $cat->id, $nextPageUrl);
        $this->assertStringContainsString('page=2', $nextPageUrl);

        // Verifica que en el HTML renderizado el enlace de la página 2 tenga los parámetros
        $response->assertSee('q=Especial', false);
        $response->assertSee('category_id=' . $cat->id, false);
    }

    /** Caso de compatibilidad regresiva: la vista de clientes no falla y no muestra el selector */
    public function test_vista_de_clientes_no_se_rompe_y_no_muestra_selector_de_categorias(): void
    {
        $client1 = $this->createClient(['name' => 'Carlos Gomez', 'document' => '12345']);
        $client2 = $this->createClient(['name' => 'Maria Rodriguez', 'document' => '67890']);

        $response = $this->actingAs($this->admin)->get(route('clientes.index', ['q' => 'Carlos']));

        $response->assertOk();
        $response->assertViewHas('clients', fn ($c) => $c->contains('id', $client1->id) && ! $c->contains('id', $client2->id));

        // No debe mostrar el selector de categoría en la vista de clientes
        $response->assertDontSee('name="category_id"', false);
        $response->assertDontSee('Todas las categorías');
    }
}
