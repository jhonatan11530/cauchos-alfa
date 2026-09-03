<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        if ($this->image_path) {
            $urls[] = asset('storage/' . $this->image_path);
        }

        return $urls;
    }
}
