@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">Estados de pedido</h2><h5 class="text-white op-7 mb-2">{{ $status->exists ? 'Editar estado' : 'Crear estado' }}</h5></div>
@endsection
@section('contenido')
    <div class="page-inner mt--5"><div class="card"><div class="card-body">
        <form id="order-status-crud-form" action="{{ $status->exists ? route('estados-pedido.update', $status) : route('estados-pedido.store') }}" method="POST">
            @csrf
            @if ($status->exists) @method('PUT') @endif
            @if ($status->exists && $status->isCritical())
                <div class="alert alert-info py-2">
                    <i class="fas fa-lock mr-1"></i> Estado oficial del sistema (<strong>{{ $status->slug }}</strong>): el nombre y las banderas estructurales son fijos; puedes personalizar el color y el orden de visualización.
                </div>
            @endif
            <div class="row">
                <div class="form-group col-md-6"><label>Nombre</label><input name="name" class="form-control" value="{{ old('name', $status->name) }}" {{ ($status->exists && $status->isCritical()) ? 'readonly' : '' }} required></div>
                <div class="form-group col-md-3"><label>Color</label><input type="color" name="color" class="form-control" value="{{ old('color', $status->color ?: '#1572e8') }}" required></div>
                <div class="form-group col-md-3"><label>Orden</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $status->sort_order ?? 0) }}" required></div>
            </div>
            <div class="form-group">
                <label class="mr-3"><input type="checkbox" name="is_final" value="1" {{ old('is_final', $status->is_final) ? 'checked' : '' }} {{ ($status->exists && $status->isCritical()) ? 'disabled' : '' }}> Final</label>
                <label class="mr-3"><input type="checkbox" name="is_cancelled" value="1" {{ old('is_cancelled', $status->is_cancelled) ? 'checked' : '' }} {{ ($status->exists && $status->isCritical()) ? 'disabled' : '' }}> Cancelado</label>
                <label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $status->is_active ?? true) ? 'checked' : '' }} {{ ($status->exists && $status->isCritical()) ? 'disabled' : '' }}> Activo</label>
            </div>
            <button class="btn btn-primary">Guardar</button>
            <a href="{{ route('estados-pedido.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div></div></div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#order-status-crud-form').validate({
        rules: {
            name: {
                required: true,
                maxlength: 255
            },
            color: {
                required: true,
                maxlength: 20
            },
            sort_order: {
                required: true,
                digits: true,
                min: 0
            }
        },
        messages: {
            name: {
                required: "Este campo es obligatorio.",
                maxlength: "Por favor, no escribas más de 255 caracteres."
            },
            color: {
                required: "Este campo es obligatorio.",
                maxlength: "Por favor, no escribas más de 20 caracteres."
            },
            sort_order: {
                required: "Este campo es obligatorio.",
                digits: "Por favor, escribe sólo dígitos.",
                min: "Por favor, escribe un valor mayor o igual a 0."
            }
        }
    });
});
</script>
@endpush
