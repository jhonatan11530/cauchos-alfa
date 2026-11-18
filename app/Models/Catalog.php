<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Catalog extends Model
{
    public const VALID_TEMPLATES = ['default', 'catalogo-web'];

    protected $fillable = [
        'name',
        'description',
        'company_name',
        'logo_path',
        'contact_email',
        'contact_phone',
        'contact_address',
        'notes',
        'is_active',
        'template',
        'category_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'category_order' => 'array',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->orderBy('products.id');
    }

    public static function categoryKeyForProduct(Product $product): string
    {
        return $product->category_id !== null ? (string) $product->category_id : 'uncategorized';
    }

    public function resolveTemplateView(): string
    {
        $template = in_array($this->template, self::VALID_TEMPLATES, true)
            ? $this->template
            : 'default';

        $viewName = 'admin.catalogs.templates.'.$template;

        return view()->exists($viewName)
            ? $viewName
            : 'admin.catalogs.templates.default';
    }

    public function orderedProductsForPdf(): Collection
    {
        $this->loadMissing(['products.category', 'products.images']);

        $products = $this->products
            ->filter(fn (Product $product) => (bool) $product->is_active)
            ->sortBy(fn (Product $product) => sprintf('%010d_%010d', (int) ($product->pivot->sort_order ?? 0), $product->id))
            ->values();

        if ($products->isEmpty()) {
            return collect();
        }

        $groupedByKey = $products->groupBy(fn (Product $product) => self::categoryKeyForProduct($product));

        $configuredOrder = collect(is_array($this->category_order) ? $this->category_order : [])
            ->filter(fn ($key) => is_scalar($key))
            ->map(fn ($key) => trim((string) $key))
            ->filter(fn (string $key) => $key !== '')
            ->unique()
            ->values();

        $groupedKeys = $groupedByKey->keys()->map(fn ($key) => (string) $key);

        $orderedKeys = $configuredOrder
            ->filter(fn (string $key) => $groupedByKey->has($key))
            ->merge($groupedKeys->diff($configuredOrder))
            ->values();

        $ordered = collect();
        foreach ($orderedKeys as $key) {
            foreach ($groupedByKey->get($key, collect()) as $product) {
                $ordered->push($product);
            }
        }

        return $ordered;
    }

    public function groupedOrderedProductsForPdf(): Collection
    {
        return $this->orderedProductsForPdf()->groupBy(function (Product $product) {
            return $product->category?->name ?? 'Otros Productos';
        });
    }

    /**
     * Genera un Data URI base64 del logo para renderizado directo en PDFs (Dompdf).
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($this->logo_path);
        if (! file_exists($fullPath) || ! is_readable($fullPath)) {
            $fullPath = public_path('storage/'.$this->logo_path);
        }

        if (! file_exists($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if ($extension === 'webp') {
            return null;
        }

        $mime = mime_content_type($fullPath) ?: '';
        if (str_contains($mime, 'webp')) {
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $mime = $mime ?: ($extension === 'png' ? 'image/png' : 'image/jpeg');

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }
}
