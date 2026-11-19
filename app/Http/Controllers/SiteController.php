<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Support\SellerCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        // Si viene por SEO url (/catalogo/1-llantas) o por query string (?categoria=1)
        $filterCategoryId = $categoriaId ?? $request->input('categoria');

        $products = Product::where('is_active', true)
            ->with(['category', 'images'])
            ->when($filterCategoryId, fn ($q) => $q->where('category_id', $filterCategoryId))
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
        $throttleKey = 'seller-login:'.strtolower($code).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'code' => 'Demasiados intentos. Inténtalo de nuevo en '.$seconds.' segundos.',
            ]);
        }

        $user = User::where('seller_code', $code)
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
            ->with('success', 'Bienvenido '.$user->name.'. Ya puedes crear pedidos desde el catálogo.');
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
            ->with(['category', 'images'])->get()
            ->map(function ($product) use ($cart) {
                $product->cart_quantity = $cart[$product->id];

                return $product;
            });

        return view('site.seller-order', [
            'cartProducts' => $cartProducts,
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function sellerOrders(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        $despachadoStatus = OrderStatus::where('slug', OrderStatus::SLUG_DESPACHADO)->first();
        $entregadoStatus = OrderStatus::where('slug', OrderStatus::SLUG_ENTREGADO)->first();

        $query = Order::query();

        if ($user->isSeller()) {
            $query->where('created_by', $user->id);
        }

        $filter = $request->query('status', 'despachado');

        if ($filter === 'despachado' || ! $request->has('status')) {
            if ($despachadoStatus) {
                $query->where('order_status_id', $despachadoStatus->id);
            }
        } elseif ($filter === 'entregado') {
            if ($entregadoStatus) {
                $query->where('order_status_id', $entregadoStatus->id);
            }
        } elseif ($filter !== 'all') {
            $status = OrderStatus::where('slug', $filter)->first();
            if ($status) {
                $query->where('order_status_id', $status->id);
            }
        }

        $orders = $query->with(['status', 'client', 'items.product'])
            ->latest('ordered_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $dispatchedCount = $user->isSeller()
            ? Order::where('created_by', $user->id)->where('order_status_id', $despachadoStatus?->id)->count()
            : Order::where('order_status_id', $despachadoStatus?->id)->count();

        return view('site.seller-orders', [
            'orders' => $orders,
            'currentFilter' => $filter,
            'dispatchedCount' => $dispatchedCount,
            'despachadoStatus' => $despachadoStatus,
        ]);
    }

    public function deliverOrder(Request $request, Order $pedido): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Authorization check: Only assigned seller or admin can deliver
        if ($user->isSeller()) {
            if ((int) $pedido->created_by !== (int) $user->id) {
                abort(403, 'No tienes autorización para gestionar este pedido.');
            }

            $currentSlug = $pedido->status?->slug;
            if ($currentSlug !== OrderStatus::SLUG_DESPACHADO) {
                $validator = Validator::make([], []);
                $validator->errors()->add('order', 'El pedido no se encuentra en estado despachado para registrar su entrega.');
                throw new ValidationException($validator);
            }
        }

        // Resolve delivered status and enforce transition matrix for all users (including admins)
        $pedido->loadMissing('status');
        $entregadoStatus = OrderStatus::whereIn('slug', [OrderStatus::SLUG_ENTREGADO, 'entregado'])->firstOrFail();

        if ($pedido->status && ! $pedido->status->canTransitionTo($entregadoStatus)) {
            throw ValidationException::withMessages([
                'order_status_id' => 'No se puede marcar como entregado un pedido desde su estado actual.',
            ]);
        }

        $validator = Validator::make([], []);

        // Photo validation
        $photo = $request->file('delivery_photo') ?? $request->file('photo') ?? $request->file('delivery_photo_file');
        if (! $photo) {
            $validator->errors()->add('delivery_photo', 'La fotografía de entrega es obligatoria.');
            $validator->errors()->add('photo', 'La fotografía de entrega es obligatoria.');
        } else {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower($photo->getClientOriginalExtension() ?: '');
            $mime = strtolower($photo->getMimeType() ?: $photo->getClientMimeType() ?: '');
            $size = $photo->getSize();

            $isImageMime = str_starts_with($mime, 'image/') || in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
            $isAllowedExt = in_array($ext, $allowedExtensions, true);
            $isUnderSize = $size !== false && $size <= (10 * 1024 * 1024);

            if (! $photo->isValid() || ! $isImageMime || ! $isAllowedExt || ! $isUnderSize) {
                $validator->errors()->add('delivery_photo', 'El archivo adjunto debe ser una imagen válida (JPG, JPEG, PNG, WEBP).');
                $validator->errors()->add('photo', 'El archivo adjunto debe ser una imagen válida (JPG, JPEG, PNG, WEBP).');
            }
        }

        // Coordinates validation
        $lat = $request->input('delivery_latitude') ?? $request->input('latitude');
        $lng = $request->input('delivery_longitude') ?? $request->input('longitude');
        $location = $request->input('client_location') ?? $request->input('delivery_location');

        $latitude = null;
        $longitude = null;
        $latNumeric = ($lat !== null && is_numeric($lat));
        $lngNumeric = ($lng !== null && is_numeric($lng));

        if ($latNumeric && $lngNumeric) {
            $latitude = (float) $lat;
            $longitude = (float) $lng;
            if (empty($location)) {
                $location = "{$latitude}, {$longitude}";
            }
        } elseif (! empty($location) && is_string($location)) {
            if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $location, $matches)) {
                $latitude = (float) $matches[1];
                $longitude = (float) $matches[2];
            }
        }

        if ($latitude === null || $longitude === null) {
            $validator->errors()->add('delivery_latitude', 'Las coordenadas de latitud son obligatorias.');
            $validator->errors()->add('delivery_longitude', 'Las coordenadas de longitud son obligatorias.');
            $validator->errors()->add('delivery_location', 'Las coordenadas o ubicación de entrega son obligatorias.');
            $validator->errors()->add('client_location', 'Las coordenadas o ubicación de entrega son obligatorias.');
        }

        $latToCheck = $latitude ?? ($latNumeric ? (float) $lat : null);
        $lngToCheck = $longitude ?? ($lngNumeric ? (float) $lng : null);

        if (($latToCheck !== null && ($latToCheck < -90.0 || $latToCheck > 90.0)) ||
            ($lngToCheck !== null && ($lngToCheck < -180.0 || $lngToCheck > 180.0))) {
            $rangeMsg = 'Las coordenadas geográficas están fuera del rango válido (-90 a 90 para latitud, -180 a 180 para longitud).';
            if ($latToCheck !== null && ($latToCheck < -90.0 || $latToCheck > 90.0)) {
                $validator->errors()->add('delivery_latitude', $rangeMsg);
                $validator->errors()->add('latitude', $rangeMsg);
            }
            if ($lngToCheck !== null && ($lngToCheck < -180.0 || $lngToCheck > 180.0)) {
                $validator->errors()->add('delivery_longitude', $rangeMsg);
                $validator->errors()->add('longitude', $rangeMsg);
            }
            $validator->errors()->add('delivery_location', $rangeMsg);
            $validator->errors()->add('client_location', $rangeMsg);
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        // Store photo
        $photoPath = $photo->store('orders/deliveries', 'public');

        $previousStatusId = $pedido->order_status_id;
        $pedido->delivery_photo_path = $photoPath;
        $pedido->delivery_latitude = $latitude;
        $pedido->delivery_longitude = $longitude;
        $pedido->client_location = $location ?: "{$latitude}, {$longitude}";
        $pedido->order_status_id = $entregadoStatus->id;

        $term = $request->input('payment_term') ?? $request->input('payment_method') ?? $request->input('payment_modality');
        if (! empty($term)) {
            $pedido->payment_term = $term;
        }

        $observation = $request->input('observation') ?? $request->input('notes') ?? 'Entrega registrada por el vendedor con foto y coordenadas GPS.';

        DB::transaction(function () use ($pedido, $previousStatusId, $entregadoStatus, $user, $observation): void {
            $pedido->save();

            OrderStatusHistory::create([
                'order_id' => $pedido->id,
                'previous_status_id' => $previousStatusId,
                'new_status_id' => $entregadoStatus->id,
                'user_id' => $user->id,
                'observation' => $observation,
                'changed_at' => now(),
            ]);
        });

        return redirect()->route('site.seller.orders')
            ->with('success', "Entrega del pedido {$pedido->code} registrada correctamente.");
    }

    /**
     * Descarga el último catálogo de productos disponible en formato PDF para vendedores.
     */
    public function downloadLatestCatalog(Request $request)
    {
        $user = $request->user();
        if (! $user || (! $user->isSeller() && ! $user->isAdmin())) {
            abort(403, 'Solo los vendedores autorizados tienen acceso a esta descarga.');
        }

        $catalog = Catalog::where('is_active', true)->latest('id')->first()
            ?? Catalog::latest('id')->first();

        if (! $catalog) {
            return redirect()->route('site.catalog')
                ->with('warning', 'No hay ningún catálogo de productos disponible para descargar.');
        }

        $orderedProducts = $catalog->orderedProductsForPdf();
        $productsByCategory = $catalog->groupedOrderedProductsForPdf();

        $pdf = app('dompdf.wrapper')->loadView($catalog->resolveTemplateView(), [
            'catalog' => $catalog,
            'orderedProducts' => $orderedProducts,
            'productsByCategory' => $productsByCategory,
        ]);

        return $pdf->download('catalogo-'.$catalog->id.'.pdf');
    }

    /**
     * Carga los productos de un pedido anterior en el carrito del vendedor para repetirlo.
     */
    public function repeatSellerOrder(Order $pedido): RedirectResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->isSeller() && ! $user->isAdmin())) {
            abort(403, 'Acceso denegado.');
        }

        if ($user->isSeller() && (int) $pedido->created_by !== (int) $user->id) {
            abort(403, 'No tienes permiso para repetir este pedido.');
        }

        $cart = app(SellerCart::class);
        $pedido->load('items.product');

        $addedCount = 0;
        foreach ($pedido->items as $item) {
            if ($item->product && $item->product->is_active) {
                $cart->add($item->product_id, (int) $item->quantity);
                $addedCount++;
            }
        }

        if ($addedCount === 0) {
            return back()->with('error', 'Los productos de este pedido ya no se encuentran activos.');
        }

        return redirect()->route('site.seller.order')
            ->with('success', "Se cargaron {$addedCount} productos del pedido {$pedido->code} al carrito.");
    }
}
