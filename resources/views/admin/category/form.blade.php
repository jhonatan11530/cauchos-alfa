@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">Categorías</h2><h5 class="text-white op-7 mb-2">{{ $category->exists ? 'Editar categoría' : 'Crear categoría' }}</h5></div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="card"><div class="card-body">
            <form action="{{ $category->exists ? route('categoria.update', $category) : route('categoria.store') }}" method="POST">
                @csrf
                @if ($category->exists) @method('PUT') @endif
                <div class="form-group"><label>Nombre</label><input name="name" class="form-control" value="{{ old('name', $category->name) }}" required></div>
                <div class="form-group"><label>Descripción</label><textarea name="description" class="form-control">{{ old('description', $category->description) }}</textarea></div>
                <div class="form-group"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}> Activa</label></div>
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('categoria.index') }}" class="btn btn-secondary">Cancelar</a>
            </form>
        </div></div>
    </div>
@endsection
