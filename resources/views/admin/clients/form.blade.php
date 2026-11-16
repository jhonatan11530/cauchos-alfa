@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">Clientes</h2><h5 class="text-white op-7 mb-2">{{ $client->exists ? 'Editar cliente' : 'Crear cliente' }}</h5></div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="card"><div class="card-body">
            <form id="client-form" action="{{ $client->exists ? route('clientes.update', $client) : route('clientes.store') }}" method="POST">
                @csrf
                @if ($client->exists) @method('PUT') @endif
                <div class="row">
                    <div class="form-group col-md-6"><label>Nombre</label><input name="name" class="form-control" value="{{ old('name', $client->name) }}" required></div>
                    <div class="form-group col-md-6"><label>Documento/NIT</label><input name="document" class="form-control" value="{{ old('document', $client->document) }}"></div>
                    <div class="form-group col-md-6"><label>Correo</label><input type="email" name="email" class="form-control" value="{{ old('email', $client->email) }}"></div>
                    <div class="form-group col-md-6"><label>Teléfono</label><input name="phone" class="form-control" value="{{ old('phone', $client->phone) }}"></div>
                    <div class="form-group col-md-6"><label>Dirección</label><input name="address" class="form-control" value="{{ old('address', $client->address) }}"></div>
                    <div class="form-group col-md-6"><label>Ciudad</label><input name="city" class="form-control" value="{{ old('city', $client->city) }}"></div>
                    <div class="form-group col-md-12"><label>Notas</label><textarea name="notes" class="form-control">{{ old('notes', $client->notes) }}</textarea></div>
                </div>
                <div class="form-group"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $client->is_active ?? true) ? 'checked' : '' }}> Activo</label></div>
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('clientes.index') }}" class="btn btn-secondary">Cancelar</a>
            </form>
        </div></div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#client-form').validate({
        rules: {
            name: {
                required: true,
                maxlength: 255
            },
            document: {
                maxlength: 100
            },
            email: {
                email: true,
                maxlength: 255
            },
            phone: {
                maxlength: 50
            },
            address: {
                maxlength: 255
            },
            city: {
                maxlength: 100
            }
        },
        messages: {
            name: {
                required: "Este campo es obligatorio.",
                maxlength: "Por favor, no escribas más de 255 caracteres."
            },
            document: {
                maxlength: "Por favor, no escribas más de 100 caracteres."
            },
            email: {
                email: "Por favor, escribe una dirección de correo válida.",
                maxlength: "Por favor, no escribas más de 255 caracteres."
            },
            phone: {
                maxlength: "Por favor, no escribas más de 50 caracteres."
            },
            address: {
                maxlength: "Por favor, no escribas más de 255 caracteres."
            },
            city: {
                maxlength: "Por favor, no escribas más de 100 caracteres."
            }
        }
    });
});
</script>
@endpush
