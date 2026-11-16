@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">Pedidos</h2><h5 class="text-white op-7 mb-2">{{ $order->exists ? 'Editar pedido' : 'Crear pedido' }}</h5></div>
@endsection
@section('contenido')
    <style>
        .select2-container .select2-selection--single {
            height: calc(2.25rem + 2px) !important;
            padding: 0.375rem 0.5rem;
            border: 1px solid #ebedf2 !important;
            border-radius: 0.25rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(2.25rem + 2px) !important;
        }
    </style>
    <div class="page-inner mt--5"><div class="card"><div class="card-body">
        <form id="order-form" action="{{ $order->exists ? route('pedidos.update', $order) : route('pedidos.store') }}" method="POST">
            @csrf
            @if ($order->exists) @method('PUT') @endif
            <div class="row">
                <div class="form-group col-md-3">
                    <label>Código</label>
                    <input name="code" class="form-control" value="{{ old('code', $order->code) }}" placeholder="Automatico si se deja vacio">
                </div>
                <div class="form-group col-md-3">
                    <label>Cliente</label>
                    <select name="client_id" id="client-select" class="form-control" required>
                        <option value="">Seleccionar cliente...</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id', $order->client_id) == $client->id)>
                                {{ $client->name }}{{ $client->document ? ' (' . $client->document . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Tipo de Facturación</label>
                    <select name="billing_type" class="form-control" required>
                        <option value="remision" @selected(old('billing_type', $order->billing_type ?? 'remision') == 'remision')>Remisión</option>
                        <option value="factura_electronica" @selected(old('billing_type', $order->billing_type ?? 'remision') == 'factura_electronica')>Factura Electrónica</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Fecha</label>
                    <input type="date" name="ordered_at" class="form-control" value="{{ old('ordered_at', optional($order->ordered_at)->format('Y-m-d') ?: now()->format('Y-m-d')) }}">
                </div>
                <div class="form-group col-md-12">
                    <label>Observaciones</label>
                    <textarea name="notes" class="form-control">{{ old('notes', $order->notes) }}</textarea>
                </div>
            </div>

            <hr>
            <h4>Productos del pedido</h4>
            <div class="row">
                <div class="form-group col-md-12">
                    <label class="fw-bold">Buscar producto por código (referencia) o nombre</label>
                    <select id="product-search-select" class="form-control">
                        <option value="">Escribe el código o nombre del producto para agregarlo a la tabla...</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                data-name="{{ $product->name }}"
                                data-reference="{{ $product->reference ?? '' }}">
                                {{ $product->reference ? '[' . $product->reference . '] ' . $product->name : $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @php
                $oldItems = old('items');
                $formItems = $oldItems !== null
                    ? array_values($oldItems)
                    : ($items->count()
                        ? $items->map(fn($item) => ['product_id' => $item->product_id, 'quantity' => $item->quantity])->values()->toArray()
                        : []);
                $productsById = $products->keyBy('id');
            @endphp

            <div class="table-responsive">
                <table class="table table-bordered" id="items-table">
                    <thead>
                        <tr>
                            <th style="width: 180px">Código / Ref.</th>
                            <th>Producto</th>
                            <th style="width: 140px">Cantidad</th>
                            <th style="width: 120px">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($formItems as $index => $item)
                            @php
                                $prodId = $item['product_id'] ?? '';
                                $prodObj = $productsById->get($prodId);
                            @endphp
                            @if ($prodId)
                                <tr class="item-row" data-product-id="{{ $prodId }}">
                                    <td class="item-reference">{{ $prodObj?->reference ?: '—' }}</td>
                                    <td>
                                        <span class="item-name">{{ $prodObj?->name ?? 'Producto #' . $prodId }}</span>
                                        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $prodId }}">
                                    </td>
                                    <td>
                                        <input type="number" min="1" name="items[{{ $index }}][quantity]" class="form-control item-quantity" value="{{ $item['quantity'] ?? 1 }}" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remove-row">Quitar</button>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr id="empty-items-row">
                                <td colspan="4" class="text-center text-muted">Aún no has agregado productos. Busca y selecciona un producto arriba para añadirlo al pedido.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('pedidos.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div></div></div>
@endsection
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let rowIndex = document.querySelectorAll('#items-table tbody tr.item-row').length;
        const tbody = document.querySelector('#items-table tbody');
        const productSearchSelect = document.getElementById('product-search-select');

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function updateEmptyState() {
            const hasRows = tbody.querySelectorAll('tr.item-row').length > 0;
            const emptyRow = document.getElementById('empty-items-row');
            if (!hasRows && !emptyRow) {
                const tr = document.createElement('tr');
                tr.id = 'empty-items-row';
                tr.innerHTML = '<td colspan="4" class="text-center text-muted">Aún no has agregado productos. Busca y selecciona un producto arriba para añadirlo al pedido.</td>';
                tbody.appendChild(tr);
            } else if (hasRows && emptyRow) {
                emptyRow.remove();
            }
        }

        function checkItemsCount() {
            const rowCount = $('#items-table tbody tr.item-row').length;
            $('#items-table-error').remove();
            if (rowCount < 1) {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Debe agregar al menos un producto al pedido.');
                }
                const $err = $('<span id="items-table-error" class="invalid-feedback d-block mt-2 font-weight-bold">Debe agregar al menos un producto al pedido.</span>');
                $('#items-table').closest('.table-responsive').after($err);
                return false;
            }
            return true;
        }

        function addProductToTable(productId, productName, productReference) {
            if (!productId) return;

            const existingRow = tbody.querySelector(`tr.item-row[data-product-id="${productId}"]`);
            if (existingRow) {
                const qtyInput = existingRow.querySelector('.item-quantity');
                if (qtyInput) {
                    qtyInput.value = (parseInt(qtyInput.value, 10) || 1) + 1;
                    qtyInput.focus();
                    if (window.jQuery && jQuery.fn.valid) {
                        $(qtyInput).valid();
                    }
                }
                return;
            }

            const emptyRow = document.getElementById('empty-items-row');
            if (emptyRow) {
                emptyRow.remove();
            }

            const row = document.createElement('tr');
            row.className = 'item-row';
            row.setAttribute('data-product-id', productId);
            row.innerHTML = `
                <td class="item-reference">${escapeHtml(productReference || '—')}</td>
                <td>
                    <span class="item-name">${escapeHtml(productName)}</span>
                    <input type="hidden" name="items[${rowIndex}][product_id]" value="${escapeHtml(productId)}">
                </td>
                <td>
                    <input type="number" min="1" name="items[${rowIndex}][quantity]" class="form-control item-quantity" value="1" required>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-row">Quitar</button>
                </td>
            `;
            tbody.appendChild(row);
            rowIndex++;

            $('#items-table-error').remove();
        }

        function handleProductSelection(selectEl) {
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                return;
            }
            addProductToTable(
                selectedOption.value,
                selectedOption.getAttribute('data-name') || selectedOption.text,
                selectedOption.getAttribute('data-reference') || ''
            );
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(selectEl).val('').trigger('change.select2');
            } else {
                selectEl.value = '';
            }
        }

        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#client-select').select2({
                placeholder: 'Seleccionar o buscar cliente...',
                width: '100%'
            });

            jQuery('#product-search-select').select2({
                placeholder: 'Escribe el código o nombre del producto para agregarlo a la tabla...',
                allowClear: true,
                width: '100%'
            });

            jQuery('#product-search-select').on('change', function () {
                handleProductSelection(this);
            });

            jQuery('#client-select').on('change.select2 change', function () {
                if (window.jQuery && jQuery.fn.valid) {
                    $(this).valid();
                }
            });
        } else if (productSearchSelect) {
            productSearchSelect.addEventListener('change', function () {
                handleProductSelection(this);
            });
        }

        document.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-row')) {
                const row = event.target.closest('tr.item-row');
                if (row) {
                    row.remove();
                    updateEmptyState();
                }
            }
        });

        // jQuery Validate Configuration
        if (window.jQuery && jQuery.fn.validate) {
            $.validator.addClassRules('item-quantity', {
                required: true,
                digits: true,
                min: 1,
                messages: {
                    required: 'La cantidad es obligatoria.',
                    digits: 'La cantidad debe ser un número entero.',
                    min: 'La cantidad mínima es 1.'
                }
            });

            $('#order-form').validate({
                rules: {
                    client_id: {
                        required: true
                    },
                    billing_type: {
                        required: true
                    },
                    code: {
                        maxlength: 100
                    },
                    ordered_at: {
                        date: true
                    }
                },
                messages: {
                    client_id: {
                        required: 'Por favor, seleccione un cliente.'
                    },
                    billing_type: {
                        required: 'Por favor, seleccione el tipo de facturación.'
                    },
                    code: {
                        maxlength: 'El código no puede exceder 100 caracteres.'
                    },
                    ordered_at: {
                        date: 'Por favor, ingrese una fecha válida.'
                    }
                },
                invalidHandler: function (event, validator) {
                    checkItemsCount();
                },
                submitHandler: function (form) {
                    if (!checkItemsCount()) {
                        return false;
                    }
                    form.submit();
                }
            });
        }
    });
</script>
@endpush
