<?php

namespace Tests\Unit;

use App\Support\SellerCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class SellerCartSupportTest extends TestCase
{
    use RefreshDatabase;

    private SellerCart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cart = new SellerCart(app('session'));
    }

    /** Caso positivo: inicia vacío */
    public function test_inicia_vacio(): void
    {
        $this->assertSame([], $this->cart->get());
        $this->assertTrue($this->cart->isEmpty());
    }

    /** Caso positivo: agrega productos */
    public function test_agrega_un_producto_con_su_cantidad(): void
    {
        $this->cart->add(10, 2);

        $this->assertSame([10 => 2], $this->cart->get());
        $this->assertFalse($this->cart->isEmpty());
    }

    /** Caso positivo: suma cantidades del mismo producto (merge) */
    public function test_suma_cantidades_del_mismo_producto(): void
    {
        $this->cart->add(10, 2);
        $this->cart->add(10, 3);

        $this->assertSame([10 => 5], $this->cart->get());
    }

    /** Caso positivo: mantiene productos distintos por separado */
    public function test_mantiene_productos_distintos_por_separado(): void
    {
        $this->cart->add(10, 1);
        $this->cart->add(20, 4);

        $this->assertSame([10 => 1, 20 => 4], $this->cart->get());
    }

    /** Caso negativo: remover un producto inexistente no altera el carrito ni falla */
    public function test_remover_producto_inexistente_no_altera_el_carrito(): void
    {
        $this->cart->add(10, 1);

        $this->cart->remove(999);

        $this->assertSame([10 => 1], $this->cart->get());
    }

    /** Caso positivo: remueve el producto indicado */
    public function test_remueve_el_producto_indicado(): void
    {
        $this->cart->add(10, 1);
        $this->cart->add(20, 2);

        $this->cart->remove(10);

        $this->assertSame([20 => 2], $this->cart->get());
    }

    /** Caso límite: clear borra la clave completa de la sesión */
    public function test_clear_vacia_el_carrito(): void
    {
        $this->cart->add(10, 1);

        $this->cart->clear();

        $this->assertTrue($this->cart->isEmpty());
        $this->assertFalse(Session::driver()->has(SellerCart::SESSION_KEY));
    }

    /** Caso límite: persiste en la sesión entre instancias */
    public function test_persiste_entre_instancias_de_la_misma_sesion(): void
    {
        $this->cart->add(7, 3);

        $other = new SellerCart(app('session'));

        $this->assertSame([7 => 3], $other->get());
    }

    /** Caso negativo: rechaza ids no enteros */
    public function test_rechaza_product_id_no_entero(): void
    {
        $this->expectException(\TypeError::class);

        $this->cart->add('abc', 1);
    }
}
