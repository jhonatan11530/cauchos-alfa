@extends('site.layout')

@section('title', 'Mi pedido | Cauchos Alfa')

@section('contenido')
    <section class="bg-dark text-white py-5 text-center">
        <div class="container">
            <h1 class="fw-bold">Mi pedido</h1>
            <p class="lead mb-0">Revisa los productos y confirma tu pedido.</p>
        </div>
    </section>

    <section class="container py-5">
        @if ($errors->any())
            <div class="alert alert-danger">Revisa los campos marcados: {{ $errors->first() }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold">Productos en el pedido</h5>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th style="width:110px">Cantidad</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cartProducts as $product)
                                    <tr>
                                        <td>{{ $product->name }}<br><span
                                                class="text-muted small">{{ $product->reference }}</span></td>
                                        <td>{{ $product->category->name ?? 'General' }}</td>
                                        <td>{{ $product->cart_quantity }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('site.seller.remove') }}">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                                <button class="btn btn-sm btn-outline-danger"><i
                                                        class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <a href="{{ route('site.catalog') }}" class="btn btn-secondary btn-sm"><i
                                class="bi bi-arrow-left"></i> Seguir agregando productos</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold">Datos del pedido</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pedidos.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Cliente *</label>
                                <select name="client_id" class="form-select @error('client_id') is-invalid @enderror"
                                    required>
                                    <option value="">Seleccionar cliente</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>
                                            {{ $client->name }}</option>
                                    @endforeach
                                </select>
                                @error('client_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
                            </div>

                            @php
                                $oldItems = old('items');
                            @endphp
                            @foreach ($cartProducts as $product)
                                <input type="hidden" name="items[{{ $loop->index }}][product_id]"
                                    value="{{ $product->id }}">
                                <input type="hidden" name="items[{{ $loop->index }}][quantity]"
                                    value="{{ $oldItems[$loop->index]['quantity'] ?? $product->cart_quantity }}">
                                <input type="hidden" name="items[{{ $loop->index }}][unit_price]"
                                    value="{{ $oldItems[$loop->index]['unit_price'] ?? ($product->price ?? 0) }}">
                            @endforeach

                            <button type="submit" class="btn btn-brand w-100"><i class="bi bi-check-circle"></i> Confirmar
                                pedido</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
