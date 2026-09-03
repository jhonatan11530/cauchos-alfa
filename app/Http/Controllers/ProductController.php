<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::with(['category', 'images'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->latest();

        return view('admin.products.index', ['products' => $products->paginate(10)->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.products.form', $this->formData(new Product()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['image_path'] = $this->storeImage($request);
        $product = Product::create($data);
        $this->storeImages($request, $product);

        return redirect()->route('productos.index')->with('success', 'Producto creado correctamente.');
    }

    public function edit(Product $producto): View
    {
        return view('admin.products.form', ['product' => $producto->load('images')] + $this->formData($producto));
    }

    public function update(Request $request, Product $producto): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['is_active'] = $request->boolean('is_active');

        if ($path = $this->storeImage($request)) {
            $data['image_path'] = $path;
        }

        $producto->update($data);
        $this->storeImages($request, $producto);

        return redirect()->route('productos.index')->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $producto): RedirectResponse
    {
        $producto->update(['is_active' => ! $producto->is_active]);

        return back()->with('success', 'Estado del producto actualizado.');
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'availabilityOptions' => ['disponible', 'agotado', 'bajo pedido'],
        ];
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:2048'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:2048'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'string'],
            'availability' => ['required', 'string', 'max:50'],
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        return $request->hasFile('image') ? $request->file('image')->store('products', 'public') : null;
    }

    /**
     * Guarda cada archivo del input múltiple "images[]" en product_images.
     */
    private function storeImages(Request $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $sortOrder = (int) $product->images()->max('sort_order');

        foreach ($request->file('images') as $file) {
            $product->images()->create([
                'path' => $file->store('products', 'public'),
                'sort_order' => ++$sortOrder,
            ]);
        }
    }

    /**
     * Elimina una imagen de la galería y su archivo físico.
     */
    public function destroyImage(Product $producto, ProductImage $imagen): RedirectResponse
    {
        abort_unless((int) $imagen->product_id === (int) $producto->id, 404);

        Storage::disk('public')->delete($imagen->path);
        $imagen->delete();

        return back()->with('success', 'Imagen eliminada correctamente.');
    }
}
