<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Catalog extends Model
{
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
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('sort_order')->orderByPivot('sort_order');
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
            $fullPath = public_path('storage/' . $this->logo_path);
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

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }
}
