<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Manifiesto de Despacho - Cauchos Alfa - {{ $selectedDate }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .meta {
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #777;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .signature-box {
            height: 45px;
            border-bottom: 1px dotted #333;
            margin-top: 15px;
        }
        .btn-print {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir Manifiesto</button>
        <button class="btn-print" style="background: #6c757d;" onclick="window.close()">Cerrar</button>
    </div>

    <div class="header">
        <div>
            <div class="title">CAUCHOS ALFA — MANIFIESTO DE ENTREGA Y DESPACHO</div>
            <div>Distribución y Logística de Pedidos</div>
        </div>
        <div class="meta" style="text-align: right;">
            <div><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }}</div>
            <div><strong>Total Pedidos:</strong> {{ $totalOrders }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 14%;">Pedido / Factura</th>
                <th style="width: 20%;">Cliente & Teléfono</th>
                <th style="width: 22%;">Dirección / Ciudad</th>
                <th style="width: 25%;">Productos a Entregar</th>
                <th style="width: 15%;">Recibido Conforme</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $index => $order)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $order->code }}</strong>
                        <div style="font-size: 10px; color: #555;">{{ $order->billing_type == 'factura_electronica' ? 'Factura Electrónica' : 'Remisión' }}</div>
                    </td>
                    <td>
                        <strong>{{ $order->client?->name }}</strong>
                        <div style="font-size: 11px;">Tel: {{ $order->client?->phone ?: 'No registra' }}</div>
                    </td>
                    <td>
                        {{ $order->client?->address ?: 'Sin dirección' }}
                        @if ($order->client?->city)
                            <div><strong>{{ $order->client->city }}</strong></div>
                        @endif
                    </td>
                    <td>
                        <ul style="margin: 0; padding-left: 15px;">
                            @foreach ($order->items as $item)
                                <li>{{ $item->quantity }}x {{ $item->product?->name }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>
                        <div style="font-size: 9px; color: #777;">Firma / C.C.:</div>
                        <div class="signature-box"></div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">
                        No hay pedidos en estado "Pedido despachado" para la fecha seleccionada.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 40px; display: flex; justify-content: space-between;">
        <div style="width: 45%; text-align: center;">
            <div style="border-top: 1px solid #000; padding-top: 5px;">Firma Responsable de Despacho (Bodega)</div>
        </div>
        <div style="width: 45%; text-align: center;">
            <div style="border-top: 1px solid #000; padding-top: 5px;">Firma Transportador / Conductor</div>
        </div>
    </div>
</body>
</html>
