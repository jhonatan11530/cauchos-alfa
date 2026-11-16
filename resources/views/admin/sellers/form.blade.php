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
                <form id="seller-form" action="{{ $seller->exists ? route('vendedores.update', $seller) : route('vendedores.store') }}"
                    method="POST">
                    @csrf
                    @if ($seller->exists)
                        @method('PUT')
                    @endif
                    <div class="row">
                        <div class="form-group col-md-6"><label>Nombre</label><input name="name" class="form-control"
                                value="{{ old('name', $seller->name) }}" required></div>
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
                        @if (! $seller->exists)
                            <div class="col-md-12 text-muted small">
                                <i class="bi bi-info-circle"></i> El correo se genera automáticamente con el nombre y el
                                dominio de la empresa. La contraseña temporal se genera de forma segura y se muestra al guardar.
                            </div>
                        @endif
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

@push('scripts')
<script>
$(document).ready(function () {
    $('#seller-form').validate({
        rules: {
            name: {
                required: true,
                maxlength: 255
            },
            seller_code: {
                required: true,
                minlength: 2,
                maxlength: 50
            },
            phone: {
                maxlength: 50
            }
        },
        messages: {
            name: {
                required: "Este campo es obligatorio.",
                maxlength: "Por favor, no escribas más de 255 caracteres."
            },
            seller_code: {
                required: "Este campo es obligatorio.",
                minlength: "Por favor, no escribas menos de 2 caracteres.",
                maxlength: "Por favor, no escribas más de 50 caracteres."
            },
            phone: {
                maxlength: "Por favor, no escribas más de 50 caracteres."
            }
        }
    });
});
</script>
@endpush
