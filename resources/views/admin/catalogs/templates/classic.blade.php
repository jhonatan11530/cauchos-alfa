<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $catalog->name }} - {{ $catalog->company_name ?: 'Cauchos Alfa' }}</title>
    <style>
        @page { margin: 15mm; }
        body {
            font-family: 'Courier New', Courier, monospace;
            color: #000;
            font-size: 11px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: middle; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .company-title { font-size: 24px; font-weight: bold; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .category-title { font-size: 14px; font-weight: bold; margin-top: 20px; margin-bottom: 10px; background: #000; color: #fff; padding: 4px; }
        .product-img { max-height: 40px; max-width: 60px; object-fit: contain; }
    </style>
</head>
<body>
    <div class="company-title">{{ $catalog->company_name ?: 'CAUCHOS ALFA' }} - LISTA DE PRECIOS / PRODUCTOS</div>

    @php
        $grouped = isset($productsByCategory) ? $productsByCategory : $catalog->products->groupBy(fn($p) => $p->category?->name ?? 'Varios');
    @endphp

    @forelse ($grouped as $categoryName => $products)
        <div class="category-title">{{ $categoryName }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Imagen</th>
                    <th style="width: 25%;">Referencia</th>
                    <th style="width: 60%;">Descripción</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $product)
                <tr>
                    <td style="text-align: center;">
                        @if ($product->primaryImageDataUri())
                            <img src="{{ $product->primaryImageDataUri() }}" class="product-img">
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $product->reference ?: 'N/A' }}</td>
                    <td><strong>{{ $product->name }}</strong><br>{{ Str::limit($product->description, 80) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div>No hay productos.</div>
    @endforelse
</body>
</html>

