<?php

namespace Tests\Feature;

use App\Models\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class SiteCatalogTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    /** Caso positivo: el catálogo carga y muestra los productos activos */
    public function test_el_catalogo_carga_y_muestra_productos_activos(): void
    {
        $product = $this->createProduct();
        $this->createProduct(null, ['is_active' => false]);

        $response = $this->get(route('site.catalog'));

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->count() === 1 && $products->first()->id === $product->id);
        $response->assertViewHas('categories');
    }

    /** Caso positivo: filtra por categoría */
    public function test_filtra_productos_por_categoria(): void
    {
        $llantas = $this->createCategory(['name' => 'Llantas']);
        $aceites = $this->createCategory(['name' => 'Aceites']);

        $llanta = $this->createProduct($llantas, ['name' => 'Llanta A']);
        $this->createProduct($aceites, ['name' => 'Aceite B']);

        $response = $this->get(route('site.catalog', ['categoria' => $llantas->id]));

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->contains('id', $llanta->id) && ! $products->contains('name', 'Aceite B'));
        $response->assertViewHas('selectedCategory', fn ($c) => $c && $c->id === $llantas->id);
    }

    /** Caso positivo: filtra por ruta SEO de categoría y marca visualmente la categoría seleccionada en lugar de Todos */
    public function test_filtra_por_ruta_seo_de_categoria_y_selecciona_la_categoria_activa(): void
    {
        $llantas = $this->createCategory(['name' => 'Llantas de Moto']);
        $aceites = $this->createCategory(['name' => 'Aceites']);

        $llanta = $this->createProduct($llantas, ['name' => 'Llanta A']);
        $this->createProduct($aceites, ['name' => 'Aceite B']);

        $response = $this->get(route('site.catalog.category', [
            'categoriaId' => $llantas->id,
            'slug' => Str::slug($llantas->name),
        ]));

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->contains('id', $llanta->id) && ! $products->contains('name', 'Aceite B'));
        $response->assertViewHas('selectedCategory', fn ($c) => $c && $c->id === $llantas->id);

        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        // La tarjeta "Todos" no debe tener la clase border-danger cuando hay una categoría seleccionada
        $todosCard = $xpath->query("//h5[normalize-space()='Todos']/ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' card ')][1]")->item(0);
        $this->assertNotNull($todosCard);
        $this->assertStringNotContainsString('border-danger', $todosCard->getAttribute('class'));

        // La tarjeta de la categoría seleccionada sí debe tener border-danger
        $selectedCard = $xpath->query("//h5[normalize-space()='Llantas de Moto']/ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' card ')][1]")->item(0);
        $this->assertNotNull($selectedCard);
        $this->assertStringContainsString('border-danger', $selectedCard->getAttribute('class'));

        // Otra categoría no seleccionada no debe tener border-danger
        $otherCard = $xpath->query("//h5[normalize-space()='Aceites']/ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' card ')][1]")->item(0);
        $this->assertNotNull($otherCard);
        $this->assertStringNotContainsString('border-danger', $otherCard->getAttribute('class'));
    }

    /** Caso positivo: página de inicio con productos destacados */
    public function test_la_pagina_de_inicio_muestra_productos_destacados(): void
    {
        $product = $this->createProduct(null, ['name' => 'Llanta Destacada']);

        $response = $this->get(route('site.home'));

        $response->assertOk();
        $this->assertInstanceOf(View::class, $response->original);
        $this->assertTrue($response->original->featured->contains('id', $product->id));
    }

    /** Caso positivo: recibe catálogos activos para la vista */
    public function test_el_catalogo_recibe_catalogos_activos(): void
    {
        Catalog::create(['name' => 'Catálogo Activo', 'is_active' => true]);
        Catalog::create(['name' => 'Catálogo Inactivo', 'is_active' => false]);

        $this->get(route('site.catalog'))
            ->assertOk()
            ->assertViewHas('activeCatalogs', fn ($catalogs) => $catalogs->count() === 1);
    }

    /** Caso límite: sin productos la página no falla */
    public function test_el_catalogo_vacio_no_falla(): void
    {
        $this->get(route('site.catalog'))->assertOk();
        $this->get(route('site.home'))->assertOk();
    }

    /** Caso límite: categoría inexistente en la query no rompe la página */
    public function test_categoria_inexistente_en_query_no_rompe_la_pagina(): void
    {
        $this->createProduct();

        $this->get(route('site.catalog', ['categoria' => 9999]))->assertOk();
    }

    /** Caso positivo: todas las etiquetas <img> en las vistas de la página web pública tienen loading="lazy" */
    public function test_todas_las_imagenes_de_la_pagina_web_publica_tienen_loading_lazy(): void
    {
        $category = $this->createCategory(['name' => 'Llantas']);

        // Producto con 1 sola imagen (cubre rama @elseif (count($urls) === 1))
        $singleImgProduct = $this->createProduct($category, [
            'name' => 'Producto Una Imagen',
            'image_path' => 'products/single.jpg',
        ]);

        // Producto con múltiples imágenes (cubre rama @if (count($urls) > 1))
        $multiImgProduct = $this->createProduct($category, [
            'name' => 'Producto Galeria',
            'image_path' => 'products/main.jpg',
        ]);
        $multiImgProduct->images()->createMany([
            ['path' => 'products/gal1.jpg', 'sort_order' => 1],
            ['path' => 'products/gal2.jpg', 'sort_order' => 2],
        ]);

        $publicUrls = [
            route('site.home'),
            route('site.catalog'),
            route('site.product', ['id' => $singleImgProduct->id, 'slug' => 'producto-una-imagen']),
            route('site.product', ['id' => $multiImgProduct->id, 'slug' => 'producto-galeria']),
        ];

        foreach ($publicUrls as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertAllImagesHaveLazyLoading($response->getContent(), $url);
        }

        // Vista pública de pedido de vendedor (/vendedor/pedido)
        $seller = $this->createSeller();
        $this->actingAs($seller)
            ->withSession(['seller_cart' => [$singleImgProduct->id => 2]])
            ->get(route('site.seller.order'))
            ->assertOk()
            ->tap(fn ($res) => $this->assertAllImagesHaveLazyLoading($res->getContent(), route('site.seller.order')));
    }

    private function assertAllImagesHaveLazyLoading(string $html, string $contextUrl): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $images = $xpath->query('//img');

        $this->assertGreaterThan(0, $images->length, "Se esperaba al menos una etiqueta <img> en {$contextUrl}");

        foreach ($images as $img) {
            /** @var \DOMElement $img */
            $src = $img->getAttribute('src');
            $this->assertSame(
                'lazy',
                $img->getAttribute('loading'),
                "La imagen con src '{$src}' en {$contextUrl} no tiene loading=\"lazy\"."
            );
        }
    }
}
