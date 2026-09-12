<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    public function test_pertenece_a_un_rol(): void
    {
        $seller = $this->createSeller();

        $this->assertEquals('vendedor', $seller->role->slug);
    }

    public function test_identifica_a_un_administrador(): void
    {
        $this->assertTrue($this->createAdmin()->isAdmin());
        $this->assertFalse($this->createSeller()->isAdmin());
    }

    public function test_identifica_a_un_vendedor(): void
    {
        $this->assertTrue($this->createSeller()->isSeller());
        $this->assertFalse($this->createAdmin()->isSeller());
    }

    public function test_usuario_sin_rol_no_es_admin_ni_vendedor(): void
    {
        $user = User::create([
            'name' => 'Sin rol',
            'email' => 'norole@test.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isSeller());
    }

    public function test_la_contrasena_se_hashea_automaticamente(): void
    {
        $user = $this->createSeller(['password' => 'mi-secreto']);

        $this->assertNotSame('mi-secreto', $user->password);
        $this->assertTrue(Hash::check('mi-secreto', $user->password));
    }

    public function test_la_contrasena_no_aparece_en_la_serializacion(): void
    {
        $array = $this->createAdmin()->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }
}
