{{-- Galería de producto: carousel si hay varias imágenes, imagen simple si hay una --}}
@php
    $urls = $product->imageUrls();
@endphp

@if (count($urls) > 1)
    <div id="gallery-{{ $product->id }}" class="carousel slide carousel-fade" data-bs-ride="carousel">
        <div class="carousel-inner">
            @foreach ($urls as $i => $url)
                <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                    <img src="{{ $url }}" class="d-block card-img-top"
                        alt="{{ $product->name }} - imagen {{ $i + 1 }}"
                        style="aspect-ratio: 4 / 3; object-fit: cover;">
                </div>
            @endforeach
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#gallery-{{ $product->id }}"
            data-bs-slide="prev">
            <span class="carousel-control-prev-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#gallery-{{ $product->id }}"
            data-bs-slide="next">
            <span class="carousel-control-next-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
        </button>
        <div class="carousel-indicators">
            @foreach ($urls as $i => $url)
                <button type="button" data-bs-target="#gallery-{{ $product->id }}"
                    data-bs-slide-to="{{ $i }}" class="{{ $i === 0 ? 'active' : '' }}"
                    aria-label="Imagen {{ $i + 1 }}"></button>
            @endforeach
        </div>
    </div>
@elseif (count($urls) === 1)
    <img src="{{ $urls[0] }}" class="card-img-top" alt="{{ $product->name }}"
        style="aspect-ratio: 4 / 3; object-fit: cover;">
@else
    <img src="https://via.placeholder.com/400x300?text=Producto" class="card-img-top" alt="{{ $product->name }}"
        style="aspect-ratio: 4 / 3; object-fit: cover;">
@endif
