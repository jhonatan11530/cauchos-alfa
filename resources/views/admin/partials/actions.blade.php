<a href="{{ $edit }}" class="btn btn-sm btn-primary">Editar</a>
<form action="{{ $destroy }}" method="POST" class="d-inline">
    @csrf
    @method('DELETE')
    <button class="btn btn-sm {{ $active ? 'btn-warning' : 'btn-success' }}">{{ $active ? 'Desactivar' : 'Activar' }}</button>
</form>

@if(isset($forceDelete))
<form action="{{ $forceDelete }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este registro permanentemente?');">
    @csrf
    @method('DELETE')
    <button class="btn btn-sm btn-danger">Eliminar</button>
</form>
@endif
