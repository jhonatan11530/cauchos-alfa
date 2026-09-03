@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">{{ $client->name }}</h2><h5 class="text-white op-7 mb-2">Detalle del cliente y pedidos asociados</h5></div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="card"><div class="card-body">
            <p><strong>Documento:</strong> {{ $client->document ?: 'No registrado' }}</p>
            <p><strong>Contacto:</strong> {{ $client->email }} {{ $client->phone }}</p>
            <p><strong>Dirección:</strong> {{ $client->address }} {{ $client->city }}</p>
            <h4 class="mt-4">Pedidos</h4>
            <table class="table">
                <thead><tr><th>Código</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($client->orders as $order)
                        <tr><td>{{ $order->code }}</td><td>{{ $order->status->name }}</td><td><a href="{{ route('pedidos.show', $order) }}" class="btn btn-sm btn-primary">Ver</a></td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center">Este cliente no tiene pedidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
@endsection
