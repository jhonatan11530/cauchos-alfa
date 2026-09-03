<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 12px; }
        h1 { color: #123f73; margin-bottom: 4px; }
        h2 { border-bottom: 1px solid #ddd; padding-bottom: 6px; color: #123f73; }
        table { border-collapse: collapse; width: 100%; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 7px; vertical-align: top; }
        th { background: #f0f4f8; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h1>{{ $catalog->company_name ?: 'Catálogo' }}</h1>
    <p class="muted">{{ $catalog->contact_email }} {{ $catalog->contact_phone }} {{ $catalog->contact_address }}</p>
    <h2>{{ $catalog->name }}</h2>
    <p>{{ $catalog->description }}</p>
    <table>
        <thead><tr><th>Producto</th><th>Referencia</th><th>Categoría</th><th>Descripción</th></tr></thead>
        <tbody>
            @foreach ($catalog->products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->reference }}</td>
                    <td>{{ $product->category->name ?? '' }}</td>
                    <td>{{ $product->description }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>{{ $catalog->notes }}</p>
</body>
</html>
