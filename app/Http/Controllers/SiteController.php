<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\SellerCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', [
            'featured' => Product::where('is_active', true)->with(['category', 'images'])->latest()->take(6)->get(),
        ]);
    }

    public function catalog(Request $request): View
    {
        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $products = Product::where('is_active', true)
            ->with(['category', 'images'])
            ->when($request->filled('categoria'), fn ($q) => $q->where('category_id', $request->input('categoria')))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('site.catalog', [
            'products' => $products,
            'categories' => $categories,
            'selectedCategory' => $categories->firstWhere('id', (int) $request->input('categoria')),
            'activeCatalogs' => Catalog::where('is_active', true)->withCount('products')->get(),
        ]);
    }

    public function showSellerLogin(): View|RedirectResponse
    {
        if (auth()->check() && auth()->user()->isSeller()) {
            return redirect()->route('site.catalog');
        }

        return view('site.seller-login');
    }

    public function sellerLogin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $throttleKey = 'seller-login:'.strtolower($request->input('code')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'code' => 'Demasiados intentos. Inténtalo de nuevo en '.$seconds.' segundos.',
            ]);
        }

        $user = User::where('seller_code', strtoupper(trim($data['code'])))
            ->where('is_active', true)
            ->first();

        if (! $user || ! $user->isSeller()) {
            RateLimiter::hit($throttleKey, 120);

            return back()->withErrors(['code' => 'El código ingresado no corresponde a un vendedor activo.']);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('site.catalog')
            ->with('success', 'Bienvenido ' . $user->name . '. Ya puedes crear pedidos desde el catálogo.');
    }

    public function sellerLogout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('site.home');
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::where('is_active', true)->findOrFail($data['product_id']);
        app(SellerCart::class)->add($product->id, (int) $data['quantity']);

        return back()->with('success', "Se agregaron {$data['quantity']} unidad(es) de {$product->name} al pedido.");
    }

    public function removeCartItem(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'exists:products,id']]);
        app(SellerCart::class)->remove((int) $data['product_id']);

        return back()->with('success', 'Producto retirado del pedido.');
    }

    public function showSellerOrder(): View|RedirectResponse
    {
        $cart = app(SellerCart::class)->get();

        if (empty($cart)) {
            return redirect()->route('site.catalog')
                ->with('warning', 'Tu pedido está vacío. Agrega productos desde el catálogo.');
        }

        $cartProducts = Product::whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->with('category')->get()
            ->map(function ($product) use ($cart) {
                $product->cart_quantity = $cart[$product->id];

                return $product;
            });

        return view('site.seller-order', [
            'cartProducts' => $cartProducts,
            'clients' => \App\Models\Client::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function contact(): View
    {
        return view('site.contact');
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        return redirect()
            ->route('site.contact')
            ->with('success', 'Gracias por tu mensaje. Nos pondremos en contacto contigo pronto.');
    }
}
