<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="importModalLabel">
                    <i class="fas fa-file-import mr-2"></i>{{ $importTitle ?? 'Importación Masiva desde CSV' }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ $importAction }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Descarga y completa la plantilla oficial para evitar inconsistencias de formato.
                    </div>

                    <div class="mb-3 text-center">
                        <a href="{{ $importTemplateUrl }}" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-download mr-1"></i> Descargar Plantilla Oficial (.csv)
                        </a>
                    </div>

                    <div class="form-group px-0">
                        <label for="csv_file" class="font-weight-bold">Seleccionar archivo CSV:</label>
                        <input type="file" name="file" id="csv_file" class="form-control-file border p-2 rounded" accept=".csv,text/csv,text/plain" required>
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
