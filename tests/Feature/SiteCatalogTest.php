<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class SiteCatalogTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

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
        $response->assertViewHas('selectedCategory', fn($c) => $c && $c->id === $llantas->id);
    }

    /** Caso positivo: página de inicio con productos destacados */
    public function test_la_pagina_de_inicio_muestra_productos_destacados(): void
    {
        $product = $this->createProduct(null, ['name' => 'Llanta Destacada']);

        $response = $this->get(route('site.home'));

        $response->assertOk();
        $this->assertInstanceOf(\Illuminate\View\View::class, $response->original);
        $this->assertTrue($response->original->featured->contains('id', $product->id));
    }

    /** Caso positivo: recibe catálogos activos para la vista */
    public function test_el_catalogo_recibe_catalogos_activos(): void
    {
        Catalog::create(['name' => 'Catálogo Activo', 'is_active' => true]);
        Catalog::create(['name' => 'Catálogo Inactivo', 'is_active' => false]);

        $this->get(route('site.catalog'))
            ->assertOk()
            ->assertViewHas('activeCatalogs', fn($catalogs) => $catalogs->count() === 1);
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
}

