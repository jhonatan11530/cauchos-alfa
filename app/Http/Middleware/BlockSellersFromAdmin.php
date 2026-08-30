<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSellersFromAdmin
{
    /**
     * Los vendedores no ingresan al panel administrativo; su zona de trabajo es la web pública.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->isSeller()) {
            return redirect()->route('site.catalog')
                ->with('success', 'Los vendedores gestionan sus pedidos desde el catálogo de la página web.');
        }

        return $next($request);
    }
}
