@extends('layouts::app')

@section('title', __rs('slide.create'))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Navegación --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="breadcrumb">
                <a href="{{ route('react-sliders.index') }}">{{ __rs('slider.sliders') }}</a>
                <span class="mx-2">/</span>
                <a href="{{ route('react-sliders.edit', ['id' => $slider->id]) }}">{{ e($slider->name) }}</a>
                <span class="mx-2">/</span>
                <span>{{ __rs('slide.create') }}</span>
            </div>
            <a href="{{ route('react-sliders.edit', ['id' => $slider->id]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Slider
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-plus-circle me-2"></i>{{ __rs('slide.add_slide') }} para "{{ e($slider->name) }}"
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('react-sliders.slides.store', ['sliderId' => $slider->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="slider_id" value="{{ $slider->id }}">

                    <div class="row">
                        <div class="col-md-8">

                            {{-- Imagen --}}
                            <div class="mb-3">
                                <label for="image" class="form-label">{{ __rs('slide.image') }} <span class="text-danger">*</span></label>
                                <input type="file"
                                       class="form-control @error('image') is-invalid @enderror"
                                       id="image"
                                       name="image"
                                       accept="image/*"
                                       required>
                                <small class="text-muted">Formatos permitidos: JPG, PNG, GIF. Máx 10MB.</small>
                                @error('image')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Título --}}
                            <div class="mb-3">
                                <label for="title" class="form-label">{{ __rs('slide.title_field') }}</label>
                                <input type="text"
                                       class="form-control @error('title') is-invalid @enderror"
                                       id="title"
                                       name="title"
                                       value="{{ old('title') }}"
                                       placeholder="{{ __rs('slide.title_field') }}">
                                <small class="text-muted">Texto principal que se superpone a la imagen</small>
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Subtítulo --}}
                            <div class="mb-3">
                                <label for="subtitle" class="form-label">{{ __rs('slide.subtitle') }}</label>
                                <input type="text"
                                       class="form-control @error('subtitle') is-invalid @enderror"
                                       id="subtitle"
                                       name="subtitle"
                                       value="{{ old('subtitle') }}"
                                       placeholder="{{ __rs('slide.subtitle') }}">
                                @error('subtitle')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Descripción --}}
                            <div class="mb-3">
                                <label for="description" class="form-label">{{ __rs('slide.description') }}</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description"
                                          name="description"
                                          rows="3">{{ old('description') }}</textarea>
                                <small class="text-muted">Texto adicional corto</small>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Botón de acción --}}
                            <h5 class="mb-3 mt-4 text-primary border-bottom pb-2">
                                <i class="bi bi-link-45deg me-2"></i>Botón de Acción (Opcional)
                            </h5>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="button_text" class="form-label">{{ __rs('slide.button_text') }}</label>
                                    <input type="text"
                                           class="form-control @error('button_text') is-invalid @enderror"
                                           id="button_text"
                                           name="button_text"
                                           value="{{ old('button_text') }}"
                                           placeholder="Ej: Ver más">
                                    @error('button_text')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="button_link" class="form-label">{{ __rs('slide.button_link') }}</label>
                                    <input type="url"
                                           class="form-control @error('button_link') is-invalid @enderror"
                                           id="button_link"
                                           name="button_link"
                                           value="{{ old('button_link') }}"
                                           placeholder="https://...">
                                    @error('button_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="button_target" class="form-label">{{ __rs('slide.button_target') }}</label>
                                <select class="form-select @error('button_target') is-invalid @enderror"
                                        id="button_target"
                                        name="button_target">
                                    <option value="_self" {{ old('button_target', '_self') == '_self' ? 'selected' : '' }}>{{ __rs('slide.target_self') }}</option>
                                    <option value="_blank" {{ old('button_target') == '_blank' ? 'selected' : '' }}>{{ __rs('slide.target_blank') }}</option>
                                </select>
                                @error('button_target')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                        </div>

                        <div class="col-md-4">

                            {{-- Estado --}}
                            <div class="card mb-3">
                                <div class="card-header">Estado</div>
                                <div class="card-body">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="is_active"
                                               name="is_active"
                                               value="1"
                                               {{ old('is_active', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            {{ __rs('slide.active') }}
                                        </label>
                                        <small class="d-block text-muted mt-2">Desmarcar para ocultar esta diapositiva</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Colores --}}
                            <div class="card mb-3">
                                <div class="card-header">Personalización</div>
                                <div class="card-body">

                                    <div class="mb-3">
                                        <label for="background_color" class="form-label">{{ __rs('slide.background_color') }}</label>
                                        <input type="color"
                                               class="form-control form-control-color"
                                               id="background_color"
                                               name="background_color"
                                               value="{{ old('background_color', '#000000') }}">
                                    </div>

                                    <div class="mb-3">
                                        <label for="text_color" class="form-label">{{ __rs('slide.text_color') }}</label>
                                        <input type="color"
                                               class="form-control form-control-color"
                                               id="text_color"
                                               name="text_color"
                                               value="{{ old('text_color', '#ffffff') }}">
                                    </div>

                                    <div class="mb-3">
                                        <label for="overlay_opacity" class="form-label">{{ __rs('slide.overlay_opacity') }}</label>
                                        <input type="range"
                                               class="form-range"
                                               id="overlay_opacity"
                                               name="overlay_opacity"
                                               min="0"
                                               max="1"
                                               step="0.1"
                                               value="{{ old('overlay_opacity', '0.3') }}">
                                        <small class="text-muted">Valor: <span id="opacity-value">0.3</span></small>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Botones de acción --}}
                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>{{ __rs('slider.save') }}
                        </button>
                        <a href="{{ route('react-sliders.edit', ['id' => $slider->id]) }}" class="btn btn-secondary">
                            <i class="bi bi-x-lg me-2"></i>{{ __rs('slider.cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
// Actualizar valor de opacidad en tiempo real
document.getElementById('overlay_opacity').addEventListener('input', function() {
    document.getElementById('opacity-value').textContent = this.value;
});
</script>
@endpush
