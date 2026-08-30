@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">Pedidos</h2><h5 class="text-white op-7 mb-2">{{ $order->exists ? 'Editar pedido' : 'Crear pedido' }}</h5></div>
@endsection
@section('contenido')
    <div class="page-inner mt--5"><div class="card"><div class="card-body">
        <form action="{{ $order->exists ? route('pedidos.update', $order) : route('pedidos.store') }}" method="POST">
            @csrf
            @if ($order->exists) @method('PUT') @endif
            <div class="row">
                <div class="form-group col-md-4"><label>Código</label><input name="code" class="form-control" value="{{ old('code', $order->code) }}" placeholder="Automatico si se deja vacio"></div>
                <div class="form-group col-md-4"><label>Cliente</label><select name="client_id" class="form-control" required><option value="">Seleccionar</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id', $order->client_id) == $client->id)>{{ $client->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label>Fecha</label><input type="date" name="ordered_at" class="form-control" value="{{ old('ordered_at', optional($order->ordered_at)->format('Y-m-d') ?: now()->format('Y-m-d')) }}"></div>
                <div class="form-group col-md-12"><label>Observaciones</label><textarea name="notes" class="form-control">{{ old('notes', $order->notes) }}</textarea></div>
            </div>
            <h4>Productos</h4>
            <div class="table-responsive">
                <table class="table" id="items-table">
                    <thead><tr><th>Producto</th><th style="width: 120px">Cantidad</th><th style="width: 160px">Precio</th><th style="width: 120px"></th></tr></thead>
                    <tbody>
                        @php
                            $oldItems = old('items');
                            $formItems = $oldItems ?: ($items->count() ? $items->map(fn($item) => ['product_id' => $item->product_id, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price])->toArray() : [['product_id' => '', 'quantity' => 1, 'unit_price' => 0]]);
                        @endphp
                        @foreach ($formItems as $index => $item)
                            <tr>
                                <td><select name="items[{{ $index }}][product_id]" class="form-control product-select" required><option value="">Seleccionar</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->price ?? 0 }}" @selected(($item['product_id'] ?? '') == $product->id)>{{ $product->name }}</option>@endforeach</select></td>
                                <td><input type="number" min="1" name="items[{{ $index }}][quantity]" class="form-control" value="{{ $item['quantity'] ?? 1 }}" required></td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" class="form-control price-input" value="{{ $item['unit_price'] ?? 0 }}" required></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row">Quitar</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-info btn-sm" id="add-row">Agregar producto</button>
            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('pedidos.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div></div></div>
@endsection
@push('scripts')
<script>
    const products = @json($products->map(fn($product) => ['id' => $product->id, 'name' => $product->name, 'price' => $product->price ?? 0])->values());
    let rowIndex = document.querySelectorAll('#items-table tbody tr').length;

    function productOptions() {
        return '<option value="">Seleccionar</option>' + products.map(product => `<option value="${product.id}" data-price="${product.price}">${product.name}</option>`).join('');
    }

    document.getElementById('add-row').addEventListener('click', function () {
        const tbody = document.querySelector('#items-table tbody');
        const row = document.createElement('tr');
        row.innerHTML = `<td><select name="items[${rowIndex}][product_id]" class="form-control product-select" required>${productOptions()}</select></td><td><input type="number" min="1" name="items[${rowIndex}][quantity]" class="form-control" value="1" required></td><td><input type="number" step="0.01" min="0" name="items[${rowIndex}][unit_price]" class="form-control price-input" value="0" required></td><td><button type="button" class="btn btn-danger btn-sm remove-row">Quitar</button></td>`;
        tbody.appendChild(row);
        rowIndex++;
    });

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('product-select')) {
            const price = event.target.selectedOptions[0].dataset.price || 0;
            event.target.closest('tr').querySelector('.price-input').value = price;
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-row') && document.querySelectorAll('#items-table tbody tr').length > 1) {
            event.target.closest('tr').remove();
        }
    });
</script>
@endpush
