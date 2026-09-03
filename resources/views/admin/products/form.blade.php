@extends('admin.layout.plantilla')
@section('banner')
    <div>
        <h2 class="text-white pb-2 fw-bold">Productos</h2>
        <h5 class="text-white op-7 mb-2">{{ $product->exists ? 'Editar producto' : 'Crear producto' }}</h5>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="card">
            <div class="card-body">
                <form action="{{ $product->exists ? route('productos.update', $product) : route('productos.store') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    @if ($product->exists)
                        @method('PUT')
                    @endif
                    <div class="row">
                        <div class="form-group col-md-12"><label>Nombre</label><input name="name" class="form-control"
                                value="{{ old('name', $product->name) }}" required></div>
                        <div class="form-group col-md-6"><label>Referencia</label><input name="reference"
                                class="form-control" value="{{ old('reference', $product->reference) }}"></div>
                        <div class="form-group col-md-3"><label>Categoría</label><select name="category_id"
                                class="form-control">
                                <option value="">Sin categoría</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3"><label>Disponibilidad</label><select name="availability"
                                class="form-control">
                                @foreach ($availabilityOptions as $option)
                                    <option value="{{ $option }}" @selected(old('availability', $product->availability ?: 'disponible') === $option)>{{ ucfirst($option) }}
                                    </option>
                                @endforeach
                            </select></div>
                        <div class="form-group col-md-12"><label>Imagen principal</label><input type="file"
                                name="image" class="form-control"></div>
                        <div class="form-group col-md-12">
                            <label>Galería de imágenes (puedes seleccionar varias)</label>
                            <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                            @error('images.*')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group col-md-6"><label>Descripción</label>
                            <textarea name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
                        </div>
                        <div class="form-group col-md-6"><label>Características</label>
                            <textarea name="features" class="form-control">{{ old('features', $product->features) }}</textarea>
                        </div>
                    </div>
                    <div class="form-group"><label><input type="checkbox" name="is_active" value="1"
                                {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}> Activo</label></div>
                    <button class="btn btn-primary">Guardar</button>
                    <a href="{{ route('productos.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>

                @if ($product->exists && ($product->images->isNotEmpty() || $product->image_path))
                    <div class="mt-3">
                        <label class="fw-bold">Imágenes actuales</label>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($product->image_path)
                                <div class="border rounded p-1 text-center">
                                    <img src="{{ asset('storage/' . $product->image_path) }}"
                                        style="height:80px;width:80px;object-fit:cover;" alt="Imagen principal">
                                    <div class="small text-muted">Principal</div>
                                </div>
                            @endif
                            @foreach ($product->images as $image)
                                <div class="border rounded p-1 text-center">
                                    <img src="{{ asset('storage/' . $image->path) }}"
                                        style="height:80px;width:80px;object-fit:cover;" alt="Imagen">
                                    <form action="{{ route('productos.images.destroy', [$product, $image]) }}"
                                        method="POST" onsubmit="return confirm('¿Eliminar esta imagen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger p-0">Eliminar</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
