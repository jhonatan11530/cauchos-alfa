<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);
        $sellerRole = Role::firstOrCreate(['slug' => 'vendedor'], ['name' => 'Vendedor']);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin User',
            'email' => 'admin_csv@cauchosalfa.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->seller = User::create([
            'role_id' => $sellerRole->id,
            'name' => 'Seller User',
            'seller_code' => 'V-999',
            'email' => 'seller_csv@cauchosalfa.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_download_client_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('import.template', ['tipo' => 'clientes']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Nombre;Documento;Email;Telefono;Direccion;Ciudad;Notas', $content);
        $this->assertStringContainsString('Taller El Ciclista', $content);
    }

    public function test_admin_can_download_product_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('import.template', ['tipo' => 'productos']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Nombre;Referencia;Categoria;Descripcion;Caracteristicas;Disponibilidad', $content);
        $this->assertStringContainsString('Llanta MTB 29 x 2.25', $content);
    }

    public function test_admin_can_import_clients_from_csv(): void
    {
        $csvContent = "\xEF\xBB\xBFNombre;Documento;Email;Telefono;Direccion;Ciudad;Notas\n".
            "Bicicletas Cali;900111222;bici@cali.com;3111111111;Calle 5 # 10-20;Cali;Cliente frecuente\n".
            "Moto Repuestos Sur;800333444;moto@sur.com;3222222222;Carrera 15 # 30-40;Palmira;Distribuidor\n";

        $file = UploadedFile::fake()->createWithContent('clientes.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('import.clients'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'name' => 'Bicicletas Cali',
            'document' => '900111222',
            'email' => 'bici@cali.com',
            'city' => 'Cali',
        ]);

        $this->assertDatabaseHas('clients', [
            'name' => 'Moto Repuestos Sur',
            'document' => '800333444',
            'city' => 'Palmira',
        ]);
    }

    public function test_import_clients_updates_existing_client_by_document(): void
    {
        Client::create([
            'name' => 'Nombre Antiguo',
            'document' => '900999888',
            'phone' => '1111111',
            'city' => 'Yumbo',
            'is_active' => true,
        ]);

        $csvContent = "Nombre;Documento;Email;Telefono;Direccion;Ciudad;Notas\n".
            "Nombre Actualizado;900999888;nuevo@email.com;3009999999;Nueva Direccion;Cali;Actualizado\n";

        $file = UploadedFile::fake()->createWithContent('clientes_update.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('import.clients'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'name' => 'Nombre Actualizado',
            'document' => '900999888',
            'email' => 'nuevo@email.com',
            'city' => 'Cali',
        ]);
        $this->assertDatabaseMissing('clients', [
            'name' => 'Nombre Antiguo',
        ]);
    }

    public function test_admin_can_import_products_and_auto_create_category(): void
    {
        $csvContent = "Nombre;Referencia;Categoria;Descripcion;Caracteristicas;Disponibilidad\n".
            "Neumático 26x1.95;NE-26-195;Neumáticos Ciclismo;Neumático estándar;Válvula moto;disponible\n";

        $file = UploadedFile::fake()->createWithContent('productos.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('import.products'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Neumáticos Ciclismo',
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Neumático 26x1.95',
            'reference' => 'NE-26-195',
            'availability' => 'disponible',
        ]);
    }

    public function test_admin_can_export_clients_to_csv(): void
    {
        Client::create([
            'name' => 'Taller San Fernando',
            'document' => '123456789',
            'phone' => '3123456789',
            'email' => 'sanfernando@test.com',
            'city' => 'Cali',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('export.clients'));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Taller San Fernando', $content);
        $this->assertStringContainsString('123456789', $content);
    }

    public function test_admin_can_export_products_to_csv(): void
    {
        $category = Category::create(['name' => 'Corazas', 'slug' => 'corazas', 'is_active' => true]);
        Product::create([
            'name' => 'Coraza 700x25 Ruta',
            'reference' => 'CRZ-700',
            'category_id' => $category->id,
            'availability' => 'disponible',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('export.products'));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Coraza 700x25 Ruta', $content);
        $this->assertStringContainsString('CRZ-700', $content);
        $this->assertStringContainsString('Corazas', $content);
    }

    public function test_admin_can_export_orders_to_csv(): void
    {
        $status = OrderStatus::firstOrCreate(['slug' => 'pedido-creado'], ['name' => 'Pedido Creado', 'color' => '#333']);
        $client = Client::create(['name' => 'Cliente Pedido CSV', 'document' => '555444', 'is_active' => true]);
        $product = Product::create(['name' => 'Pedal Aluminio', 'reference' => 'PED-ALU', 'is_active' => true]);

        $order = Order::create([
            'code' => 'PED-CSV-100',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'order_status_id' => $status->id,
            'billing_type' => 'factura_electronica',
            'payment_term' => 'credito',
            'payment_due_date' => now()->addDays(15),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $response = $this->actingAs($this->admin)->get(route('export.orders'));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('PED-CSV-100', $content);
        $this->assertStringContainsString('Cliente Pedido CSV', $content);
        $this->assertStringContainsString('Pedal Aluminio', $content);
        $this->assertStringContainsString('Factura Electrónica', $content);
        $this->assertStringContainsString('Credito', $content);
    }

    public function test_seller_cannot_access_import_export_routes(): void
    {
        $response = $this->actingAs($this->seller)->get(route('export.clients'));
        $response->assertRedirect(route('site.catalog'));

        $response = $this->actingAs($this->seller)->get(route('import.template', ['tipo' => 'clientes']));
        $response->assertRedirect(route('site.catalog'));
    }
}
