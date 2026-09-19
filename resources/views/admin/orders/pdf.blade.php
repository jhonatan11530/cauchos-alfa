<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido {{ $pedido->code }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; margin: 0; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; vertical-align: top; }
        .details-title { font-weight: bold; font-size: 16px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px;}
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table.items th { background-color: #f4f4f4; }
        .text-center { text-align: center; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">ORDEN DE PEDIDO</h1>
        <p style="margin: 5px 0 0 0; color: #555;">{{ config('app.name', 'Cauchos Alfa') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="50%">
                <div class="details-title">Datos del Pedido</div>
                <p><strong>Nº Pedido:</strong> {{ $pedido->code }}</p>
                <p><strong>Fecha:</strong> {{ $pedido->ordered_at ? $pedido->ordered_at->format('d/m/Y') : $pedido->created_at->format('d/m/Y') }}</p>
                <p><strong>Tipo de Facturación:</strong> {{ $pedido->billing_type == 'factura_electronica' ? 'Factura Electrónica' : 'Remisión' }}</p>
                <p><strong>Vendedor:</strong> {{ $pedido->creator->name ?? 'N/A' }}</p>
            </td>
            <td width="50%">
                <div class="details-title">Datos del Cliente</div>
                <p><strong>Nombre:</strong> {{ $pedido->client->name }}</p>
                <p><strong>Identificación:</strong> {{ $pedido->client->document_type ?? '' }} {{ $pedido->client->document_number ?? 'No registrada' }}</p>
                <p><strong>Teléfono:</strong> {{ $pedido->client->phone ?? 'No registrado' }}</p>
                <p><strong>Dirección:</strong> {{ $pedido->client->address ?? 'No registrada' }}</p>
            </td>
        </tr>
    </table>

    <div class="details-title" style="margin-top: 20px;">Productos Solicitados</div>
    <table class="items">
        <thead>
            <tr>
                <th width="10%">Item</th>
                <th width="70%">Descripción del Producto</th>
                <th width="20%" class="text-center">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->product->name }}</strong>
                    @if($item->product->reference)
                        <br><small>Ref: {{ $item->product->reference }}</small>
                    @endif
                </td>
                <td class="text-center">{{ $item->quantity }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($pedido->notes)
    <div style="margin-top: 30px;">
        <div class="details-title">Observaciones</div>
        <p style="font-size: 13px; color: #444;">{{ $pedido->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Este documento es una orden de pedido. {{ $pedido->billing_type == 'factura_electronica' ? 'Se emitirá Factura Electrónica para esta transacción.' : 'Documento generado como Remisión.' }}</p>
    </div>
</body>
</html>

