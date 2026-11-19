<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        return view('admin.catalogs.index', ['catalogs' => Catalog::withCount('products')->latest()->paginate(10)]);
    }

    public function create(): View
    {
        return view('admin.catalogs.form', $this->formData(new Catalog));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        unset($data['products']);

        $ordering = $this->normalizeOrdering(
            $request->input('category_order', []),
            $request->input('products', [])
        );

        $data['category_order'] = $ordering['category_order'];
        $data['is_active'] = $request->boolean('is_active');
        $data['logo_path'] = $this->storeLogo($request);

        $catalog = Catalog::create($data);
        $this->syncProducts($catalog, $ordering['product_ids']);

        return redirect()->route('catalogos.index')->with('success', 'Catalogo creado correctamente.');
    }

    public function show(Catalog $catalogo): View
    {
        $orderedProducts = $catalogo->orderedProductsForPdf();

        return view('admin.catalogs.show', [
            'catalog' => $catalogo,
            'orderedProducts' => $orderedProducts,
        ]);
    }

    public function edit(Catalog $catalogo): View
    {
        return view('admin.catalogs.form', $this->formData($catalogo));
    }

    public function update(Request $request, Catalog $catalogo): RedirectResponse
    {
        $data = $this->validatedData($request);
        unset($data['products']);

        $ordering = $this->normalizeOrdering(
            $request->input('category_order', []),
            $request->input('products', [])
        );

        $data['category_order'] = $ordering['category_order'];
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
        $this->syncProducts($catalogo, $ordering['product_ids']);

        return redirect()->route('catalogos.index')->with('success', 'Catalogo actualizado correctamente.');
    }

    public function destroy(Catalog $catalogo): RedirectResponse
    {
        $catalogo->update(['is_active' => ! $catalogo->is_active]);

        return back()->with('success', 'Estado del catalogo actualizado.');
    }

    public function pdf(Catalog $catalogo)
    {
        $orderedProducts = $catalogo->orderedProductsForPdf();
        $productsByCategory = $catalogo->groupedOrderedProductsForPdf();

        return app('dompdf.wrapper')->loadView($catalogo->resolveTemplateView(), [
            'catalog' => $catalogo,
            'orderedProducts' => $orderedProducts,
            'productsByCategory' => $productsByCategory,
        ])->setPaper('A4', 'portrait')
        ->stream('catalogo-'.$catalogo->id.'.pdf');
    }

    public function publicPdf(Catalog $catalogo)
    {
        $orderedProducts = $catalogo->orderedProductsForPdf();
        $productsByCategory = $catalogo->groupedOrderedProductsForPdf();

        return app('dompdf.wrapper')
            ->loadView($catalogo->resolveTemplateView(), [
                'catalog' => $catalogo,
                'orderedProducts' => $orderedProducts,
                'productsByCategory' => $productsByCategory,
            ])
            ->setPaper('A4', 'portrait')
            ->stream('catalogo-'.$catalogo->id.'.pdf');
    }

    private function formData(Catalog $catalog): array
    {
        $allProducts = Product::with('category')->where('is_active', true)->orderBy('name')->get();
        $savedSelectedIds = $catalog->exists
            ? $catalog->products()->pluck('products.id')->map(fn ($id) => (int) $id)->all()
            : [];

        $hasOldInput = session()->hasOldInput() || session()->has('_old_input');
        $effectiveSelectedIds = $hasOldInput
            ? array_values(array_unique(array_map('intval', (array) old('products', []))))
            : $savedSelectedIds;

        $selectedRank = array_flip($effectiveSelectedIds);

        $oldCategoryOrder = old('category_order');
        if (is_array($oldCategoryOrder)) {
            $effectiveCategoryOrder = $this->normalizeCategoryOrderList($oldCategoryOrder);
        } elseif ($catalog->exists && is_array($catalog->category_order) && count($catalog->category_order) > 0) {
            $effectiveCategoryOrder = $this->normalizeCategoryOrderList($catalog->category_order);
        } elseif ($catalog->exists) {
            $catalog->loadMissing('products');
            $effectiveCategoryOrder = $catalog->products
                ->map(fn (Product $product) => Catalog::categoryKeyForProduct($product))
                ->unique()
                ->values()
                ->all();
        } else {
            $effectiveCategoryOrder = [];
        }

        $categoryRank = array_flip($effectiveCategoryOrder);

        $orderedCategoryGroups = $allProducts
            ->groupBy(fn (Product $product) => Catalog::categoryKeyForProduct($product))
            ->map(function (Collection $groupProducts, string $key) use ($selectedRank) {
                $first = $groupProducts->first();
                $name = $first?->category?->name ?? 'Sin Categoría';

                $sortedProducts = $groupProducts->sort(function (Product $a, Product $b) use ($selectedRank) {
                    $rankA = $selectedRank[$a->id] ?? PHP_INT_MAX;
                    $rankB = $selectedRank[$b->id] ?? PHP_INT_MAX;

                    if ($rankA !== $rankB) {
                        return $rankA <=> $rankB;
                    }

                    $nameCmp = strcasecmp($a->name, $b->name);

                    return $nameCmp !== 0 ? $nameCmp : ($a->id <=> $b->id);
                })->values();

                return [
                    'key' => $key,
                    'name' => $name,
                    'products' => $sortedProducts,
                ];
            })
            ->sort(function (array $a, array $b) use ($categoryRank) {
                $rankA = $categoryRank[$a['key']] ?? PHP_INT_MAX;
                $rankB = $categoryRank[$b['key']] ?? PHP_INT_MAX;

                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                if ($a['key'] === 'uncategorized' && $b['key'] !== 'uncategorized') {
                    return 1;
                }
                if ($b['key'] === 'uncategorized' && $a['key'] !== 'uncategorized') {
                    return -1;
                }

                return strcasecmp($a['name'], $b['name']);
            })
            ->values();

        return [
            'catalog' => $catalog,
            'products' => $allProducts,
            'selectedProducts' => $effectiveSelectedIds,
            'orderedCategoryGroups' => $orderedCategoryGroups,
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
            'template' => ['required', 'string', Rule::in(Catalog::VALID_TEMPLATES)],
            'category_order' => ['nullable', 'array'],
            'category_order.*' => ['string', 'max:50'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer', Rule::exists('products', 'id')->where('is_active', true)],
        ]);
    }

    private function normalizeCategoryOrderList(array $categoryOrder): array
    {
        return collect($categoryOrder)
            ->filter(fn ($key) => is_scalar($key))
            ->map(fn ($key) => trim((string) $key))
            ->filter(fn (string $key) => $key !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{category_order: ?array<int, string>, product_ids: array<int, int>}
     */
    private function normalizeOrdering(array $submittedCategoryOrder, array $submittedProductIds): array
    {
        $normalizedCategoryOrder = $this->normalizeCategoryOrderList($submittedCategoryOrder);

        $uniqueProductIds = collect($submittedProductIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($uniqueProductIds->isEmpty()) {
            return [
                'category_order' => count($normalizedCategoryOrder) > 0 ? $normalizedCategoryOrder : null,
                'product_ids' => [],
            ];
        }

        $productsById = Product::whereIn('id', $uniqueProductIds->all())
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $groupedProductIds = collect();
        foreach ($uniqueProductIds as $productId) {
            $product = $productsById->get($productId);
            if (! $product) {
                continue;
            }
            $catKey = Catalog::categoryKeyForProduct($product);
            if (! $groupedProductIds->has($catKey)) {
                $groupedProductIds->put($catKey, collect());
            }
            $groupedProductIds->get($catKey)->push($productId);
        }

        if (count($normalizedCategoryOrder) > 0) {
            $groupedKeys = $groupedProductIds->keys()->map(fn ($key) => (string) $key);
            $orderedKeys = collect($normalizedCategoryOrder)
                ->filter(fn (string $key) => $groupedProductIds->has($key))
                ->merge($groupedKeys->diff($normalizedCategoryOrder))
                ->values();
            $persistedCategoryOrder = $normalizedCategoryOrder;
        } else {
            $orderedKeys = $groupedProductIds->keys()->map(fn ($key) => (string) $key)->values();
            $persistedCategoryOrder = null;
        }

        $orderedProductIds = [];
        foreach ($orderedKeys as $key) {
            foreach ($groupedProductIds->get($key, collect()) as $productId) {
                $orderedProductIds[] = $productId;
            }
        }

        return [
            'category_order' => $persistedCategoryOrder,
            'product_ids' => $orderedProductIds,
        ];
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
