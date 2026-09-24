<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $catalog->name }} | {{ $catalog->company_name ?: 'Cauchos Alfa' }}</title>
    <style>
        @page {
            margin: 0;
            background-color: #0a0a0b;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #ffffff;
            font-size: 12px;
            background-color: #0a0a0b;
            margin: 0;
            padding: 0;
        }

        /* Utilidades */
        .text-center { text-align: center; }
        .text-red { color: #e60012; }
        .text-white { color: #ffffff; }
        .text-grey { color: #888888; }
        .w-100 { width: 100%; }

        table {
            border-collapse: collapse;
            border-spacing: 0;
            width: 100%;
        }

        /* --- PORTADA --- */
        .cover-page {
            padding: 50px 30px 0 30px;
            text-align: center;
        }
        .cover-meta {
            color: #e60012;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 80px;
            margin-bottom: 10px;
        }
        .cover-title {
            font-size: 48px;
            font-weight: bold;
            color: #ffffff;
            margin: 0;
        }
        .cover-subtitle {
            font-size: 18px;
            color: #888888;
            margin-top: 10px;
        }
        .cover-image-container {
            margin-top: 80px;
            height: 400px;
        }
        .cover-image {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
        }
        .cover-footer {
            position: absolute;
            bottom: 50px;
            left: 0;
            right: 0;
            text-align: center;
        }
        .cover-line {
            width: 50px;
            height: 3px;
            background-color: #e60012;
            margin: 0 auto 15px auto;
        }
        .cover-contact {
            color: #888888;
            font-size: 12px;
        }

        /* --- PÁGINAS INTERNAS --- */
        .page {
            padding: 40px 40px;
            page-break-before: always;
        }

        .section-title {
            font-size: 32px;
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #ffffff;
        }
        .section-subtitle {
            font-size: 14px;
            color: #888888;
            margin-bottom: 30px;
        }

        /* Cards de Producto */
        .product-card {
            background-color: #161618;
            border-radius: 12px;
            margin-bottom: 25px;
            overflow: hidden;
            width: 100%;
        }

        .product-card td {
            vertical-align: middle;
        }

        .product-card-img-td {
            width: 45%;
            background-color: #f5f5f5; /* Fondo claro para que la bici destaque */
            text-align: center;
            padding: 20px;
        }

        .product-img {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }

        .product-card-info-td {
            width: 55%;
            padding: 30px;
        }

        .product-category {
            color: #e60012;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .product-name {
            font-size: 26px;
            font-weight: bold;
            color: #ffffff;
            margin: 0 0 10px 0;
        }

        .product-desc {
            font-size: 12px;
            color: #aaaaaa;
            line-height: 1.4;
            margin-bottom: 25px;
        }

        .product-price {
            font-size: 20px;
            font-weight: bold;
            color: #ffffff;
        }

        /* Detalles / Specs */
        .specs-table {
            width: 100%;
            margin-top: 30px;
        }
        .specs-table td {
            padding: 15px 0;
            border-bottom: 1px solid #333333;
            color: #dddddd;
            font-size: 14px;
        }
        .specs-table td.spec-label {
            color: #888888;
            width: 30%;
        }
        .specs-table td.spec-value {
            font-weight: bold;
            text-align: right;
            width: 70%;
        }

        /* CTA Bottom Box */
        .cta-box {
            background-color: #e60012;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            margin-top: 60px;
            color: #ffffff;
        }
        .cta-title {
            font-size: 28px;
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        .cta-text {
            font-size: 14px;
            margin: 0;
        }

        .footer-note {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            color: #555555;
            font-size: 10px;
        }
    </style>
</head>
<body>

    {{-- Portada --}}
    <div class="cover-page">
        <div class="cover-meta">{{ $catalog->name }}</div>
        <h1 class="cover-title">{{ $catalog->company_name ?: 'CAUCHOS ALFA' }}</h1>
        <div class="cover-subtitle">{{ $catalog->description ?: 'Diseñada para lo extraordinario.' }}</div>

        <div class="cover-image-container">
            @if($catalog->logoDataUri())
                <img src="{{ $catalog->logoDataUri() }}" alt="Logo" class="cover-image">
            @else
                <div style="height: 400px; display:table; width:100%;">
                    <div style="display:table-cell; vertical-align:middle; color:#333;">SIN IMAGEN PRINCIPAL</div>
                </div>
            @endif
        </div>

        <div class="cover-footer">
            <div class="cover-line"></div>
            <div class="cover-contact">
                {{ $catalog->contact_email ?: 'hola@empresa.com' }} · {{ $catalog->contact_phone ?: '+57 123 456 7890' }}
            </div>
        </div>
    </div>

    {{-- Productos por Categoría --}}
    @php
        $grouped = isset($productsByCategory) ? $productsByCategory : $catalog->products->groupBy(fn($p) => $p->category?->name ?? 'Varios');
    @endphp

    @foreach ($grouped as $categoryName => $products)
    <div class="page">
        <h2 class="section-title">{{ mb_strtoupper($categoryName) }}</h2>
        @php
            $firstProduct = $products->first();
            $catDesc = $firstProduct && $firstProduct->category ? $firstProduct->category->description : '';
        @endphp
        @if($catDesc)
            <div class="section-subtitle">{{ $catDesc }}</div>
        @else
            <div class="section-subtitle">Modelos exclusivos, una misma obsesión por el detalle.</div>
        @endif

        @foreach ($products as $product)
                <table class="product-card">
                    <tr>
                        <td class="product-card-img-td">
                            @if ($product->primaryImageDataUri())
                                <img src="{{ $product->primaryImageDataUri() }}" alt="{{ $product->name }}" class="product-img">
                            @else
                                <span style="color:#aaa;">Sin imagen</span>
                            @endif
                        </td>
                        <td class="product-card-info-td">
                            <div class="product-category">{{ mb_strtoupper($categoryName) }} - REF: {{ $product->reference ?: 'N/A' }}</div>
                            <h3 class="product-name">{{ $product->name }}</h3>
                            <div class="product-desc">{{ Str::limit($product->description, 120) }}</div>

                            @if($product->price > 0)
                                <div class="product-price">${{ number_format($product->price, 0, ',', '.') }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            @endforeach

            <div class="footer-note">Precios sujetos a cambio.</div>
        </div>
    @endforeach

    {{-- Especificaciones / Contacto --}}
    <div class="page">
        <h2 class="section-title">Contactenos.</h2>

        <table class="specs-table">
            @if($catalog->contact_email)
            <tr>
                <td class="spec-label">Correo de contacto</td>
                <td class="spec-value">{{ $catalog->contact_email }}</td>
            </tr>
            @endif

            @if($catalog->contact_phone)
            <tr>
                <td class="spec-label">Teléfono</td>
                <td class="spec-value">{{ $catalog->contact_phone }}</td>
            </tr>
            @endif

            @if($catalog->contact_address)
            <tr>
                <td class="spec-label">Dirección</td>
                <td class="spec-value">{{ $catalog->contact_address }}</td>
            </tr>
            @endif

            @if($catalog->notes)
            <tr>
                <td class="spec-label">Notas Comerciales</td>
                <td class="spec-value" style="font-weight: normal;">{{ $catalog->notes }}</td>
            </tr>
            @endif
        </table>

        <div class="cta-box">
            <h3 class="cta-title">¿Lista para rodar?</h3>
            <p class="cta-text">Contáctanos en: {{ $catalog->contact_email ?: 'hola@empresa.com' }} · {{ $catalog->contact_phone ?: '+57 123 456 7890' }}</p>
        </div>
    </div>

</body>
</html>
