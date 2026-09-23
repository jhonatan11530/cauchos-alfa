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
use Illuminate\Support\Facades\Validator;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', [
            'featured' => Product::where('is_active', true)->with(['category', 'images'])->latest()->take(6)->get(),
        ]);
    }

    public function catalog(Request $request, $categoriaId = null, $slug = null): View
    {
        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        // Si viene por SEO url (/catalogo/1-llantas) o por query string (?categoria=1)
        $filterCategoryId = $categoriaId ?? $request->input('categoria');

        $products = Product::where('is_active', true)
            ->with(['category', 'images'])
            ->when($filterCategoryId, fn($q) => $q->where('category_id', $filterCategoryId))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('site.catalog', [
            'products' => $products,
            'categories' => $categories,
            'selectedCategory' => $categories->firstWhere('id', (int) $filterCategoryId),
            'activeCatalogs' => Catalog::where('is_active', true)->withCount('products')->get(),
        ]);
    }

    public function product(Request $request, $id, $slug = null): View
    {
        $product = Product::where('is_active', true)
            ->with(['category', 'images'])
            ->findOrFail($id);

        $relatedProducts = Product::where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('images')
            ->inRandomOrder()
            ->take(4)
            ->get();

        return view('site.product', compact('product', 'relatedProducts'));
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
        $rules = [
            'code' => ['required', 'string'],
        ];

        if (config('recaptchav3.secret') && config('recaptchav3.sitekey')) {
            $rules['g-recaptcha-response'] = ['required', 'recaptchav3:seller_login,0.5'];
        }

        $data = $request->validate($rules, [
            'g-recaptcha-response.required' => 'No se pudo generar la verificación de seguridad. Habilita JavaScript y vuelve a intentarlo.',
            'g-recaptcha-response.recaptchav3' => 'La verificación anti-robot falló. Vuelve a intentarlo.',
        ]);

        $code = strtoupper(trim($data['code']));
        $throttleKey = 'seller-login:' . strtolower($code) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'code' => 'Demasiados intentos. Inténtalo de nuevo en ' . $seconds . ' segundos.',
            ]);
        }

        $user = User::where('seller_code', $code)
            ->where('is_active', true)
            ->first();

        if (!$user || !$user->isSeller()) {
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
}
