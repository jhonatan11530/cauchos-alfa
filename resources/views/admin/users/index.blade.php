@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div>
            <h2 class="text-white pb-2 fw-bold">Usuarios</h2>
            <h5 class="text-white op-7 mb-2">Gestión de usuarios autorizados</h5>
        </div>
        <div class="ml-md-auto py-2 py-md-0"><a href="{{ route('usuarios.create') }}" class="btn btn-primary">Crear usuario</a>
        </div>
    </div>
@endsection
@section('contenido')
    @include('admin.partials.search')
    <div class="page-inner mt--5">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-head-bg-primary">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Código vendedor</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone }}</td>
                                <td>{{ $user->seller_code ?? '-' }}</td>
                                <td>{{ $user->role->name ?? 'Sin rol' }}</td>
                                <td>{{ $user->is_active ? 'Activo' : 'Inactivo' }}</td>
                                <td>@include('admin.partials.actions', [
                                    'edit' => route('usuarios.edit', $user),
                                    'destroy' => route('usuarios.destroy', $user),
                                    'active' => $user->is_active,
                                ])</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No hay usuarios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection
