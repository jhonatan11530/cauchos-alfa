<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Services\CsvHandlerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(protected CsvHandlerService $csvHandler) {}

    /**
     * Descarga la plantilla CSV oficial según el tipo solicitado.
     */
    public function downloadTemplate(string $tipo): StreamedResponse
    {
        if ($tipo === 'clientes') {
            $headers = ['Nombre', 'Documento', 'Email', 'Telefono', 'Direccion', 'Ciudad', 'Notas'];
            $sample = [
                ['Taller El Ciclista', '900123456-1', 'contacto@elciclista.com', '3101234567', 'Calle 10 # 5-20', 'Cali', 'Cliente mayorista'],
                ['Distribuidora Valle', '800654321-9', 'ventas@distrivalle.com', '3159876543', 'Av. 6N # 24-10', 'Cali', 'Pago a crédito 30 días'],
            ];

            return $this->csvHandler->streamDownload('plantilla_clientes.csv', $headers, $sample);
        }

        if ($tipo === 'productos') {
            $headers = ['Nombre', 'Referencia', 'Categoria', 'Descripcion', 'Caracteristicas', 'Disponibilidad'];
            $sample = [
                ['Llanta MTB 29 x 2.25', 'LL-29-MTB', 'Llantas Bicicleta', 'Llanta para terreno mixto con excelente agarre', 'Aro 29, Ancho 2.25, Tubeless ready', 'disponible'],
                ['Neumático Moto 17', 'NE-MOTO-17', 'Neumáticos Moto', 'Neumático reforzado para moto urbana', 'Rin 17, Válvula curva', 'disponible'],
            ];

            return $this->csvHandler->streamDownload('plantilla_productos.csv', $headers, $sample);
        }

        abort(404, 'Tipo de plantilla no soportado.');
    }

    /**
     * Importa clientes desde un archivo CSV.
     */
    public function importClients(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return back()->with('error', 'El archivo subido no es válido.');
        }

        try {
            $parsed = $this->csvHandler->parse($file->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al leer el archivo CSV: '.$e->getMessage());
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($parsed['rows'] as $row) {
            $rowNum = $row['_row_number'] ?? '?';
            $name = $row['nombre'] ?? $row['name'] ?? null;

            if (empty($name)) {
                $errors[] = "Fila {$rowNum}: El campo 'Nombre' es obligatorio.";

                continue;
            }

            $document = ! empty($row['documento']) ? trim($row['documento']) : (! empty($row['document']) ? trim($row['document']) : null);
            $email = ! empty($row['email']) ? trim($row['email']) : (! empty($row['correo']) ? trim($row['correo']) : null);
            $phone = ! empty($row['telefono']) ? trim($row['telefono']) : (! empty($row['phone']) ? trim($row['phone']) : null);
            $address = ! empty($row['direccion']) ? trim($row['direccion']) : (! empty($row['address']) ? trim($row['address']) : null);
            $city = ! empty($row['ciudad']) ? trim($row['ciudad']) : (! empty($row['city']) ? trim($row['city']) : null);
            $notes = ! empty($row['notas']) ? trim($row['notas']) : (! empty($row['notes']) ? trim($row['notes']) : null);

            $data = [
                'name' => $name,
                'document' => $document,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'notes' => $notes,
                'is_active' => true,
            ];

            if ($document) {
                $existing = Client::where('document', $document)->first();
                if ($existing) {
                    $existing->update($data);
                    $updated++;

                    continue;
                }
            }

            Client::create($data);
            $created++;
        }

        $msg = "Importación completada: {$created} clientes creados, {$updated} actualizados.";
        if (! empty($errors)) {
            $msg .= ' Se omitieron '.count($errors).' filas con errores.';

            return back()->with('warning', $msg)->with('import_errors', array_slice($errors, 0, 10));
        }

        return back()->with('success', $msg);
    }

    /**
     * Importa productos desde un archivo CSV.
     */
    public function importProducts(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return back()->with('error', 'El archivo subido no es válido.');
        }

        try {
            $parsed = $this->csvHandler->parse($file->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al leer el archivo CSV: '.$e->getMessage());
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($parsed['rows'] as $row) {
            $rowNum = $row['_row_number'] ?? '?';
            $name = $row['nombre'] ?? $row['name'] ?? null;

            if (empty($name)) {
                $errors[] = "Fila {$rowNum}: El campo 'Nombre' es obligatorio.";

                continue;
            }

            $reference = ! empty($row['referencia']) ? trim($row['referencia']) : (! empty($row['reference']) ? trim($row['reference']) : null);
            $categoryName = ! empty($row['categoria']) ? trim($row['categoria']) : (! empty($row['category']) ? trim($row['category']) : null);
            $description = ! empty($row['descripcion']) ? trim($row['descripcion']) : (! empty($row['description']) ? trim($row['description']) : null);
            $features = ! empty($row['caracteristicas']) ? trim($row['caracteristicas']) : (! empty($row['features']) ? trim($row['features']) : null);
            $availability = ! empty($row['disponibilidad']) ? strtolower(trim($row['disponibilidad'])) : 'disponible';

            if (! in_array($availability, ['disponible', 'agotado', 'bajo pedido'])) {
                $availability = 'disponible';
            }

            // Mapear o crear categoría si fue suministrada
            $categoryId = null;
            if ($categoryName) {
                $category = Category::firstOrCreate(
                    ['name' => $categoryName],
                    ['slug' => Str::slug($categoryName), 'is_active' => true]
                );
                $categoryId = $category->id;
            }

            $data = [
                'name' => $name,
                'reference' => $reference,
                'category_id' => $categoryId,
                'description' => $description,
                'features' => $features,
                'availability' => $availability,
                'is_active' => true,
            ];

            if ($reference) {
                $existing = Product::where('reference', $reference)->first();
                if ($existing) {
                    $existing->update($data);
                    $updated++;

                    continue;
                }
            }

            Product::create($data);
            $created++;
        }

        $msg = "Importación completada: {$created} productos creados, {$updated} actualizados.";
        if (! empty($errors)) {
            $msg .= ' Se omitieron '.count($errors).' filas con errores.';

            return back()->with('warning', $msg)->with('import_errors', array_slice($errors, 0, 10));
        }

        return back()->with('success', $msg);
    }
}
