<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;

/**
 * Helper compartido para construir datos de prueba del sistema
 * Cauchos Alfa (roles, usuarios, categorías, productos, clientes).
 */
trait CreatesSystemData
{
    protected function createRoles(): array
    {
        return [
            'admin' => Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']),
            'seller' => Role::firstOrCreate(['slug' => 'vendedor'], ['name' => 'Vendedor']),
        ];
    }

    protected function createAdmin(array $attributes = []): User
    {
        $roles = $this->createRoles();

        return User::create(array_merge([
            'role_id' => $roles['admin']->id,
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => 'secret-password',
            'is_active' => true,
        ], $attributes));
    }

    protected function createSeller(array $attributes = []): User
    {
        $roles = $this->createRoles();

        return User::create(array_merge([
            'role_id' => $roles['seller']->id,
            'name' => 'Vendedor Test',
            'email' => 'seller@test.com',
            'seller_code' => 'VEND001',
            'password' => 'secret-password',
            'is_active' => true,
        ], $attributes));
    }

    protected function createCategory(array $attributes = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Llantas',
            'is_active' => true,
        ], $attributes));
    }

    protected function createProduct(?Category $category = null, array $attributes = []): Product
    {
        $category ??= $this->createCategory();

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Llanta 185/65 R15',
            'reference' => 'REF-001',
            'availability' => 'disponible',
            'is_active' => true,
        ], $attributes));
    }

    protected function createClient(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Cliente Test',
            'phone' => '3000000000',
            'is_active' => true,
        ], $attributes));
    }
}
