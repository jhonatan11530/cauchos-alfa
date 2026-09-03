<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        return view('admin.catalogs.index', ['catalogs' => Catalog::withCount('products')->latest()->paginate(10)]);
    }

    public function create(): View
    {
        return view('admin.catalogs.form', $this->formData(new Catalog()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['logo_path'] = $this->storeLogo($request);
        $catalog = Catalog::create($data);
        $this->syncProducts($catalog, $request->input('products', []));

        return redirect()->route('catalogos.index')->with('success', 'Catalogo creado correctamente.');
    }

    public function show(Catalog $catalogo): View
    {
        return view('admin.catalogs.show', ['catalog' => $catalogo->load('products.category')]);
    }

    public function edit(Catalog $catalogo): View
    {
        return view('admin.catalogs.form', $this->formData($catalogo));
    }

    public function update(Request $request, Catalog $catalogo): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['is_active'] = $request->boolean('is_active');

        if ($path = $this->storeLogo($request)) {
            // El logo anterior se elimina del disco para no acumular
            // archivos huerfanos en el storage publico.
            if ($catalogo->logo_path) {
                Storage::disk('public')->delete($catalogo->logo_path);
            }
            $data['logo_path'] = $path;
        }

        $catalogo->update($data);
        $this->syncProducts($catalogo, $request->input('products', []));

        return redirect()->route('catalogos.index')->with('success', 'Catalogo actualizado correctamente.');
    }

    public function destroy(Catalog $catalogo): RedirectResponse
    {
        $catalogo->update(['is_active' => ! $catalogo->is_active]);

        return back()->with('success', 'Estado del catalogo actualizado.');
    }

    public function pdf(Catalog $catalogo)
    {
        $catalogo->load('products.category');

        $pdf = app('dompdf.wrapper')->loadView('admin.catalogs.pdf', ['catalog' => $catalogo]);

        return $pdf->stream('catalogo-'.$catalogo->id.'.pdf');
    }

    private function formData(Catalog $catalog): array
    {
        return [
            'catalog' => $catalog,
            'products' => Product::with('category')->where('is_active', true)->orderBy('name')->get(),
            'selectedProducts' => $catalog->exists ? $catalog->products()->pluck('products.id')->all() : [],
        ];
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'products' => ['nullable', 'array'],
            'products.*' => ['exists:products,id'],
        ]);
    }

    private function storeLogo(Request $request): ?string
    {
        return $request->hasFile('logo') ? $request->file('logo')->store('catalogs', 'public') : null;
    }

    private function syncProducts(Catalog $catalog, array $productIds): void
    {
        $sync = [];
        foreach (array_values($productIds) as $index => $productId) {
            $sync[$productId] = ['sort_order' => $index + 1];
        }
        $catalog->products()->sync($sync);
    }
}
