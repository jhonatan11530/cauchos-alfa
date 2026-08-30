@extends('admin.layout.plantilla')
@section('banner')
    <div>
        <h2 class="text-white pb-2 fw-bold">Vendedores</h2>
        <h5 class="text-white op-7 mb-2">{{ $seller->exists ? 'Editar vendedor' : 'Crear vendedor' }}</h5>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="card">
            <div class="card-body">
                <form action="{{ $seller->exists ? route('vendedores.update', $seller) : route('vendedores.store') }}"
                    method="POST">
                    @csrf
                    @if ($seller->exists)
                        @method('PUT')
                    @endif
                    <div class="row">
                        <div class="form-group col-md-6"><label>Nombre</label><input name="name" class="form-control"
                                value="{{ old('name', $seller->name) }}" required></div>
                        <div class="form-group col-md-6"><label>Correo</label><input type="email" name="email"
                                class="form-control" value="{{ old('email', $seller->email) }}" required></div>
                        <div class="form-group col-md-6"><label>Teléfono</label><input name="phone" class="form-control"
                                value="{{ old('phone', $seller->phone) }}"></div>
                        <div class="form-group col-md-6">
                            <label>Código de acceso (con este código el vendedor ingresa desde la web)</label>
                            <input name="seller_code" class="form-control" value="{{ old('seller_code', $suggestedCode) }}"
                                required>
                            @error('seller_code')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group col-md-6"><label>Contraseña
                                {{ $seller->exists ? '(dejar vacío para no cambiar)' : '' }}</label><input type="password"
                                name="password" class="form-control" {{ $seller->exists ? '' : 'required' }}></div>
                    </div>
                    <div class="form-group"><label><input type="checkbox" name="is_active" value="1"
                                {{ old('is_active', $seller->is_active ?? true) ? 'checked' : '' }}> Activo</label></div>
                    <button class="btn btn-primary">Guardar</button>
                    <a href="{{ route('vendedores.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
@endsection
