<?php

namespace App\Support;

use Illuminate\Session\SessionManager;

/**
 * Encapsula el acceso al carrito de vendedor guardado en la sesion,
 * evitando repetir los snippets de lectura/escritura de session().
 */
class SellerCart
{
    public const SESSION_KEY = 'seller_cart';

    public function __construct(private SessionManager $session)
    {
    }

    /** @return array<int, int> mapa product_id => cantidad */
    public function get(): array
    {
        return (array) $this->session->get(self::SESSION_KEY, []);
    }

    public function add(int $productId, int $quantity): void
    {
        $cart = $this->get();
        $cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
        $this->session->put(self::SESSION_KEY, $cart);
    }

    public function remove(int $productId): void
    {
        $cart = $this->get();
        unset($cart[$productId]);
        $this->session->put(self::SESSION_KEY, $cart);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public function isEmpty(): bool
    {
        return empty($this->get());
    }
}
