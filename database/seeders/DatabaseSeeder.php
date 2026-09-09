<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(
            ['slug' => 'administrador'],
            ['name' => 'Administrador']
        );

        User::updateOrCreate(['email' => 'admin@cauchosalfa.com'], [
            'role_id' => $adminRole->id,
            'name' => 'Administrador',
            'phone' => null,
            'password' => Hash::make('CauchosAlfa@2025'),
            'is_active' => true,
        ]);

        $vendedorRole = Role::firstOrCreate(
            ['slug' => 'vendedor'],
            ['name' => 'Vendedor']
        );

        $statuses = [
            ['name' => 'Pedido creado', 'slug' => 'pedido-creado', 'color' => '#1572e8', 'sort_order' => 1],
            ['name' => 'Pedido recibido', 'slug' => 'pedido-recibido', 'color' => '#48abf7', 'sort_order' => 2],
            ['name' => 'Pedido en preparacion', 'slug' => 'pedido-en-preparacion', 'color' => '#ffad46', 'sort_order' => 3],
            ['name' => 'Pedido en proceso', 'slug' => 'pedido-en-proceso', 'color' => '#6861ce', 'sort_order' => 4],
            ['name' => 'Pedido despachado', 'slug' => 'pedido-despachado', 'color' => '#31ce36', 'sort_order' => 5],
            ['name' => 'Pedido entregado', 'slug' => 'pedido-entregado', 'color' => '#2bb930', 'sort_order' => 6, 'is_final' => true],
            ['name' => 'Pedido cancelado', 'slug' => 'pedido-cancelado', 'color' => '#f25961', 'sort_order' => 7, 'is_final' => true, 'is_cancelled' => true],
        ];

        foreach ($statuses as $status) {
            OrderStatus::updateOrCreate(['slug' => $status['slug']], $status);
        }
    }
}
