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
        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .text-red {
            color: #e60012;
        }

        .text-white {
            color: #ffffff;
        }

        .text-grey {
            color: #888888;
        }

        .w-100 {
            width: 100%;
        }

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
            padding: 22px 26px;
            page-break-before: always;
        }

        .contact-page {
            padding: 40px 40px;
            page-break-before: always;
        }

        .page-header {
            margin-bottom: 10px;
            border-bottom: 1px solid #232327;
            padding-bottom: 6px;
        }

        .section-title {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 3px 0;
            color: #ffffff;
        }

        .section-subtitle {
            font-size: 10px;
            color: #888888;
            margin: 0;
        }

        .page-badge {
            display: inline-block;
            background-color: #1c1c20;
            border: 1px solid #333338;
            color: #e60012;
            font-size: 9px;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* Grilla 2x3 de Productos (6 por página, diseño vertical) */
        .grid-table {
            width: 100%;
            table-layout: fixed;
        }

        .grid-row {
            page-break-inside: avoid;
        }

        .grid-cell {
            width: 50%;
            padding: 4px;
            vertical-align: top;
            box-sizing: border-box;
        }

        .grid-cell-empty {
            width: 50%;
            padding: 4px;
        }

        /* Tarjeta de Producto Vertical */
        .product-card {
            background-color: #161618;
            border: 1px solid #26262a;
            border-radius: 8px;
            overflow: hidden;
            text-align: center;
            height: 300px;
        }

        .product-category-badge {
            background-color: #1c1c20;
            border-left: 3px solid #e60012;
            color: #e60012;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2.5px 8px;
            text-align: left;
            height: 12px;
            line-height: 12px;
            overflow: hidden;
            white-space: nowrap;
        }

        .product-img-wrapper {
            height: 182px;
            background-color: #ffffff;
            text-align: center;
            vertical-align: middle;
            padding: 3px;
        }

        .product-img {
            max-height: 175px;
            max-width: 95%;
            display: inline-block;
            vertical-align: middle;
            margin: 0 auto;
        }

        .product-body {
            padding: 5px 8px 4px 8px;
            text-align: center;
        }

        .product-name {
            font-size: auto;
            font-weight: bold;
            color: #ffffff;
            margin: 0 0 3px 0;
            line-height: 1.2;
            text-transform: uppercase;
            height: 100vh;
            overflow: hidden;
        }

        .product-ref {
            font-size: auto;
            font-weight: bold;
            color: #e60012;
            margin-bottom: 2px;
            display: block;
            height: 12px;
            overflow: hidden;
        }

        .product-desc {
            font-size: 7.5px;
            color: #aaaaaa;
            line-height: 1.2;
            height: 16px;
            overflow: hidden;
            margin-top: 1px;
            height: auto;
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
            margin-top: 8px;
            text-align: center;
            color: #666666;
            font-size: 9px;
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
                {{ $catalog->contact_email ?: 'hola@empresa.com' }} ·
                {{ $catalog->contact_phone ?: '+57 123 456 7890' }}
            </div>
        </div>
    </div>

    {{-- Páginas de Productos (6 por página en grilla 2x3) --}}
    @php
        if (isset($orderedProducts)) {
            $items = collect($orderedProducts)->values();
        } elseif (isset($productsByCategory)) {
            $items = collect($productsByCategory)->flatten(1)->values();
        } else {
            $items = $catalog->orderedProductsForPdf();
        }

        if (isset($productsByCategory) && $productsByCategory instanceof \Illuminate\Support\Collection) {
            $categoriesGrouped = $productsByCategory;
        } else {
            $categoriesGrouped = $items->groupBy(fn($p) => $p->category?->name ?? 'Varios');
        }

        $pages = collect();
        foreach ($categoriesGrouped as $catName => $catProducts) {
            $catChunks = collect($catProducts)->chunk(6);
            $totalCatPages = $catChunks->count();
            foreach ($catChunks as $chunkIdx => $chunkProducts) {
                $pages->push([
                    'categoryName' => $catName,
                    'products' => $chunkProducts,
                    'categoryPage' => $chunkIdx + 1,
                    'totalCategoryPages' => $totalCatPages,
                ]);
            }
        }
        $totalProductPages = $pages->count();
    @endphp

    @foreach ($pages as $pageData)
        @php
            $pageProducts = $pageData['products'];
            $categoryName = $pageData['categoryName'];
        @endphp
        <div class="page">
            <div class="page-header">
                <table class="w-100">
                    <tr>
                        <td class="text-left" style="vertical-align: middle;">
                            <h2 class="section-title">{{ mb_strtoupper($catalog->name) }}</h2>
                            <div class="section-subtitle">
                                {{ mb_strtoupper($categoryName) }}
                                @if ($pageData['totalCategoryPages'] > 1)
                                    &bull; PARTE {{ $pageData['categoryPage'] }} / {{ $pageData['totalCategoryPages'] }}
                                @endif
                            </div>
                        </td>
                        <td class="text-right" style="vertical-align: middle; width: 120px;">
                            <span class="page-badge">PÁGINA {{ $loop->iteration }} / {{ $totalProductPages }}</span>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="grid-table">
                @foreach ($pageProducts->chunk(2) as $row)
                    <tr class="grid-row">
                        @foreach ($row as $product)
                            @php
                                $imageDataUri = $product->primaryImageDataUri();
                                $categoryName = $product->category?->name ?? 'Varios';
                            @endphp
                            <td class="grid-cell">
                                <div class="product-card">
                                    <div class="product-category-badge">{{ mb_strtoupper($categoryName) }}</div>
                                    <div class="product-img-wrapper">
                                        <table style="width: 100%; height: 100%;">
                                            <tr>
                                                <td style="vertical-align: middle; text-align: center;">
                                                    @if ($imageDataUri)
                                                        <img src="{{ $imageDataUri }}" alt="{{ $product->name }}" class="product-img">
                                                    @else
                                                        <span style="color:#888888; font-size: 9px;">Sin imagen</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="product-body">
                                        <div class="product-name">{{ $product->name }}</div>
                                        <div class="product-ref">REF: {{ $product->reference ?: 'N/A' }}</div>
                                        @if ($product->description)
                                            <div class="product-desc">{{ Str::limit($product->description, 150) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        @endforeach

                        @for ($i = 0; $i < (2 - $row->count()); $i++)
                            <td class="grid-cell-empty">&nbsp;</td>
                        @endfor
                    </tr>
                @endforeach
            </table>

            <div class="footer-note">Precios y disponibilidad sujetos a cambio.</div>
        </div>
    @endforeach

    {{-- Especificaciones / Contacto --}}
    <div class="contact-page">
        <h2 class="section-title" style="font-size: 32px; margin-bottom: 5px;">Contáctenos.</h2>
        @if ($items->isEmpty())
            <p class="text-grey" style="margin-top: 8px;">Este catálogo no tiene productos registrados actualmente.</p>
        @endif

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
            <p class="cta-text">Contáctanos en: {{ $catalog->contact_email ?: 'hola@empresa.com' }} ·
                {{ $catalog->contact_phone ?: '+57 123 456 7890' }}</p>
        </div>
    </div>

</body>
</html>
