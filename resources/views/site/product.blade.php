@extends('site.layouts.app')

@section('title', $product->name . ' | Cauchos Alfa')
@section('description', \Illuminate\Support\Str::limit(strip_tags($product->description), 150))

@section('schema')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "Product",
  "name": "{!! $product->name !!}",
  "image": {!! json_encode($product->imageUrls()) !!},
  "description": "{!! strip_tags($product->description) !!}",
  "sku": "{!! $product->reference !!}",
  "brand": {
    "@@type": "Brand",
    "name": "Cauchos Alfa"
  },
  "offers": {
    "@@type": "Offer",
    "url": "{!! url()->current() !!}",
    "priceCurrency": "COP",
    "price": "{!! $product->price ?? 0 !!}",
    "itemCondition": "https://schema.org/NewCondition",
    "availability": "{!! $product->availability === 'disponible' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' !!}"
  }
}
</script>
@endsection

@section('content')
<div class="container my-5 pt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-transparent px-0">
            <li class="breadcrumb-item"><a href="{{ route('site.home') }}" class="text-danger">Inicio</a></li>
            <li class="breadcrumb-item"><a href="{{ route('site.catalog') }}" class="text-danger">Catálogo</a></li>
            @if($product->category)
            <li class="breadcrumb-item"><a href="{{ route('site.catalog.category', ['slug' => \Illuminate\Support\Str::slug($product->category->name), 'categoriaId' => $product->category->id]) }}" class="text-danger">{{ $product->category->name }}</a></li>
            @endif
            <li class="breadcrumb-item active text-white" aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row">
        <!-- Imágenes del Producto -->
        <div class="col-md-6 mb-4">
            @php $urls = $product->imageUrls(); @endphp
            @if (count($urls) > 1)
                <div id="gallery-{{ $product->id }}" class="carousel slide" data-ride="carousel">
                    <ol class="carousel-indicators">
                        @foreach ($urls as $i => $url)
                            <li data-target="#gallery-{{ $product->id }}" data-slide-to="{{ $i }}" class="{{ $i === 0 ? 'active' : '' }}"></li>
                        @endforeach
                    </ol>
                    <div class="carousel-inner rounded" style="background-color: #2b3035;">
                        @foreach ($urls as $i => $url)
                            <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                                <img src="{{ $url }}" class="d-block w-100" alt="{{ $product->name }} - imagen {{ $i + 1 }}" loading="lazy" style="aspect-ratio: 4 / 3; object-fit: contain;">
                            </div>
                        @endforeach
                    </div>
                    <button class="carousel-control-prev" type="button" data-target="#gallery-{{ $product->id }}" data-slide="prev">
                        <span class="bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; opacity: 0.8;">
                            <span class="carousel-control-prev-icon" style="width: 15px; height: 15px;" aria-hidden="true"></span>
                        </span>
                        <span class="sr-only">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-target="#gallery-{{ $product->id }}" data-slide="next">
                        <span class="bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; opacity: 0.8;">
                            <span class="carousel-control-next-icon" style="width: 15px; height: 15px;" aria-hidden="true"></span>
                        </span>
                        <span class="sr-only">Siguiente</span>
                    </button>
                </div>
            @elseif (count($urls) === 1)
                <div class="rounded overflow-hidden" style="background-color: #2b3035;">
                    <img src="{{ $urls[0] }}" class="w-100" alt="{{ $product->name }}" loading="lazy" style="aspect-ratio: 4 / 3; object-fit: contain;">
                </div>
            @else
                <div class="rounded d-flex align-items-center justify-content-center" style="aspect-ratio: 4 / 3; background-color: #2b3035;">
                    <i class="bi bi-image text-secondary" style="font-size: 4rem;"></i>
                </div>
            @endif
        </div>

        <!-- Detalles del Producto -->
        <div class="col-md-6">
            <h1 class="font-weight-bold text-white mb-2">{{ $product->name }}</h1>
            <p class="text-muted mb-3">Referencia: <span class="text-white">{{ $product->reference }}</span></p>

            <div class="mb-4">
                @if($product->availability === 'disponible')
                    <span class="badge badge-success px-3 py-2" style="font-size: 0.9rem;">Disponible</span>
                @else
                    <span class="badge badge-danger px-3 py-2" style="font-size: 0.9rem;">Agotado</span>
                @endif

                @if($product->price)
                    <h3 class="text-danger font-weight-bold mt-3">${{ number_format($product->price, 0, ',', '.') }} COP</h3>
                @endif
            </div>

            @if($product->description)
            <div class="mb-4">
                <h5 class="text-white border-bottom border-secondary pb-2">Descripción</h5>
                <div class="text-light" style="font-size: 0.95rem; line-height: 1.6;">
                    {!! nl2br(e($product->description)) !!}
                </div>
            </div>
            @endif

            @if($product->features)
            <div class="mb-4">
                <h5 class="text-white border-bottom border-secondary pb-2">Características Principales</h5>
                <div class="text-light" style="font-size: 0.95rem; line-height: 1.6;">
                    {!! nl2br(e($product->features)) !!}
                </div>
            </div>
            @endif

            <div class="mt-4 d-flex flex-wrap align-items-center" style="gap: 15px;">
                @auth
                    @if(auth()->user()->isSeller())
                        <form action="{{ route('site.seller.add') }}" method="POST" class="m-0">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="d-flex align-items-center">
                                <input type="number" name="quantity" value="1" min="1" class="form-control bg-dark text-white border-secondary text-center mr-2" style="width: 80px;" required>
                                <button type="submit" class="btn btn-red px-4 py-2 d-flex align-items-center" {{ $product->availability !== 'disponible' ? 'disabled' : '' }} style="border-radius: 20px;">
                                    <i class="bi bi-cart-plus mr-2" style="font-size: 1.2rem;"></i> Agregar al Pedido
                                </button>
                            </div>
                        </form>
                    @endif
                @endauth

                <a href="{{ config('whatsapp.phone') }}?text={{ urlencode('Hola Cauchos Alfa, estoy interesado en el producto: ' . $product->name . ' (Ref: ' . $product->reference . '). ¿Me podrían dar más información?') }}" target="_blank" class="btn btn-outline-success px-4 py-2 d-flex align-items-center" style="border-radius: 20px;">
                    <i class="bi bi-whatsapp mr-2" style="font-size: 1.2rem;"></i> Cotizar por WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Productos Relacionados -->
    @if(count($relatedProducts) > 0)
    <div class="mt-5 pt-5 border-top border-secondary">
        <h4 class="text-white mb-4">Productos Relacionados</h4>
        <div class="row">
            @foreach($relatedProducts as $related)
            <div class="col-6 col-md-3 mb-4">
                <div class="card h-100 bg-dark text-white border-secondary" style="border-radius: 15px; overflow: hidden; transition: 0.3s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <a href="{{ route('site.product', ['slug' => \Illuminate\Support\Str::slug($related->name), 'id' => $related->id]) }}">
                        @php $relUrls = $related->imageUrls(); @endphp
                        @if (count($relUrls) > 0)
                            <img src="{{ $relUrls[0] }}" class="card-img-top w-100" alt="{{ $related->name }}" loading="lazy" style="aspect-ratio: 4 / 3; object-fit: cover;">
                        @else
                            <div class="card-img-top w-100 d-flex align-items-center justify-content-center" style="aspect-ratio: 4 / 3; background-color: #2b3035;">
                                <i class="bi bi-image text-secondary" style="font-size: 2rem;"></i>
                            </div>
                        @endif
                    </a>
                    <div class="card-body p-3 text-center">
                        <h6 class="card-title text-truncate mb-1" style="font-size: 0.9rem;">
                            <a href="{{ route('site.product', ['slug' => \Illuminate\Support\Str::slug($related->name), 'id' => $related->id]) }}" class="text-white text-decoration-none">{{ $related->name }}</a>
                        </h6>
                        <small class="text-muted d-block mb-2">{{ $related->reference }}</small>
                        <a href="{{ route('site.product', ['slug' => \Illuminate\Support\Str::slug($related->name), 'id' => $related->id]) }}" class="btn btn-sm btn-outline-danger w-100" style="border-radius: 20px;">Ver más</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

