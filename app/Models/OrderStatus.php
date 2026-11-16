<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderStatus extends Model
{
    public const SLUG_CREADO = 'pedido-creado';

    public const SLUG_RECIBIDO = 'pedido-recibido';

    public const SLUG_EN_PREPARACION = 'pedido-en-preparacion';

    public const SLUG_EN_PROCESO = 'pedido-en-proceso';

    public const SLUG_DESPACHADO = 'pedido-despachado';

    public const SLUG_ENTREGADO = 'pedido-entregado';

    public const SLUG_CANCELADO = 'pedido-cancelado';

    public const SLUG_DEVUELTO = 'pedido-devuelto';

    public const SLUG_RECHAZADO = 'pedido-rechazado';

    public const SLUG_COBRADO = 'pedido-cobrado';

    public const SLUG_COBRO = 'cobro';

    public const CRITICAL_SLUGS = [
        self::SLUG_CREADO,
        self::SLUG_RECIBIDO,
        self::SLUG_EN_PREPARACION,
        self::SLUG_EN_PROCESO,
        self::SLUG_DESPACHADO,
        self::SLUG_ENTREGADO,
        self::SLUG_CANCELADO,
        self::SLUG_DEVUELTO,
        self::SLUG_RECHAZADO,
        self::SLUG_COBRADO,
        self::SLUG_COBRO,
    ];

    public function isCritical(): bool
    {
        return in_array($this->slug, self::CRITICAL_SLUGS, true);
    }

    protected static function booted(): void
    {
        static::updating(function (OrderStatus $status) {
            if ($status->isDirty('slug')) {
                $originalSlug = $status->getOriginal('slug');
                if (in_array($originalSlug, self::CRITICAL_SLUGS, true)) {
                    $status->slug = $originalSlug;
                }
            }
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'color',
        'sort_order',
        'is_final',
        'is_cancelled',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_final' => 'boolean',
            'is_cancelled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isDespachado(): bool
    {
        return $this->slug === self::SLUG_DESPACHADO;
    }

    public function isEntregado(): bool
    {
        return $this->slug === self::SLUG_ENTREGADO;
    }

    public function isCobrado(): bool
    {
        return in_array($this->slug, [self::SLUG_COBRADO, self::SLUG_COBRO]);
    }

    public function isDevuelto(): bool
    {
        return $this->slug === self::SLUG_DEVUELTO;
    }

    public function isRechazado(): bool
    {
        return in_array($this->slug, [self::SLUG_RECHAZADO, self::SLUG_CANCELADO]);
    }
}
