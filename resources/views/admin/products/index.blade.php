@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div><h2 class="text-white pb-2 fw-bold">Productos</h2><h5 class="text-white op-7 mb-2">Gestión de productos</h5></div>
        <div class="ml-md-auto py-2 py-md-0 d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-light mr-2 mb-2 mb-md-0" data-toggle="modal" data-target="#importProductModal">
                <i class="fas fa-file-import mr-1"></i> Importar CSV
            </button>
            <a href="{{ route('export.products', request()->all()) }}" class="btn btn-outline-light mr-2 mb-2 mb-md-0">
                <i class="fas fa-file-excel mr-1"></i> Exportar CSV
            </a>
            <a href="{{ route('productos.create') }}" class="btn btn-primary mb-2 mb-md-0">Crear producto</a>
        </div>
    </div>
@endsection
@section('contenido')
    @if (session('import_errors'))
        <div class="page-inner mt--5 pb-0">
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong><i class="fas fa-exclamation-triangle mr-1"></i> Observaciones de la importación:</strong>
                <ul class="mb-0 mt-1 pl-3">
                    @foreach (session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>
    @endif
    @include('admin.partials.search')
    <div class="page-inner mt--5"><div class="card"><div class="card-body table-responsive">
        <table class="table table-head-bg-primary">
            <thead><tr><th>Id</th><th>Nombre</th><th>Referencia</th><th>Categoría</th><th>Disponibilidad</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>{{ $product->id }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->reference }}</td>
                        <td>{{ $product->category->name ?? 'Sin categoría' }}</td>
                        <td>{{ $product->availability }}</td>
                        <td>{{ $product->is_active ? 'Activo' : 'Inactivo' }}</td>
                        <td>
                            @php
                                $actionParams = [
                                    'edit' => route('productos.edit', $product),
                                    'destroy' => route('productos.destroy', $product),
                                    'active' => $product->is_active
                                ];
                                if (empty($product->category_id)) {
                                    $actionParams['forceDelete'] = route('productos.forceDelete', $product);
                                }
                            @endphp
                            @include('admin.partials.actions', $actionParams)
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">No hay productos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $products->links() }}
    </div></div></div>

    <div class="modal fade" id="importProductModal" tabindex="-1" role="dialog" aria-labelledby="importProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold" id="importProductModalLabel">
                        <i class="fas fa-file-import mr-2"></i>Importar Productos desde CSV
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('import.products') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Descarga y completa la plantilla oficial. Si la categoría indicada no existe, el sistema la creará automáticamente.
                        </div>

                        <div class="mb-3 text-center">
                            <a href="{{ route('import.template', ['tipo' => 'productos']) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download mr-1"></i> Descargar Plantilla de Productos (.csv)
                            </a>
                        </div>

                        <div class="form-group px-0">
                            <label for="product_csv_file" class="font-weight-bold">Seleccionar archivo CSV:</label>
                            <input type="file" name="file" id="product_csv_file" class="form-control-file border p-2 rounded" accept=".csv,text/csv,text/plain" required>
                            <small class="form-text text-muted mt-2">
                                Formato admitido: <strong>.csv</strong> (delimitado por coma o punto y coma, codificación UTF-8). Tamaño máx: 5MB.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cloud-upload-alt mr-1"></i> Subir e Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
