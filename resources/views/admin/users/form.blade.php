@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">Usuarios</h2><h5 class="text-white op-7 mb-2">{{ $user->exists ? 'Editar usuario' : 'Crear usuario' }}</h5></div>
@endsection
@section('contenido')
    <div class="page-inner mt--5"><div class="card"><div class="card-body">
        <form action="{{ $user->exists ? route('usuarios.update', $user) : route('usuarios.store') }}" method="POST">
            @csrf
            @if ($user->exists) @method('PUT') @endif
            <div class="row">
                <div class="form-group col-md-6"><label>Nombre</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
                <div class="form-group col-md-6"><label>Correo</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
                <div class="form-group col-md-6"><label>Teléfono</label><input name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
                <div class="form-group col-md-6"><label>Rol</label><select name="role_id" class="form-control"><option value="">Sin rol</option>@foreach ($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-6"><label>Contraseña</label><input type="password" name="password" class="form-control" {{ $user->exists ? '' : 'required' }}></div>
            </div>
            <div class="form-group"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}> Activo</label></div>
            <button class="btn btn-primary">Guardar</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div></div></div>
@endsection
