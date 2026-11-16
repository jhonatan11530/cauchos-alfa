<?php

namespace Database\Seeders;

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
            'phone' => 0,
            'password' => Hash::make('CauchosAlfa@2025'),
            'is_active' => true,
        ]);

        $vendedorRole = Role::firstOrCreate(
            ['slug' => 'vendedor'],
            ['name' => 'Vendedor']
        );

        $this->call(OrderStatusSeeder::class);
    }
}
