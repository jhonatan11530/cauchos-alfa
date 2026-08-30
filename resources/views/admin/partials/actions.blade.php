<a href="{{ $edit }}" class="btn btn-sm btn-primary">Editar</a>
<form action="{{ $destroy }}" method="POST" class="d-inline">
    @csrf
    @method('DELETE')
    <button class="btn btn-sm {{ $active ? 'btn-warning' : 'btn-success' }}">{{ $active ? 'Desactivar' : 'Activar' }}</button>
</form>
