<?php

namespace Tests\Feature;

use App\Models\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class CatalogPdfTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    /** Caso positivo: el administrador puede exportar el catálogo a PDF */
    public function test_admin_puede_descargar_catalogo_en_pdf(): void
    {
        $admin = $this->createAdmin();
        $catalog = Catalog::create([
            'name' => 'Catálogo General 2026',
            'company_name' => 'Cauchos Alfa',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('catalogos.pdf', $catalog));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /** Caso positivo: cualquier usuario puede consultar el PDF público */
    public function test_usuario_puede_descargar_catalogo_publico_en_pdf(): void
    {
        $catalog = Catalog::create([
            'name' => 'Catálogo Público',
            'is_active' => true,
        ]);

        $response = $this->get(route('catalogos.public-pdf', $catalog));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    /** Caso positivo: el PDF se renderiza con productos organizados por categoría y con imágenes */
    public function test_catalogo_en_pdf_procesa_productos_con_imagenes_y_categorias(): void
    {
        Storage::fake('public');

        // Crear archivo dummy para la imagen
        Storage::disk('public')->put('products/calapie-viva.jpg', 'fake-image-binary-data');

        $calapies = $this->createCategory(['name' => 'Calapiés']);
        $conectores = $this->createCategory(['name' => 'Conectores']);

        $producto1 = $this->createProduct($calapies, [
            'name' => 'Calapié Trasero Viva 115',
            'reference' => 'CAL-001',
            'image_path' => 'products/calapie-viva.jpg',
            'description' => 'Fabricado en caucho de alta densidad',
        ]);

        $producto2 = $this->createProduct($conectores, [
            'name' => 'Conector Filtro Pulsar',
            'reference' => 'CON-002',
            'description' => 'Resistente a vibraciones y calor',
        ]);

        // Agregar una imagen en product_images para el producto 2
        Storage::disk('public')->put('products/conector-pulsar.png', 'fake-png-binary-data');
        $producto2->images()->create([
            'path' => 'products/conector-pulsar.png',
            'sort_order' => 1,
        ]);

        $catalog = Catalog::create([
            'name' => 'Catálogo Motos 2026',
            'company_name' => 'Cauchos Alfa',
            'contact_phone' => '304 3210',
            'contact_email' => 'ventas@cauchosalfa.com',
            'notes' => 'Precios sujetos a cambio sin previo aviso.',
            'is_active' => true,
        ]);

        $catalog->products()->attach([
            $producto1->id => ['sort_order' => 1],
            $producto2->id => ['sort_order' => 2],
        ]);

        $response = $this->get(route('catalogos.public-pdf', $catalog));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    /** Caso límite: catálogo vacío genera PDF sin fallos */
    public function test_catalogo_vacio_genera_pdf_sin_errores(): void
    {
        $catalog = Catalog::create([
            'name' => 'Catálogo Vacío',
            'is_active' => true,
        ]);

        $response = $this->get(route('catalogos.public-pdf', $catalog));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    /** Caso especial: producto con imagen webp no debe incluir webp en el PDF */
    public function test_catalogo_con_imagen_webp_no_rompe_generacion_de_pdf(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/test-image.webp', 'fake-webp-data');

        $category = $this->createCategory(['name' => 'Repuestos']);
        $product = $this->createProduct($category, [
            'name' => 'Producto Webp',
            'image_path' => 'products/test-image.webp',
        ]);

        // Debe ignorar terminantemente la imagen WebP
        $this->assertNull($product->primaryImageDataUri());

        $catalog = Catalog::create(['name' => 'Catálogo Webp', 'is_active' => true]);
        $catalog->products()->attach($product->id, ['sort_order' => 1]);

        $response = $this->get(route('catalogos.public-pdf', $catalog));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    /** Caso real: prueba con los archivos de imagen existentes en public/storage */
    public function test_catalogo_con_imagenes_fisicas_existentes(): void
    {
        $category = $this->createCategory(['name' => 'Productos Reales']);
        $productJpg = $this->createProduct($category, [
            'name' => 'Producto con JPG real',
            'reference' => 'JPG-001',
            'image_path' => 'products/8MZlkK8agOblr9wq7CjPtGRotSOp2f2DRqXpGH1E.jpg',
        ]);

        $productWebp = $this->createProduct($category, [
            'name' => 'Producto con WEBP real',
            'reference' => 'WEBP-001',
            'image_path' => 'products/HYI50EGrUvOxP6LVwiSF0838KVwqkkMJB74qDBdY.webp',
        ]);

        $catalog = Catalog::create([
            'name' => 'Catálogo Completo Real',
            'company_name' => 'Cauchos Alfa',
            'is_active' => true,
        ]);

        $catalog->products()->attach([
            $productJpg->id => ['sort_order' => 1],
            $productWebp->id => ['sort_order' => 2],
        ]);

        $response = $this->get(route('catalogos.public-pdf', $catalog));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_r1_obsolete_templates_removed_and_legacy_rows_fallback_safely_to_default(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/admin/catalogs/templates/classic.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/admin/catalogs/templates/modern.blade.php'));
        $this->assertFileExists(resource_path('views/admin/catalogs/templates/default.blade.php'));
        $this->assertFileExists(resource_path('views/admin/catalogs/templates/catalogo-web.blade.php'));

        $admin = $this->createAdmin();
        $category = $this->createCategory(['name' => 'Llantas']);
        $product = $this->createProduct($category, ['name' => 'LLANTA_LEGACY_01']);

        foreach (['modern', 'classic'] as $legacyTemplate) {
            $catalog = Catalog::create([
                'name' => 'Legacy '.$legacyTemplate,
                'template' => $legacyTemplate,
                'is_active' => true,
            ]);
            $catalog->products()->attach($product->id, ['sort_order' => 1]);

            $this->assertSame('admin.catalogs.templates.default', $catalog->resolveTemplateView());

            $adminPdf = $this->actingAs($admin)->get(route('catalogos.pdf', $catalog));
            $adminPdf->assertOk();
            $this->assertStringContainsString('application/pdf', $adminPdf->headers->get('Content-Type') ?? '');
            $this->assertStringStartsWith('%PDF-', $adminPdf->getContent());

            $publicPdf = $this->get(route('catalogos.public-pdf', $catalog));
            $publicPdf->assertOk();
            $this->assertStringContainsString('application/pdf', $publicPdf->headers->get('Content-Type') ?? '');
            $this->assertStringStartsWith('%PDF-', $publicPdf->getContent());

            $editPage = $this->actingAs($admin)->get(route('catalogos.edit', $catalog));
            $editPage->assertOk();
            $editPage->assertDontSee('value="modern"', false);
            $editPage->assertDontSee('value="classic"', false);
            $editPage->assertSee('<option value="default" selected>', false);
        }
    }

    public function test_r2_both_templates_paginate_to_exactly_9_products_per_full_page(): void
    {
        $admin = $this->createAdmin();
        $catA = $this->createCategory(['name' => 'CatAlpha']);
        $catB = $this->createCategory(['name' => 'CatBeta']);
        $catC = $this->createCategory(['name' => 'CatGamma']);
        $categories = [$catA, $catB, $catC];

        foreach (['default', 'catalogo-web'] as $template) {
            foreach ([10, 18, 19] as $totalProducts) {
                $products = collect();
                for ($i = 1; $i <= $totalProducts; $i++) {
                    $products->push($this->createProduct($categories[($i - 1) % 3], [
                        'name' => sprintf('PAG_%s_%02d_P%02d', strtoupper(substr($template, 0, 3)), $totalProducts, $i),
                        'reference' => sprintf('REF-%02d-%02d', $totalProducts, $i),
                    ]));
                }

                $catalog = Catalog::create([
                    'name' => "Paginacion {$template} {$totalProducts}",
                    'company_name' => 'Cauchos Alfa',
                    'notes' => 'Condiciones comerciales vigentes.',
                    'template' => $template,
                    'is_active' => true,
                ]);

                $sync = [];
                foreach ($products as $idx => $p) {
                    $sync[$p->id] = ['sort_order' => $idx + 1];
                }
                $catalog->products()->sync($sync);

                // Verify HTML chunking structure
                $html = view($catalog->resolveTemplateView(), [
                    'catalog' => $catalog,
                    'orderedProducts' => $catalog->orderedProductsForPdf(),
                    'productsByCategory' => $catalog->groupedOrderedProductsForPdf(),
                ])->render();

                $expectedProductPages = (int) ceil($totalProducts / 9);
                $pageClass = $template === 'default' ? 'pdf-page' : 'page';
                $dom = new \DOMDocument;
                @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
                $xpath = new \DOMXPath($dom);
                $pageNodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$pageClass} ')]");
                $this->assertSame($expectedProductPages, $pageNodes->length, "HTML page count mismatch for {$template} N={$totalProducts}");

                foreach ($pageNodes as $pageIdx => $node) {
                    $cardNodes = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' product-card ')]", $node);
                    $expectedOnPage = ($pageIdx < $expectedProductPages - 1)
                        ? 9
                        : (($totalProducts % 9) === 0 ? 9 : ($totalProducts % 9));
                    $this->assertSame($expectedOnPage, $cardNodes->length, 'HTML card count mismatch on page '.($pageIdx + 1)." for {$template} N={$totalProducts}");
                }

                // Verify real Dompdf PDF output & per-page stream content
                $response = $this->actingAs($admin)->get(route('catalogos.pdf', $catalog));
                $response->assertOk();
                $pdfBytes = $response->getContent();
                $this->assertStringStartsWith('%PDF-', $pdfBytes);

                $pdfPagesText = $this->extractPdfPagesText($pdfBytes);
                $expectedTotalPdfPages = $template === 'default'
                    ? $expectedProductPages
                    : 1 + $expectedProductPages + 1;
                $this->assertCount($expectedTotalPdfPages, $pdfPagesText, "PDF page count mismatch for {$template} N={$totalProducts}");

                $productPageOffset = $template === 'default' ? 0 : 1;
                $pattern = '/PAG_'.preg_quote(strtoupper(substr($template, 0, 3)), '/').'_'.sprintf('%02d', $totalProducts).'_P\d{2}/';

                for ($pIdx = 0; $pIdx < $expectedProductPages; $pIdx++) {
                    $streamText = $pdfPagesText[$productPageOffset + $pIdx];
                    preg_match_all($pattern, $streamText, $matches);
                    $uniqueFound = array_values(array_unique($matches[0]));
                    $expectedOnPage = ($pIdx < $expectedProductPages - 1)
                        ? 9
                        : (($totalProducts % 9) === 0 ? 9 : ($totalProducts % 9));
                    $this->assertCount($expectedOnPage, $uniqueFound, 'PDF stream product count mismatch on product page '.($pIdx + 1)." for {$template} N={$totalProducts}");
                }
            }
        }
    }

    public function test_r2_catalogo_web_empty_catalog_renders_cleanly_without_orphan_pages(): void
    {
        $catalog = Catalog::create([
            'name' => 'Web Vacío',
            'template' => 'catalogo-web',
            'is_active' => true,
        ]);

        $response = $this->get(route('catalogos.public-pdf', $catalog));
        $response->assertOk();
        $pdfPages = $this->extractPdfPagesText($response->getContent());
        $this->assertCount(2, $pdfPages);
    }

    public function test_r3_admin_can_specify_custom_category_and_product_order_on_create_edit_and_pdf(): void
    {
        $admin = $this->createAdmin();
        $catA = $this->createCategory(['name' => 'CatPrimeraAlfa']);
        $catB = $this->createCategory(['name' => 'CatSegundaBeta']);

        $prodA1 = $this->createProduct($catA, ['name' => 'PROD_A_UNO']);
        $prodA2 = $this->createProduct($catA, ['name' => 'PROD_A_DOS']);
        $prodB1 = $this->createProduct($catB, ['name' => 'PROD_B_UNO']);
        $prodB2 = $this->createProduct($catB, ['name' => 'PROD_B_DOS']);

        // Create with custom reversed category order [B, A] and reversed products [B2, B1, A2, A1]
        $createRes = $this->actingAs($admin)->post(route('catalogos.store'), [
            'name' => 'Catálogo Ordenado 2026',
            'template' => 'default',
            'is_active' => '1',
            'category_order' => [(string) $catB->id, (string) $catA->id],
            'products' => [$prodB2->id, $prodB1->id, $prodA2->id, $prodA1->id],
        ]);
        $createRes->assertRedirect(route('catalogos.index'));

        $catalog = Catalog::where('name', 'Catálogo Ordenado 2026')->firstOrFail();
        $this->assertSame([(string) $catB->id, (string) $catA->id], $catalog->category_order);
        $this->assertSame(
            [$prodB2->id, $prodB1->id, $prodA2->id, $prodA1->id],
            $catalog->orderedProductsForPdf()->pluck('id')->all()
        );

        // Verify edit page hydrates categories and products in saved order
        $editRes = $this->actingAs($admin)->get(route('catalogos.edit', $catalog));
        $editRes->assertOk();
        $editHtml = $editRes->getContent();
        $this->assertLessThan(strpos($editHtml, 'CatPrimeraAlfa'), strpos($editHtml, 'CatSegundaBeta'));
        $this->assertLessThan(strpos($editHtml, 'PROD_B_UNO'), strpos($editHtml, 'PROD_B_DOS'));
        $this->assertLessThan(strpos($editHtml, 'PROD_A_UNO'), strpos($editHtml, 'PROD_A_DOS'));

        // Verify show page reflects custom order
        $showRes = $this->actingAs($admin)->get(route('catalogos.show', $catalog));
        $showRes->assertOk();
        $showHtml = $showRes->getContent();
        $this->assertLessThan(strpos($showHtml, 'PROD_B_UNO'), strpos($showHtml, 'PROD_B_DOS'));
        $this->assertLessThan(strpos($showHtml, 'PROD_A_DOS'), strpos($showHtml, 'PROD_B_UNO'));

        // Verify default PDF stream preserves exact order
        $defaultPdfBytes = $this->actingAs($admin)->get(route('catalogos.pdf', $catalog))->getContent();
        $defaultPageText = $this->extractPdfPagesText($defaultPdfBytes)[0];
        $this->assertOrderedSubstrings($defaultPageText, ['PROD_B_DOS', 'PROD_B_UNO', 'PROD_A_DOS', 'PROD_A_UNO']);

        // Update order to [A, B] and [A1, A2, B1, B2] with template 'catalogo-web'
        $updateRes = $this->actingAs($admin)->put(route('catalogos.update', $catalog), [
            'name' => 'Catálogo Ordenado 2026',
            'template' => 'catalogo-web',
            'is_active' => '1',
            'category_order' => [(string) $catA->id, (string) $catB->id],
            'products' => [$prodA1->id, $prodA2->id, $prodB1->id, $prodB2->id],
        ]);
        $updateRes->assertRedirect(route('catalogos.index'));

        $catalog->refresh();
        $this->assertSame([(string) $catA->id, (string) $catB->id], $catalog->category_order);
        $this->assertSame(
            [$prodA1->id, $prodA2->id, $prodB1->id, $prodB2->id],
            $catalog->orderedProductsForPdf()->pluck('id')->all()
        );

        // Verify catalogo-web PDF stream preserves exact updated order (Page index 1 = first product page after cover)
        $webPdfBytes = $this->actingAs($admin)->get(route('catalogos.pdf', $catalog))->getContent();
        $webPages = $this->extractPdfPagesText($webPdfBytes);
        $this->assertCount(3, $webPages);
        $this->assertOrderedSubstrings($webPages[1], ['PROD_A_UNO', 'PROD_A_DOS', 'PROD_B_UNO', 'PROD_B_DOS']);
    }

    public function test_r3_uncategorized_products_and_interleaved_submission_respect_category_order(): void
    {
        $admin = $this->createAdmin();
        $cat = $this->createCategory(['name' => 'CatConNombre']);
        $uncatProd = $this->createProduct($cat, ['name' => 'PROD_SIN_CAT', 'category_id' => null]);
        $catProd1 = $this->createProduct($cat, ['name' => 'PROD_CAT_1']);
        $catProd2 = $this->createProduct($cat, ['name' => 'PROD_CAT_2']);

        $createRes = $this->actingAs($admin)->get(route('catalogos.create'));
        $createRes->assertOk();
        $createRes->assertSee('id="categories-sortable"', false);
        $createRes->assertSee('name="category_order[]"', false);
        $createRes->assertSee('move-category-up', false);
        $createRes->assertSee('move-product-up', false);

        // Interleaved products array with explicit category_order putting uncategorized first
        $storeRes = $this->actingAs($admin)->post(route('catalogos.store'), [
            'name' => 'Catálogo Mixto Ordenado',
            'template' => 'default',
            'is_active' => '1',
            'category_order' => ['uncategorized', (string) $cat->id],
            'products' => [$catProd2->id, $uncatProd->id, $catProd1->id],
        ]);
        $storeRes->assertRedirect(route('catalogos.index'));

        $catalog = Catalog::where('name', 'Catálogo Mixto Ordenado')->firstOrFail();
        $this->assertSame(['uncategorized', (string) $cat->id], $catalog->category_order);
        $this->assertSame(
            [$uncatProd->id, $catProd2->id, $catProd1->id],
            $catalog->orderedProductsForPdf()->pluck('id')->all()
        );

        $pdfBytes = $this->actingAs($admin)->get(route('catalogos.public-pdf', $catalog))->getContent();
        $pageText = $this->extractPdfPagesText($pdfBytes)[0];
        $this->assertOrderedSubstrings($pageText, ['PROD_SIN_CAT', 'PROD_CAT_2', 'PROD_CAT_1']);
    }

    public function test_r3_edit_validation_failure_with_zero_products_keeps_all_checkboxes_unchecked_and_preserves_category_order(): void
    {
        $admin = $this->createAdmin();
        $catOne = $this->createCategory(['name' => 'CatUno']);
        $catTwo = $this->createCategory(['name' => 'CatDos']);

        $prodOne = $this->createProduct($catOne, ['name' => 'PROD_SAVED_1']);
        $prodTwo = $this->createProduct($catTwo, ['name' => 'PROD_SAVED_2']);

        $catalog = Catalog::create([
            'name' => 'Catálogo Edit Validación',
            'template' => 'default',
            'category_order' => [(string) $catOne->id, (string) $catTwo->id],
            'is_active' => true,
        ]);
        $catalog->products()->attach([
            $prodOne->id => ['sort_order' => 1],
            $prodTwo->id => ['sort_order' => 2],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('catalogos.edit', $catalog))
            ->put(route('catalogos.update', $catalog), [
                'name' => 'Catálogo Edit Validación',
                'contact_email' => 'correo-invalido',
                'template' => 'default',
                'category_order' => [(string) $catTwo->id, (string) $catOne->id],
                // Intentionally omit 'products' (0 checkboxes checked)
            ]);

        $response->assertRedirect(route('catalogos.edit', $catalog));
        $response->assertSessionHasErrors('contact_email');

        $editPage = $this->actingAs($admin)->get(route('catalogos.edit', $catalog));
        $editPage->assertOk();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$editPage->getContent());
        $xpath = new \DOMXPath($dom);

        $productCheckboxes = $xpath->query("//input[contains(concat(' ', normalize-space(@class), ' '), ' product-checkbox ')]");
        $this->assertSame(2, $productCheckboxes->length);

        $checkedCheckboxes = $xpath->query("//input[contains(concat(' ', normalize-space(@class), ' '), ' product-checkbox ') and @checked]");
        $this->assertSame(0, $checkedCheckboxes->length, 'Expected 0 checked product checkboxes after edit validation redirect with 0 products.');

        $catOrderNodes = $xpath->query("//input[@name='category_order[]']");
        $renderedCatOrder = [];
        foreach ($catOrderNodes as $node) {
            if ($node instanceof \DOMElement) {
                $renderedCatOrder[] = $node->getAttribute('value');
            }
        }
        $this->assertSame([(string) $catTwo->id, (string) $catOne->id], $renderedCatOrder);
    }

    public function test_r2_default_template_n9_stress_with_long_header_categories_and_notes_fits_on_one_page(): void
    {
        $admin = $this->createAdmin();
        $products = [];
        for ($i = 1; $i <= 9; $i++) {
            $cat = $this->createCategory([
                'name' => "CATEGORIA_LARGA_NUMERO_{$i}_REPUESTOS_INDUSTRIALES_CAUCHOS",
            ]);
            $products[] = $this->createProduct($cat, [
                'name' => sprintf('STRESS_N9_P%02d_%s', $i, str_repeat('PRODUCTO_LARGO_', 3)),
                'reference' => sprintf('STR-N9-%02d', $i),
                'description' => 'Descripción comercial extendida para validar que la tarjeta no desborde la página.',
                'image_path' => ($i % 2 === 1) ? 'products/8MZlkK8agOblr9wq7CjPtGRotSOp2f2DRqXpGH1E.jpg' : null,
            ]);
        }

        $notes = 'Condiciones comerciales: precios sujetos a cambio sin previo aviso, entregas a nivel nacional en 48 horas. '
            .'Contamos con garantía de fábrica en toda nuestra línea de cauchos industriales y repuestos para motocicleta y bicicleta. '
            .'Pedidos al por mayor tienen descuento especial por volumen de compra mensual en Cali y Colombia.';

        $catalog = Catalog::create([
            'name' => 'Catálogo Empresarial Completo De Repuestos Y Cauchos Industriales 2026 Edicion Especial',
            'company_name' => 'CAUCHOS ALFA COLOMBIA DISTRIBUIDORA INDUSTRIAL SAS',
            'description' => 'Línea completa de repuestos industriales y cauchos para motos y bicicletas con cobertura nacional.',
            'contact_phone' => '+57 300 123 4567 / 326 2376 Ext 102',
            'contact_email' => 'distribuidora.industrial@cauchosalfacolombia.com',
            'contact_address' => 'Cra 39 No. 26 A 52 Barrio La Independencia Zona Industrial Sur - Santiago de Cali, Valle del Cauca',
            'notes' => $notes,
            'template' => 'default',
            'is_active' => true,
        ]);

        $sync = [];
        foreach ($products as $idx => $product) {
            $sync[$product->id] = ['sort_order' => $idx + 1];
        }
        $catalog->products()->sync($sync);

        $response = $this->actingAs($admin)->get(route('catalogos.pdf', $catalog));
        $response->assertOk();

        $pdfBytes = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfBytes);

        $pdfPages = $this->extractPdfPagesText($pdfBytes);
        $this->assertCount(1, $pdfPages, 'Stress N=9 default catalog spilled onto extra pages.');

        preg_match_all('/STRESS_N9_P\d{2}/', $pdfPages[0], $matches);
        $this->assertCount(9, array_values(array_unique($matches[0])));
        $this->assertStringContainsString('Notas / Condiciones Comerciales:', $pdfPages[0]);
    }

    public function test_inactive_products_are_rejected_on_store_update_and_excluded_from_catalog_views_and_pdfs(): void
    {
        $admin = $this->createAdmin();
        $seller = $this->createSeller();
        $category = $this->createCategory(['name' => 'Repuestos Activos']);

        $activeProd = $this->createProduct($category, ['name' => 'ACTIVE_PROD_OK', 'is_active' => true]);
        $inactiveProd = $this->createProduct($category, ['name' => 'INACTIVE_PROD_NO', 'is_active' => false]);
        $laterDeactivatedProd = $this->createProduct($category, ['name' => 'DEACTIVATED_PROD_LATER', 'is_active' => true]);

        // Reject inactive product on POST /catalogos
        $storeFail = $this->actingAs($admin)
            ->from(route('catalogos.create'))
            ->post(route('catalogos.store'), [
                'name' => 'Catálogo Rechaza Inactivo',
                'template' => 'default',
                'is_active' => '1',
                'products' => [$activeProd->id, $inactiveProd->id],
            ]);
        $storeFail->assertRedirect(route('catalogos.create'));
        $storeFail->assertSessionHasErrors('products.1');
        $this->assertSame(0, Catalog::count());

        // Create valid catalog with two active products
        $storeOk = $this->actingAs($admin)->post(route('catalogos.store'), [
            'name' => 'Catálogo Filtro Activos',
            'template' => 'default',
            'is_active' => '1',
            'category_order' => [(string) $category->id],
            'products' => [$activeProd->id, $laterDeactivatedProd->id],
        ]);
        $storeOk->assertRedirect(route('catalogos.index'));

        $catalog = Catalog::where('name', 'Catálogo Filtro Activos')->firstOrFail();

        // Reject inactive product on PUT /catalogos/{id}
        $updateFail = $this->actingAs($admin)
            ->from(route('catalogos.edit', $catalog))
            ->put(route('catalogos.update', $catalog), [
                'name' => 'Catálogo Filtro Activos',
                'template' => 'default',
                'is_active' => '1',
                'products' => [$activeProd->id, $inactiveProd->id],
            ]);
        $updateFail->assertRedirect(route('catalogos.edit', $catalog));
        $updateFail->assertSessionHasErrors('products.1');

        // Deactivate an already-attached product and ensure all read/PDF endpoints exclude it
        $laterDeactivatedProd->update(['is_active' => false]);
        $catalog->refresh();

        $this->assertSame([$activeProd->id], $catalog->orderedProductsForPdf()->pluck('id')->all());

        $showRes = $this->actingAs($admin)->get(route('catalogos.show', $catalog));
        $showRes->assertOk();
        $showRes->assertSee('ACTIVE_PROD_OK');
        $showRes->assertDontSee('DEACTIVATED_PROD_LATER');

        foreach ([
            $this->actingAs($admin)->get(route('catalogos.pdf', $catalog)),
            $this->get(route('catalogos.public-pdf', $catalog)),
            $this->actingAs($seller)->get(route('site.seller.catalog.latest')),
        ] as $pdfResponse) {
            $pdfResponse->assertOk();
            $pagesText = implode("\n", $this->extractPdfPagesText($pdfResponse->getContent()));
            $this->assertStringContainsString('ACTIVE_PROD_OK', $pagesText);
            $this->assertStringNotContainsString('DEACTIVATED_PROD_LATER', $pagesText);
        }
    }

    public function test_catalog_ordered_products_for_pdf_handles_malformed_non_scalar_category_order_safely(): void
    {
        $catA = $this->createCategory(['name' => 'CatMalformedA']);
        $catB = $this->createCategory(['name' => 'CatMalformedB']);
        $prodA = $this->createProduct($catA, ['name' => 'MALFORMED_A']);
        $prodB = $this->createProduct($catB, ['name' => 'MALFORMED_B']);

        $catalog = Catalog::create([
            'name' => 'Catálogo JSON Malformado',
            'template' => 'default',
            'is_active' => true,
        ]);
        $catalog->products()->sync([
            $prodA->id => ['sort_order' => 1],
            $prodB->id => ['sort_order' => 2],
        ]);

        Catalog::whereKey($catalog->id)->update([
            'category_order' => json_encode([['nested' => 'bad'], ['x'], (string) $catB->id, null]),
        ]);
        $catalog->refresh();

        $orderedIds = $catalog->orderedProductsForPdf()->pluck('id')->all();
        $this->assertSame([$prodB->id, $prodA->id], $orderedIds);
    }

    private function assertOrderedSubstrings(string $haystack, array $orderedNeedles): void
    {
        $lastPos = -1;
        foreach ($orderedNeedles as $needle) {
            $pos = strpos($haystack, $needle);
            $this->assertNotFalse($pos, "Missing expected token '{$needle}' in PDF text.");
            $this->assertGreaterThan($lastPos, $pos, "Token '{$needle}' appeared out of expected order.");
            $lastPos = $pos;
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractPdfPagesText(string $pdfBytes): array
    {
        preg_match_all('/(\d+)\s+0\s+obj\b(.*?)endobj/s', $pdfBytes, $objMatches, PREG_SET_ORDER);
        $objects = [];
        foreach ($objMatches as $m) {
            $objects[(int) $m[1]] = $m[2];
        }

        $decompressObj = function (string $body) use ($objects): ?string {
            if (! preg_match('/<<(.*?)>>\s*stream\r?\n/s', $body, $hm, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            $dict = $hm[1][0];
            $start = $hm[0][1] + strlen($hm[0][0]);
            $raw = null;
            if (preg_match('/\/Length\s+(\d+)(?:\s+0\s+R)?/', $dict, $lm)) {
                $lenVal = (int) $lm[1];
                if (str_contains($lm[0], '0 R') && isset($objects[$lenVal])) {
                    $lenVal = (int) trim($objects[$lenVal]);
                }
                $raw = substr($body, $start, $lenVal);
            } else {
                $endPos = strpos($body, 'endstream', $start);
                if ($endPos !== false) {
                    $raw = rtrim(substr($body, $start, $endPos - $start), "\r\n");
                }
            }
            if ($raw === null) {
                return null;
            }
            if (str_contains($dict, '/FlateDecode')) {
                $dec = @gzuncompress($raw);

                return $dec !== false ? $dec : null;
            }

            return $raw;
        };

        $pages = [];
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Page\b(?!s)/s', $body) && preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $body, $cm)) {
                $stream = $decompressObj($objects[(int) $cm[1]] ?? '') ?? '';
                $out = '';
                preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $stream, $strMatches);
                foreach ($strMatches[0] as $tok) {
                    $out .= $this->decodePdfLiteralString(substr($tok, 1, -1));
                }
                $pages[] = $out;
            }
        }

        return $pages;
    }

    private function decodePdfLiteralString(string $raw): string
    {
        $out = '';
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $next = $raw[$i + 1];
                if ($next >= '0' && $next <= '7') {
                    $oct = $next;
                    if ($i + 2 < $len && $raw[$i + 2] >= '0' && $raw[$i + 2] <= '7') {
                        $oct .= $raw[$i + 2];
                        if ($i + 3 < $len && $raw[$i + 3] >= '0' && $raw[$i + 3] <= '7') {
                            $oct .= $raw[$i + 3];
                            $i += 3;
                        } else {
                            $i += 2;
                        }
                    } else {
                        $i += 1;
                    }
                    $out .= chr(octdec($oct));
                } else {
                    $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0c", '(' => '(', ')' => ')', '\\' => '\\'];
                    $out .= $map[$next] ?? $next;
                    $i++;
                }
            } else {
                $out .= $ch;
            }
        }
        if (str_contains($out, "\x00")) {
            $out = @mb_convert_encoding($out, 'UTF-8', 'UTF-16BE') ?: str_replace("\x00", '', $out);
        }

        return $out;
    }
}
