@extends('admin.layout.plantilla')

@section('banner')
    <div>
        <h2 class="text-white pb-2 fw-bold">Plantillas de WhatsApp</h2>
        <h5 class="text-white op-7 mb-2">{{ $template->exists ? 'Editar plantilla' : 'Nueva plantilla' }}</h5>
    </div>
@endsection

@section('contenido')
    <div class="page-inner mt--5">
        <div class="card">
            <div class="card-body">
                <form action="{{ $template->exists ? route('whatsapp.templates.update', $template) : route('whatsapp.templates.store') }}" method="POST">
                    @csrf
                    @if ($template->exists)
                        @method('PUT')
                    @endif
                    <div class="form-group">
                        <label for="template-name">Nombre</label>
                        <input id="template-name" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $template->name) }}" required maxlength="255">
                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="template-message">Mensaje</label>
                        <textarea id="template-message" name="message" class="form-control @error('message') is-invalid @enderror"
                            rows="6" maxlength="4096" required>{{ old('message', $template->message) }}</textarea>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1"
                                @checked(old('is_active', $template->exists ? $template->is_active : true))>
                            Activa
                        </label>
                    </div>
                    <button class="btn btn-primary">Guardar</button>
                    <a href="{{ route('whatsapp.templates.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
@endsection
