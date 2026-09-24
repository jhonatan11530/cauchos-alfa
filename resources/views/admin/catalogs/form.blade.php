@extends('admin.layout.plantilla')

@section('contenido')
    <div class="row"><div class="col-md-12"><div class="card card-body">
        <h4 class="card-title">{{ $catalog->exists ? 'Editar Catálogo' : 'Nuevo Catálogo' }}</h4>
        <form action="{{ $catalog->exists ? route('catalogos.update', $catalog) : route('catalogos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($catalog->exists) @method('PUT') @endif

            <div class="row">
                <div class="form-group col-md-6"><label>Nombre</label><input name="name" class="form-control" value="{{ old('name', $catalog->name) }}" required></div>
                <div class="form-group col-md-6"><label>Empresa</label><input name="company_name" class="form-control" value="{{ old('company_name', $catalog->company_name) }}"></div>
                <div class="form-group col-md-12"><label>Descripción</label><textarea name="description" class="form-control">{{ old('description', $catalog->description) }}</textarea></div>
                <div class="form-group col-md-4"><label>Correo contacto</label><input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $catalog->contact_email) }}"></div>
                <div class="form-group col-md-4"><label>Teléfono contacto</label><input name="contact_phone" class="form-control" value="{{ old('contact_phone', $catalog->contact_phone) }}"></div>
                <div class="form-group col-md-4"><label>Logo</label><input type="file" name="logo" class="form-control"></div>
                <div class="form-group col-md-12"><label>Dirección contacto</label><input name="contact_address" class="form-control" value="{{ old('contact_address', $catalog->contact_address) }}"></div>
                <div class="form-group col-md-12"><label>Notas</label><textarea name="notes" class="form-control">{{ old('notes', $catalog->notes) }}</textarea></div>
                <div class="form-group col-md-12">
                    <label>Plantilla de Diseño (PDF)</label>
                    <select name="template" class="form-control">
                        <option value="default" {{ old('template', $catalog->template ?? 'default') == 'default' ? 'selected' : '' }}>Clásico Alfa (Fondo Azul Flotante)</option>
                        <option value="modern" {{ old('template', $catalog->template ?? '') == 'modern' ? 'selected' : '' }}>Moderno (Fondo Blanco con Grilla)</option>
                        <option value="classic" {{ old('template', $catalog->template ?? '') == 'classic' ? 'selected' : '' }}>Lista Minimalista</option>
                        <option value="catalogo-web" {{ old('template', $catalog->template ?? '') == 'catalogo-web' ? 'selected' : '' }}>Catálogo Web (Estilo Roja Bicis)</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $catalog->is_active ?? true) ? 'checked' : '' }}> Activo</label></div>
            
            <div class="form-group">
                <label class="font-weight-bold">Productos del catálogo</label>
                
                @if($products->isEmpty())
                    <div class="text-muted">Primero crea productos activos.</div>
                @else
                    <div class="mb-3">
                        <label class="btn btn-outline-primary btn-sm" style="cursor: pointer;">
                            <input type="checkbox" id="selectAllGlobal"> <strong class="ml-1">Seleccionar Todo</strong>
                        </label>
                    </div>

                    @php 
                        $groupedProducts = $products->groupBy(fn($p) => $p->category?->name ?? 'Sin Categoría'); 
                    @endphp

                    @foreach($groupedProducts as $categoryName => $catProducts)
                        @php $catId = 'cat_' . Str::slug($categoryName); @endphp
                        <div class="card mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                <h6 class="m-0 font-weight-bold text-primary">{{ $categoryName }} <span class="badge badge-secondary">{{ $catProducts->count() }}</span></h6>
                                <label class="mb-0" style="cursor: pointer;">
                                    <input type="checkbox" class="select-category" data-target=".{{ $catId }}-checkbox"> Marcar todos
                                </label>
                            </div>
                            <div class="card-body py-2">
                                <div class="row">
                                    @foreach($catProducts as $product)
                                        <div class="col-md-4 mb-2">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input product-checkbox {{ $catId }}-checkbox" name="products[]" id="prod_{{ $product->id }}" value="{{ $product->id }}" @checked(in_array($product->id, old('products', $selectedProducts)))> 
                                                <label class="custom-control-label" for="prod_{{ $product->id }}" style="cursor: pointer;">{{ $product->name }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <button class="btn btn-primary">Guardar</button>
            <a href="{{ route('catalogos.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div></div></div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const globalCheckbox = document.getElementById('selectAllGlobal');
            const categoryCheckboxes = document.querySelectorAll('.select-category');
            const allProductCheckboxes = document.querySelectorAll('.product-checkbox');

            // Global Select All
            if (globalCheckbox) {
                globalCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    allProductCheckboxes.forEach(cb => cb.checked = isChecked);
                    categoryCheckboxes.forEach(cb => cb.checked = isChecked);
                });
            }

            // Category Select All
            categoryCheckboxes.forEach(catCb => {
                catCb.addEventListener('change', function() {
                    const targetClass = this.getAttribute('data-target');
                    const targetCheckboxes = document.querySelectorAll(targetClass);
                    targetCheckboxes.forEach(cb => cb.checked = this.checked);
                    updateGlobalCheckbox();
                });
            });

            // Individual Checkbox changes update parent
            allProductCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    updateCategoryCheckbox(this);
                    updateGlobalCheckbox();
                });
            });

            function updateCategoryCheckbox(changedElement) {
                categoryCheckboxes.forEach(catCb => {
                    const targetClass = catCb.getAttribute('data-target');
                    if (changedElement.classList.contains(targetClass.replace('.', ''))) {
                        const groupCheckboxes = document.querySelectorAll(targetClass);
                        const allChecked = Array.from(groupCheckboxes).every(c => c.checked);
                        const someChecked = Array.from(groupCheckboxes).some(c => c.checked);
                        catCb.checked = allChecked;
                        catCb.indeterminate = someChecked && !allChecked;
                    }
                });
            }

            function updateGlobalCheckbox() {
                if (!globalCheckbox) return;
                const allChecked = Array.from(allProductCheckboxes).every(c => c.checked);
                const someChecked = Array.from(allProductCheckboxes).some(c => c.checked);
                globalCheckbox.checked = allChecked;
                globalCheckbox.indeterminate = someChecked && !allChecked;
            }

            // Init indeterminate states on load
            if (allProductCheckboxes.length > 0) {
                allProductCheckboxes.forEach(cb => updateCategoryCheckbox(cb));
                updateGlobalCheckbox();
            }
        });
    </script>
    @endpush
@endsection
