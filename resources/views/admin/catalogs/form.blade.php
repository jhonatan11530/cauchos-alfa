@extends('admin.layout.plantilla')

@section('contenido')
    <div class="row"><div class="col-md-12"><div class="card card-body">
        <h4 class="card-title">{{ $catalog->exists ? 'Editar Catálogo' : 'Nuevo Catálogo' }}</h4>
        <form id="catalog-form" action="{{ $catalog->exists ? route('catalogos.update', $catalog) : route('catalogos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($catalog->exists) @method('PUT') @endif

            @php
                $selectedTemplate = old('template', $catalog->template ?? 'default');
                if (! in_array($selectedTemplate, \App\Models\Catalog::VALID_TEMPLATES, true)) {
                    $selectedTemplate = 'default';
                }

                $hasOldInput = session()->hasOldInput() || session()->has('_old_input');
                $selectedIds = $hasOldInput
                    ? array_map('intval', (array) old('products', []))
                    : array_map('intval', (array) ($selectedProducts ?? []));

                if (! isset($orderedCategoryGroups)) {
                    $orderedCategoryGroups = $products
                        ->groupBy(fn ($p) => \App\Models\Catalog::categoryKeyForProduct($p))
                        ->map(fn ($items, $key) => [
                            'key' => $key,
                            'name' => $items->first()?->category?->name ?? 'Sin Categoría',
                            'products' => $items,
                        ])
                        ->values();
                }
            @endphp

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
                        <option value="default" {{ $selectedTemplate === 'default' ? 'selected' : '' }}>Clásico Alfa (Fondo Azul Flotante)</option>
                        <option value="catalogo-web" {{ $selectedTemplate === 'catalogo-web' ? 'selected' : '' }}>Catálogo Web (Estilo Roja Bicis)</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label><input type="checkbox" name="is_active" value="1" {{ ($hasOldInput ? (bool) old('is_active') : ($catalog->is_active ?? true)) ? 'checked' : '' }}> Activo</label></div>

            <div class="form-group">
                <label class="font-weight-bold d-block">Productos del catálogo y orden en PDF</label>
                <small class="form-text text-muted mb-2">
                    Arrastra las categorías o productos (o usa los botones Subir/Bajar) para elegir el orden exacto en que aparecerán en el catálogo PDF.
                </small>

                @if($products->isEmpty())
                    <div class="text-muted">Primero crea productos activos.</div>
                @else
                    <div class="mb-3">
                        <label class="btn btn-outline-primary btn-sm" style="cursor: pointer;">
                            <input type="checkbox" id="selectAllGlobal"> <strong class="ml-1">Seleccionar Todo</strong>
                        </label>
                    </div>

                    <div id="categories-sortable" class="categories-sortable">
                        @foreach($orderedCategoryGroups as $group)
                            @php
                                $catKey = (string) $group['key'];
                                $catClass = 'cat_' . \Illuminate\Support\Str::slug($catKey, '_');
                                $catProducts = $group['products'];
                            @endphp
                            <div class="card mb-3 category-card border" data-category-key="{{ $catKey }}">
                                <input type="hidden" name="category_order[]" value="{{ $catKey }}">
                                <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-2">
                                    <div class="d-flex align-items-center" style="min-width: 0; max-width: 100%;">
                                        <span class="category-drag-handle mr-2 text-muted" title="Arrastrar categoría" style="cursor: grab; user-select: none;">&#9776;</span>
                                        <span class="badge badge-primary category-order-badge mr-2">#{{ $loop->iteration }}</span>
                                        <h6 class="m-0 font-weight-bold text-primary" style="min-width: 0; white-space: normal; overflow-wrap: anywhere;">
                                            {{ $group['name'] }}
                                            <span class="badge badge-secondary ml-1">{{ $catProducts->count() }}</span>
                                        </h6>
                                    </div>
                                    <div class="d-flex align-items-center mt-1 mt-sm-0">
                                        <div class="btn-group btn-group-sm mr-3" role="group" aria-label="Ordenar categoría">
                                            <button type="button" class="btn btn-outline-secondary py-0 px-2 move-category-up" title="Subir categoría" @disabled($loop->first)>&uarr;</button>
                                            <button type="button" class="btn btn-outline-secondary py-0 px-2 move-category-down" title="Bajar categoría" @disabled($loop->last)>&darr;</button>
                                        </div>
                                        <label class="mb-0" style="cursor: pointer;">
                                            <input type="checkbox" class="select-category" data-target=".{{ $catClass }}-checkbox"> Marcar todos
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body py-2">
                                    <div class="products-sortable" data-category-key="{{ $catKey }}">
                                        @foreach($catProducts as $product)
                                            <div class="product-item mb-1" data-product-id="{{ $product->id }}">
                                                <div class="border rounded py-1 px-2 d-flex align-items-center justify-content-between bg-white">
                                                    <div class="d-flex align-items-center flex-grow-1" style="min-width: 0;">
                                                        <span class="product-drag-handle mr-2 text-muted" title="Arrastrar producto" style="cursor: grab; user-select: none;">&#9776;</span>
                                                        <span class="badge badge-light border product-order-badge mr-2">{{ $loop->iteration }}</span>
                                                        <div class="custom-control custom-checkbox mb-0" style="min-width: 0; margin-right: 0;">
                                                            <input type="checkbox"
                                                                class="custom-control-input product-checkbox {{ $catClass }}-checkbox"
                                                                name="products[]"
                                                                id="prod_{{ $product->id }}"
                                                                value="{{ $product->id }}"
                                                                @checked(in_array((int) $product->id, $selectedIds, true))>
                                                            <label class="custom-control-label" for="prod_{{ $product->id }}" style="cursor: pointer; white-space: normal; overflow-wrap: anywhere;">
                                                                <span>{{ $product->name }}</span>
                                                                @if($product->reference)
                                                                    <small class="text-muted ml-2">REF: {{ $product->reference }}</small>
                                                                @endif
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="btn-group btn-group-sm ml-2 flex-shrink-0" role="group" aria-label="Ordenar producto">
                                                        <button type="button" class="btn btn-outline-secondary py-0 px-2 move-product-up" title="Subir producto" @disabled($loop->first)>&uarr;</button>
                                                        <button type="button" class="btn btn-outline-secondary py-0 px-2 move-product-down" title="Bajar producto" @disabled($loop->last)>&darr;</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <button class="btn btn-primary">Guardar</button>
            <a href="{{ route('catalogos.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div></div></div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            if (window.jQuery && jQuery.fn.validate) {
                if (!$.validator.methods.imageFile) {
                    $.validator.addMethod('imageFile', function (value, element) {
                        return this.optional(element) || /\.(jpe?g|png|webp|gif|bmp|svg)$/i.test(value);
                    }, 'Por favor, seleccione un archivo de imagen válido (jpg, png, webp, gif).');
                }

                $('#catalog-form').validate({
                    rules: {
                        name: {
                            required: true,
                            maxlength: 255
                        },
                        company_name: {
                            maxlength: 255
                        },
                        contact_email: {
                            email: true,
                            maxlength: 255
                        },
                        contact_phone: {
                            maxlength: 50
                        },
                        contact_address: {
                            maxlength: 255
                        },
                        template: {
                            required: true
                        },
                        logo: {
                            imageFile: true
                        }
                    },
                    messages: {
                        name: {
                            required: "Este campo es obligatorio.",
                            maxlength: "Por favor, no escribas más de 255 caracteres."
                        },
                        company_name: {
                            maxlength: "Por favor, no escribas más de 255 caracteres."
                        },
                        contact_email: {
                            email: "Por favor, escribe una dirección de correo válida.",
                            maxlength: "Por favor, no escribas más de 255 caracteres."
                        },
                        contact_phone: {
                            maxlength: "Por favor, no escribas más de 50 caracteres."
                        },
                        contact_address: {
                            maxlength: "Por favor, no escribas más de 255 caracteres."
                        },
                        template: {
                            required: "Este campo es obligatorio."
                        },
                        logo: {
                            imageFile: "Por favor, seleccione un archivo de imagen válido."
                        }
                    }
                });
            }

            const categoriesContainer = document.getElementById('categories-sortable');

            function refreshCategoryOrderUI() {
                if (!categoriesContainer) return;
                const cards = Array.from(categoriesContainer.querySelectorAll(':scope > .category-card'));
                cards.forEach((card, index) => {
                    const badge = card.querySelector('.category-order-badge');
                    if (badge) badge.textContent = '#' + (index + 1);
                    const upBtn = card.querySelector('.move-category-up');
                    const downBtn = card.querySelector('.move-category-down');
                    if (upBtn) upBtn.disabled = index === 0;
                    if (downBtn) downBtn.disabled = index === cards.length - 1;
                });
            }

            function refreshProductOrderUI(productsContainer) {
                if (!productsContainer) return;
                const items = Array.from(productsContainer.querySelectorAll(':scope > .product-item'));
                items.forEach((item, index) => {
                    const badge = item.querySelector('.product-order-badge');
                    if (badge) badge.textContent = String(index + 1);
                    const upBtn = item.querySelector('.move-product-up');
                    const downBtn = item.querySelector('.move-product-down');
                    if (upBtn) upBtn.disabled = index === 0;
                    if (downBtn) downBtn.disabled = index === items.length - 1;
                });
            }

            if (window.jQuery && $.fn && $.fn.sortable) {
                $('#categories-sortable').sortable({
                    handle: '.category-drag-handle',
                    items: '> .category-card',
                    axis: 'y',
                    tolerance: 'pointer',
                    update: function() {
                        refreshCategoryOrderUI();
                    }
                });

                $('.products-sortable').sortable({
                    handle: '.product-drag-handle',
                    items: '> .product-item',
                    axis: 'y',
                    tolerance: 'pointer',
                    update: function() {
                        refreshProductOrderUI(this);
                    }
                });
            }

            if (categoriesContainer) {
                categoriesContainer.addEventListener('click', function(event) {
                    const catUp = event.target.closest('.move-category-up');
                    const catDown = event.target.closest('.move-category-down');
                    if (catUp || catDown) {
                        const card = event.target.closest('.category-card');
                        if (!card || card.parentNode !== categoriesContainer) return;
                        if (catUp && card.previousElementSibling) {
                            categoriesContainer.insertBefore(card, card.previousElementSibling);
                        } else if (catDown && card.nextElementSibling) {
                            categoriesContainer.insertBefore(card.nextElementSibling, card);
                        }
                        refreshCategoryOrderUI();
                        return;
                    }

                    const prodUp = event.target.closest('.move-product-up');
                    const prodDown = event.target.closest('.move-product-down');
                    if (prodUp || prodDown) {
                        const item = event.target.closest('.product-item');
                        if (!item) return;
                        const list = item.parentNode;
                        if (!list || !list.classList.contains('products-sortable')) return;
                        if (prodUp && item.previousElementSibling) {
                            list.insertBefore(item, item.previousElementSibling);
                        } else if (prodDown && item.nextElementSibling) {
                            list.insertBefore(item.nextElementSibling, item);
                        }
                        refreshProductOrderUI(list);
                    }
                });

                refreshCategoryOrderUI();
                document.querySelectorAll('.products-sortable').forEach(list => refreshProductOrderUI(list));
            }

            const globalCheckbox = document.getElementById('selectAllGlobal');
            const categoryCheckboxes = document.querySelectorAll('.select-category');
            const allProductCheckboxes = document.querySelectorAll('.product-checkbox');

            // Global Select All
            if (globalCheckbox) {
                globalCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    allProductCheckboxes.forEach(cb => cb.checked = isChecked);
                    categoryCheckboxes.forEach(cb => {
                        cb.checked = isChecked;
                        cb.indeterminate = false;
                    });
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
