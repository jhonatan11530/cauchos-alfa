<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class OrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'Pedido creado',
                'slug' => OrderStatus::SLUG_CREADO,
                'color' => '#1572e8',
                'sort_order' => 1,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido recibido',
                'slug' => OrderStatus::SLUG_RECIBIDO,
                'color' => '#48abf7',
                'sort_order' => 2,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido en preparacion',
                'slug' => OrderStatus::SLUG_EN_PREPARACION,
                'color' => '#ffad46',
                'sort_order' => 3,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido en proceso',
                'slug' => OrderStatus::SLUG_EN_PROCESO,
                'color' => '#6861ce',
                'sort_order' => 4,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido despachado',
                'slug' => OrderStatus::SLUG_DESPACHADO,
                'color' => '#31ce36',
                'sort_order' => 5,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido entregado',
                'slug' => OrderStatus::SLUG_ENTREGADO,
                'color' => '#2bb930',
                'sort_order' => 6,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido cancelado',
                'slug' => OrderStatus::SLUG_CANCELADO,
                'color' => '#f25961',
                'sort_order' => 7,
                'is_final' => true,
                'is_cancelled' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido devuelto',
                'slug' => OrderStatus::SLUG_DEVUELTO,
                'color' => '#e83e8c',
                'sort_order' => 8,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido rechazado',
                'slug' => OrderStatus::SLUG_RECHAZADO,
                'color' => '#dc3545',
                'sort_order' => 9,
                'is_final' => true,
                'is_cancelled' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido cobrado',
                'slug' => OrderStatus::SLUG_COBRADO,
                'color' => '#28a745',
                'sort_order' => 10,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Cobro',
                'slug' => OrderStatus::SLUG_COBRO,
                'color' => '#17a2b8',
                'sort_order' => 11,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
            ],
        ];

        foreach ($statuses as $status) {
            OrderStatus::updateOrCreate(['slug' => $status['slug']], $status);
        }
    }
}
