@extends('admin.layout.plantilla')

@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div>
            <h2 class="text-white pb-2 fw-bold">Plantillas de WhatsApp</h2>
            <h5 class="text-white op-7 mb-2">Mensajes reutilizables para enviar catálogos</h5>
        </div>
        <div class="ml-md-auto py-2 py-md-0">
            <a href="{{ route('whatsapp.templates.create') }}" class="btn btn-primary">Nueva plantilla</a>
        </div>
    </div>
@endsection

@section('contenido')
    <div class="page-inner mt--5">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-head-bg-primary">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Mensaje</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td>{{ $template->name }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($template->message, 100) }}</td>
                                <td>{{ $template->is_active ? 'Activa' : 'Inactiva' }}</td>
                                <td>
                                    <a href="{{ route('whatsapp.templates.edit', $template) }}" class="btn btn-sm btn-primary">Editar</a>
                                    <form method="POST" action="{{ route('whatsapp.templates.destroy', $template) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm {{ $template->is_active ? 'btn-warning' : 'btn-success' }}">
                                            {{ $template->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center">No hay plantillas registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $templates->links() }}
            </div>
        </div>
    </div>
@endsection
