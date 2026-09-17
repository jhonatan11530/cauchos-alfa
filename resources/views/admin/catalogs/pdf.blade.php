<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $catalog->name }} - {{ $catalog->company_name ?: 'Cauchos Alfa' }}</title>
    <style>
        @page {
            margin: 8mm 8mm 12mm 8mm;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #1a1a1a;
            font-size: 9px;
            line-height: 1.25;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        .w-100 { width: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .uppercase { text-transform: uppercase; }

        /* Cabecera Principal */
        .header-container {
            border: 2px solid #e60012;
            border-radius: 6px;
            margin-bottom: 8px;
            background: #ffffff;
            overflow: hidden;
        }

        .header-top {
            padding: 8px 12px 6px 12px;
            border-bottom: 1px solid #ececec;
        }

        .company-title {
            color: #e60012;
            font-size: 19px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin: 0;
            line-height: 1.1;
        }

        .company-subtitle {
            color: #141414;
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .company-tagline {
            display: inline-block;
            background-color: #141414;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            margin-top: 3px;
        }

        .catalog-meta-title {
            font-size: 11px;
            font-weight: bold;
            color: #141414;
            margin: 0 0 2px 0;
        }

        .catalog-meta-desc {
            font-size: 8px;
            color: #666666;
            margin: 0;
        }

        .logo-img {
            max-height: 48px;
            max-width: 130px;
            display: block;
        }

        /* Cinta de contacto y servicio */
        .service-banner {
            background-color: #e60012;
            color: #ffffff;
            font-size: 7px;
            font-weight: bold;
            text-align: center;
            padding: 3px 6px;
            letter-spacing: 0.5px;
        }

        .contact-strip {
            background-color: #f7f7f8;
            font-size: 7px;
            color: #333333;
            padding: 3px 10px;
            text-align: center;
            border-top: 1px solid #eeeeee;
        }

        .contact-item {
            display: inline-block;
            margin: 0 5px;
        }

        .contact-item strong {
            color: #141414;
        }

        /* Banner de Categoría */
        .category-wrapper {
            margin-top: 6px;
            margin-bottom: 4px;
            page-break-after: avoid;
            page-break-inside: avoid;
        }

        .category-banner {
            background-color: #141414;
            border-left: 4px solid #e60012;
            color: #ffffff;
            padding: 3px 8px;
            border-radius: 3px;
        }

        .category-name {
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #ffffff;
            text-transform: uppercase;
        }

        .category-count {
            font-size: 7.5px;
            color: #cccccc;
            text-align: right;
        }

        /* Grilla de Productos */
        .grid-table {
            width: 100%;
            table-layout: fixed;
            margin-bottom: 4px;
        }

        .grid-row {
            page-break-inside: avoid;
        }

        .grid-cell {
            width: 25%;
            padding: 2.5px;
            vertical-align: top;
            box-sizing: border-box;
        }

        .grid-cell-empty {
            width: 25%;
            padding: 2.5px;
        }

        /* Tarjeta de Producto */
        .product-card {
            border: 1px solid #dcdcdc;
            border-radius: 4px;
            background-color: #ffffff;
            overflow: hidden;
            text-align: center;
            height: 150px;
        }

        .product-img-wrapper {
            height: 78px;
            background-color: #fafafa;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
            vertical-align: middle;
            padding: 2px;
        }

        .product-img {
            max-height: 72px;
            max-width: 92%;
            display: inline-block;
            vertical-align: middle;
            margin: 0 auto;
        }

        .no-img-placeholder {
            height: 72px;
            display: table;
            width: 100%;
        }

        .no-img-text {
            display: table-cell;
            vertical-align: middle;
            font-size: 7.5px;
            color: #aaaaaa;
            font-weight: bold;
            text-transform: uppercase;
        }

        .product-body {
            padding: 3px 4px 4px 4px;
            text-align: center;
        }

        .product-name {
            font-size: 8px;
            font-weight: bold;
            color: #111111;
            margin: 0 0 2px 0;
            line-height: 1.15;
            text-transform: uppercase;
            height: 22px;
            overflow: hidden;
        }

        .product-ref {
            font-size: 7px;
            font-weight: bold;
            color: #e60012;
            margin-bottom: 2px;
            display: block;
        }

        .product-desc {
            font-size: 6.5px;
            color: #555555;
            line-height: 1.1;
            height: 20px;
            overflow: hidden;
            margin-top: 1px;
        }

        /* Notas del catálogo */
        .catalog-notes {
            margin-top: 10px;
            padding: 5px 8px;
            background-color: #f9f9f9;
            border-left: 3px solid #666666;
            font-size: 7.5px;
            color: #444444;
            page-break-inside: avoid;
        }

        .catalog-notes-title {
            font-weight: bold;
            margin-bottom: 2px;
            color: #111111;
        }

        /* Pie de página fijo en Dompdf */
        .footer {
            position: fixed;
            bottom: -9mm;
            left: 0;
            right: 0;
            height: 14px;
            font-size: 7px;
            color: #666666;
            border-top: 1px solid #e0e0e0;
            padding-top: 2px;
        }
    </style>
</head>
<body>

    {{-- Pie de página repetido en cada hoja --}}
    <div class="footer">
        <table class="w-100">
            <tr>
                <td class="text-left" style="color: #666666;">
                    Cauchos Alfa &bull; Catálogo de Productos
                </td>
                <td class="text-right" style="color: #666666;">
                    Emisión: {{ date('d/m/Y') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Cabecera Principal del Catálogo --}}
    <div class="header-container">
        <div class="header-top">
            <table class="w-100">
                <tr>
                    <td class="text-left" style="vertical-align: middle; width: 65%;">
                        <h1 class="company-title">{{ $catalog->company_name ?: 'CAUCHOS ALFA' }}</h1>
                        <div class="company-subtitle">CAUCHOS PARA MOTOS Y BICICLETAS</div>
                        <div><span class="company-tagline">LOS MEJORES CAUCHOS DEL MERCADO</span></div>
                    </td>
                    <td class="text-right" style="vertical-align: middle; width: 35%;">
                        @php
                            $logoUri = $catalog->logoDataUri();
                        @endphp
                        @if ($logoUri)
                            <img src="{{ $logoUri }}" alt="Logo" class="logo-img" style="float: right;">
                        @else
                            <div class="catalog-meta-title">{{ $catalog->name }}</div>
                            @if ($catalog->description)
                                <div class="catalog-meta-desc">{{ Str::limit($catalog->description, 90) }}</div>
                            @endif
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="service-banner">
            VENTAS POR MAYOR Y DETAL &bull; CONTACTO DIRECTO &bull; SERVIMOS CON CALIDAD
        </div>

        <div class="contact-strip">
            <span class="contact-item">
                <strong>Tel:</strong> {{ $catalog->contact_phone ?: '304 3210 / 326 2376' }}
            </span>
            <span class="contact-item">&bull;</span>
            <span class="contact-item">
                <strong>Email:</strong> {{ $catalog->contact_email ?: 'cauchosalfa@hotmail.com' }}
            </span>
            <span class="contact-item">&bull;</span>
            <span class="contact-item">
                <strong>Dirección:</strong> {{ $catalog->contact_address ?: 'Cra 39 No. 26 A 52 B/ La Independencia - Cali' }}
            </span>
        </div>
    </div>

    {{-- Agrupación por Categorías y Grilla de Productos --}}
    @php
        $grouped = isset($productsByCategory)
            ? $productsByCategory
            : $catalog->products->groupBy(fn($p) => $p->category?->name ?? 'Otros Productos');
    @endphp

    @forelse ($grouped as $categoryName => $products)
        <div class="category-wrapper">
            <div class="category-banner">
                <table class="w-100">
                    <tr>
                        <td class="category-name text-left">
                            {{ $categoryName }}
                        </td>
                        <td class="category-count text-right">
                            {{ $products->count() }} {{ $products->count() === 1 ? 'PRODUCTO' : 'PRODUCTOS' }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <table class="grid-table">
            @foreach ($products->chunk(4) as $row)
                <tr class="grid-row">
                    @foreach ($row as $product)
                        @php
                            $imageDataUri = $product->primaryImageDataUri();
                        @endphp
                        <td class="grid-cell">
                            <div class="product-card">
                                <div class="product-img-wrapper">
                                    <table style="width: 100%; height: 100%;">
                                        <tr>
                                            <td style="vertical-align: middle; text-align: center;">
                                                @if ($imageDataUri)
                                                    <img src="{{ $imageDataUri }}" alt="{{ $product->name }}" class="product-img">
                                                @else
                                                    <div class="no-img-placeholder">
                                                        <span class="no-img-text">Sin Imagen</span>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="product-body">
                                    <div class="product-name" title="{{ $product->name }}">
                                        {{ $product->name }}
                                    </div>

                                    @if ($product->reference)
                                        <div class="product-ref">
                                            REF: {{ $product->reference }}
                                        </div>
                                    @endif

                                    @if ($product->description)
                                        <div class="product-desc">
                                            {{ Str::limit($product->description, 50) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    @endforeach

                    @for ($i = 0; $i < (4 - $row->count()); $i++)
                        <td class="grid-cell-empty">&nbsp;</td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @empty
        <div style="padding: 30px; text-align: center; color: #888888; font-size: 11px;">
            Este catálogo no tiene productos registrados actualmente.
        </div>
    @endforelse

    {{-- Notas adicionales del catálogo --}}
    @if ($catalog->notes)
        <div class="catalog-notes">
            <div class="catalog-notes-title">Notas / Condiciones Comerciales:</div>
            <div>{{ $catalog->notes }}</div>
        </div>
    @endif

</body>
</html>
