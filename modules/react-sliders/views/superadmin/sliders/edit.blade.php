@extends('layouts::app')

@section('title', __rs('slider.edit'))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Navegación --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="breadcrumb">
                <a href="{{ route('react-sliders.index') }}">{{ __rs('slider.sliders') }}</a>
                <span class="mx-2">/</span>
                <span>{{ __rs('slider.edit') }}: {{ e($slider->name) }}</span>
            </div>
            <a href="{{ route('react-sliders.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Listado
            </a>
        </div>

        {{-- Mensajes de éxito/error --}}
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <form method="POST" action="{{ route('react-sliders.update', ['id' => $slider->id]) }}">
            @csrf
            @method('PUT')

            <div class="row">

                {{-- Panel izquierdo (Datos principales y Diapositivas) --}}
                <div class="col-lg-8">

                    {{-- Información del Slider --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-info-circle me-2"></i>{{ __rs('slider.basic_information') }}
                        </div>
                        <div class="card-body">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">{{ __rs('slider.name') }} <span class="text-danger">*</span></label>
                                    <input type="text"
                                           class="form-control @error('name') is-invalid @enderror"
                                           id="name"
                                           name="name"
                                           value="{{ old('name', $slider->name) }}"
                                           placeholder="{{ __rs('slider.name_placeholder') }}"
                                           required>
                                    <small class="text-muted">{{ __rs('slider.name_placeholder') }}</small>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="identifier" class="form-label">{{ __rs('slider.identifier') }} <span class="text-danger">*</span></label>
                                    <input type="text"
                                           class="form-control font-monospace @error('identifier') is-invalid @enderror"
                                           id="identifier"
                                           name="identifier"
                                           value="{{ old('identifier', $slider->identifier) }}"
                                           placeholder="{{ __rs('slider.identifier_placeholder') }}"
                                           required>
                                    <small class="text-muted">{{ __rs('slider.identifier_help') }}</small>
                                    @error('identifier')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="engine" class="form-label">{{ __rs('slider.engine') }} <span class="text-danger">*</span></label>
                                    <select class="form-select @error('engine') is-invalid @enderror"
                                            id="engine"
                                            name="engine"
                                            required>
                                        <option value="swiper" {{ old('engine', $slider->engine) == 'swiper' ? 'selected' : '' }}>Swiper.js (Recomendado)</option>
                                        <option value="slick" {{ old('engine', $slider->engine) == 'slick' ? 'selected' : '' }}>Slick Slider</option>
                                        <option value="glide" {{ old('engine', $slider->engine) == 'glide' ? 'selected' : '' }}>Glide.js</option>
                                    </select>
                                    @error('engine')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check mt-4 pt-2">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   id="is_active"
                                                   name="is_active"
                                                   value="1"
                                                   {{ old('is_active', $slider->is_active) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="is_active">
                                                {{ __rs('slider.is_active') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Diapositivas --}}
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-images me-2"></i>{{ __rs('slide.title') }}</span>
                            <a href="{{ route('react-sliders.slides.create', ['sliderId' => $slider->id]) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> {{ __rs('slide.add_slide') }}
                            </a>
                        </div>
                        <div class="card-body table-responsive p-0">
                            @php
                                $slides = $slider->slides();
                            @endphp
                            @if($slides && count($slides) > 0)
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">Orden</th>
                                            <th style="width: 100px;">{{ __rs('slide.image') }}</th>
                                            <th>{{ __rs('slide.title_field') }}</th>
                                            <th>{{ __rs('slide.active') }}</th>
                                            <th class="text-end">{{ __rs('slider.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="slides-list-{{ $slider->id }}">
                                        @foreach($slides as $slide)
                                            <tr data-slide-id="{{ $slide->id }}">
                                                <td class="drag-handle" style="cursor: grab;">
                                                    <i class="bi bi-arrows-move"></i> {{ $slide->order }}
                                                </td>
                                                <td>
                                                    @if($slide->image)
                                                        <img src="{{ asset('storage/' . $slide->image) }}" alt="{{ e($slide->title ?? 'Slide') }}" style="max-width: 80px; border-radius: 4px;">
                                                    @else
                                                        <span class="text-muted">Sin imagen</span>
                                                    @endif
                                                </td>
                                                <td>{{ e($slide->title ?? '-') }}</td>
                                                <td>
                                                    @if($slide->is_active)
                                                        <span class="badge bg-success">Sí</span>
                                                    @else
                                                        <span class="badge bg-secondary">No</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('react-sliders.slides.edit', ['sliderId' => $slider->id, 'slideId' => $slide->id]) }}"
                                                           class="btn btn-outline-primary"
                                                           title="{{ __rs('slide.edit') }}">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button type="button"
                                                                class="btn btn-outline-danger"
                                                                title="{{ __rs('slide.delete') }}"
                                                                onclick="confirmDeleteSlide({{ $slide->id }})">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-images" style="font-size: 3rem; color: #dee2e6;"></i>
                                    <p class="mt-3">{{ __rs('slide.no_slides_found') }}.</p>
                                    <a href="{{ route('react-sliders.slides.create', ['sliderId' => $slider->id]) }}" class="btn btn-primary mt-2">
                                        <i class="bi bi-plus-lg me-1"></i>{{ __rs('slide.add_slide') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>

                {{-- Panel derecho (Settings) --}}
                <div class="col-lg-4">

                    {{-- Configuración del Slider --}}
                    <div class="card mb-3">
                        <div class="card-header">{{ __rs('slider.settings') }}</div>
                        <div class="card-body">

                            {{-- Opciones de comportamiento --}}
                            <div class="mb-3">
                                <div class="form-check mb-2">
                                    <input type="hidden" name="autoplay" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           name="autoplay"
                                           id="autoplay"
                                           value="1"
                                           {{ old('autoplay', $settings['autoplay'] ?? 1) == 1 ? 'checked' : '' }}>
                                    <label class="form-check-label" for="autoplay">{{ __rs('slider.autoplay') }}</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="hidden" name="loop" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           name="loop"
                                           id="loop"
                                           value="1"
                                           {{ old('loop', $settings['loop'] ?? 1) == 1 ? 'checked' : '' }}>
                                    <label class="form-check-label" for="loop">{{ __rs('slider.loop') }}</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="hidden" name="navigation" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           name="navigation"
                                           id="navigation"
                                           value="1"
                                           {{ old('navigation', $settings['navigation'] ?? 1) == 1 ? 'checked' : '' }}>
                                    <label class="form-check-label" for="navigation">{{ __rs('slider.navigation') }}</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="hidden" name="pagination" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           name="pagination"
                                           id="pagination"
                                           value="1"
                                           {{ old('pagination', $settings['pagination'] ?? 1) == 1 ? 'checked' : '' }}>
                                    <label class="form-check-label" for="pagination">{{ __rs('slider.pagination') }}</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="hidden" name="full_width" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           name="full_width"
                                           id="full_width"
                                           value="1"
                                           {{ old('full_width', $settings['full_width'] ?? 0) == 1 ? 'checked' : '' }}>
                                    <label class="form-check-label" for="full_width">{{ __rs('slider.full_width') }}</label>
                                    <small class="form-text text-muted d-block">{{ __rs('slider.full_width_help') }}</small>
                                </div>
                            </div>

                            {{-- Parámetros numéricos --}}
                            <div class="mb-3">
                                <label for="animation" class="form-label">{{ __rs('slider.animation') }}</label>
                                <select class="form-select" id="animation" name="animation">
                                    <option value="slide" {{ old('animation', $settings['animation'] ?? 'slide') == 'slide' ? 'selected' : '' }}>Slide</option>
                                    <option value="fade" {{ old('animation', $settings['animation'] ?? '') == 'fade' ? 'selected' : '' }}>Fade</option>
                                    <option value="cube" {{ old('animation', $settings['animation'] ?? '') == 'cube' ? 'selected' : '' }}>Cube</option>
                                    <option value="coverflow" {{ old('animation', $settings['animation'] ?? '') == 'coverflow' ? 'selected' : '' }}>Coverflow</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="autoplay_delay" class="form-label">{{ __rs('slider.autoplay_delay') }}</label>
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control"
                                           id="autoplay_delay"
                                           name="autoplay_delay"
                                           value="{{ old('autoplay_delay', $settings['autoplay_delay'] ?? 3000) }}"
                                           min="1000"
                                           step="100">
                                    <span class="input-group-text">ms</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="speed" class="form-label">{{ __rs('slider.speed') }}</label>
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control"
                                           id="speed"
                                           name="speed"
                                           value="{{ old('speed', $settings['speed'] ?? 300) }}"
                                           min="100"
                                           step="100">
                                    <span class="input-group-text">ms</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="slides_per_view" class="form-label">{{ __rs('slider.slides_per_view') }}</label>
                                <input type="number"
                                       class="form-control"
                                       id="slides_per_view"
                                       name="slides_per_view"
                                       value="{{ old('slides_per_view', $settings['slides_per_view'] ?? 1) }}"
                                       min="1"
                                       max="10">
                            </div>

                            <div class="mb-3">
                                <label for="space_between" class="form-label">{{ __rs('slider.space_between') }}</label>
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control"
                                           id="space_between"
                                           name="space_between"
                                           value="{{ old('space_between', $settings['space_between'] ?? 0) }}"
                                           min="0"
                                           step="10">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Botón Guardar --}}
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save me-2"></i>{{ __rs('slider.save') }}
                        </button>
                    </div>

                </div>
            </div>

        </form>

        {{-- Formularios de eliminación de slides (fuera del formulario principal) --}}
        @foreach($slides as $slide)
            <form id="delete-slide-form-{{ $slide->id }}"
                  action="{{ route('react-sliders.slides.destroy', ['sliderId' => $slider->id, 'slideId' => $slide->id]) }}"
                  method="POST"
                  style="display: none;">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
</div>

    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// Sortable.js for drag & drop reordering slides
const slidesList = document.getElementById('slides-list-{{ $slider->id }}');
if (slidesList) {
    new Sortable(slidesList, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function (evt) {
            // Update order via AJAX
            const slideIds = Array.from(slidesList.querySelectorAll('tr')).map(row => row.dataset.slideId);

            fetch('{{ route("react-sliders.slides.reorder", $slider->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ order: slideIds })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Orden actualizado correctamente');
                }
            })
            .catch(error => {
                console.error('Error al actualizar el orden:', error);
            });
        }
    });
}

// Confirm slide deletion with SweetAlert2
function confirmDeleteSlide(slideId) {
    Swal.fire({
        title: '{{ __rs("slide.delete") }}',
        text: '{{ __rs("messages.confirm_delete_slide") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-slide-form-' + slideId).submit();
        }
    });
}
</script>
@endpush
