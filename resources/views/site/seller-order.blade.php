"@extends('site.layouts.app')

@section('title', 'Mi Pedido | Cauchos Alfa')

@section('content')
    <div class="container" style="margin-top: 120px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="font-weight-bold">Gestionar Pedido</h2>
            <a href="{{ route('site.catalog') }}" class="btn btn-outline-dark">
                <i class="bi bi-plus-circle"></i> Agregar más productos
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-dark text-white">
                                    <tr>
                                        <th class="border-0 px-4 py-3">Producto</th>
                                        <th class="border-0 py-3 text-center">Categoría</th>
                                        <th class="border-0 py-3 text-center">Cantidad</th>
                                        <th class="border-0 py-3 text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cartProducts as $product)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="d-flex align-items-center">
                                                    <img src="{{ $product->images->first() ? asset('storage/' . $product->images->first()->path) : asset('images/placeholder.png') }}"
                                                        class="img-thumbnail mr-3"
                                                        style="width: 50px; height: 50px; object-fit: cover;">
                                                    <span class="font-weight-bold">{{ $product->name }}</span>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ $product->category->name ?? 'General' }}</td>
                                            <td class="text-center">
                                                <span class="badge badge-dark p-2">{{ $product->cart_quantity }}</span>
                                            </td>
                                            <td class="text-center">
                                                <form method="POST" action="{{ route('site.seller.remove') }}">
                                                    @csrf
                                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                No hay productos en el pedido actual.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 bg-void">
                    <div class="card-body p-4">
                        <h4 class="font-weight-bold mb-4">Finalizar Pedido</h4>
                        <form method="POST" action="{{ route('pedidos.store') }}">
                            @csrf
                            <div class="form-group">
                                <label class="small font-weight-bold">SELECCIONAR CLIENTE</label>
                                <select name="client_id" class="form-control custom-select" required>
                                    <option value="" selected disabled>Elige un cliente...</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}">{{ $client->name }}</option>
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
                            @endforeach
                            <button type="submit" class="btn btn-red btn-block btn-lg font-weight-bold mt-4">
                                CONFIRMAR PEDIDO
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection"
