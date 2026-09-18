<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'reference',
        'image_path',
        'description',
        'features',
        'availability',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function catalogs(): BelongsToMany
    {
        return $this->belongsToMany(Catalog::class)->withPivot('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * URLs de todas las imágenes del producto (galería nueva + imagen antigua).
     */
    public function imageUrls(): array
    {
        $urls = $this->images->map(fn (ProductImage $img) => asset('storage/' . $img->path))->all();
        $urls = $this->images->map(fn (ProductImage $img) => asset('public/storage/' . $img->path))->all();

        if ($this->image_path) {
            $urls[] = asset('storage/' . $this->image_path);
            $urls[] = asset('public/storage/' . $this->image_path);
        }

        return $urls;
    }

    /**
     * Extensiones de imagen originales permitidas en el catálogo PDF (excluye WebP).
     */
    private const ALLOWED_ORIGINAL_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * Determina si la ruta corresponde a una imagen original válida (no WebP).
     */
    private function isOriginalImageSupported(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Descartar explícitamente cualquier imagen WebP
        if ($extension === 'webp') {
            return false;
        }

        return in_array($extension, self::ALLOWED_ORIGINAL_IMAGE_EXTENSIONS, true);
    }

    /**
     * Obtiene la ruta relativa de la imagen original del producto (excluye terminantemente WebP).
     */
    public function primaryImagePath(): ?string
    {
        // 1. Usar la imagen principal si está en formato original soportado (JPG, PNG, etc.)
        if ($this->isOriginalImageSupported($this->image_path)) {
            return $this->image_path;
        }

        // 2. Si la principal es WebP o nula, buscar la primera imagen original compatible en la galería
        foreach ($this->images as $image) {
            if ($this->isOriginalImageSupported($image->path)) {
                return $image->path;
            }
        }

        return null;
    }

    /**
     * Obtiene el Data URI de la imagen original sin conversiones ni formatos WebP.
     */
    public function primaryImageDataUri(): ?string
    {
        $path = $this->primaryImagePath();
        if (! $path) {
            return null;
        }

        $fullPath = $this->resolveLocalImagePath($path);
        if (! $fullPath) {
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

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    /**
     * Resuelve la ruta absoluta accesible en disco local para una imagen.
     */
    private function resolveLocalImagePath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($relativePath);
        if (! file_exists($fullPath) || ! is_readable($fullPath)) {
            $fullPath = public_path('storage/' . $relativePath);
        }

        return (file_exists($fullPath) && is_readable($fullPath)) ? $fullPath : null;
    }
}
