@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">Catálogos</h2><h5 class="text-white op-7 mb-2">{{ $catalog->exists ? 'Editar catálogo' : 'Crear catálogo' }}</h5></div>
@endsection
@section('contenido')
    <div class="page-inner mt--5"><div class="card"><div class="card-body">
        <form action="{{ $catalog->exists ? route('catalogos.update', $catalog) : route('catalogos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if ($catalog->exists) @method('PUT') @endif
            <div class="row">
                <div class="form-group col-md-6"><label>Nombre</label><input name="name" class="form-control" value="{{ old('name', $catalog->name) }}" required></div>
                <div class="form-group col-md-6"><label>Empresa</label><input name="company_name" class="form-control" value="{{ old('company_name', $catalog->company_name) }}"></div>
                <div class="form-group col-md-12"><label>Descripción</label><textarea name="description" class="form-control">{{ old('description', $catalog->description) }}</textarea></div>
                <div class="form-group col-md-4"><label>Correo contacto</label><input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $catalog->contact_email) }}"></div>
                <div class="form-group col-md-4"><label>Teléfono contacto</label><input name="contact_phone" class="form-control" value="{{ old('contact_phone', $catalog->contact_phone) }}"></div>
                <div class="form-group col-md-4"><label>Logo</label><input type="file" name="logo" class="form-control"></div>
                <div class="form-group col-md-12"><label>Dirección contacto</label><input name="contact_address" class="form-control" value="{{ old('contact_address', $catalog->contact_address) }}"></div>
                <div class="form-group col-md-12"><label>Notas</label><textarea name="notes" class="form-control">{{ old('notes', $catalog->notes) }}</textarea></div>
            </div>
            <div class="form-group"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $catalog->is_active ?? true) ? 'checked' : '' }}> Activo</label></div>
            <div class="form-group">
                <label>Productos del catálogo</label>
                <div class="row">
                    @forelse ($products as $product)
                        <div class="col-md-4 mb-2">
                            <label class="d-block"><input type="checkbox" name="products[]" value="{{ $product->id }}" @checked(in_array($product->id, old('products', $selectedProducts)))> {{ $product->name }}</label>
                        </div>
                    @empty
                        <div class="col-12 text-muted">Primero crea productos activos.</div>
                    @endforelse
                </div>
            </div>
            <button class="btn btn-primary">Guardar</button>
            <a href="{{ route('catalogos.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div></div></div>
@endsection
