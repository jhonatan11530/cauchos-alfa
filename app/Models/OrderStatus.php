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
