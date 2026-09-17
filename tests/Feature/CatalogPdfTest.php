<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class CatalogPdfTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

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
}
