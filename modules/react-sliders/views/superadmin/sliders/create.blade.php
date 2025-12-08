@extends('layouts::app')

@section('title', __rs('slider.add_slider'))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Navegación --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="breadcrumb">
                <a href="{{ route('react-sliders.index') }}">{{ __rs('slider.sliders') }}</a>
                <span class="mx-2">/</span>
                <span>{{ __rs('slider.create') }}</span>
            </div>
            <a href="{{ route('react-sliders.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle me-2"></i>{{ __rs('slider.add_slider') }}
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('react-sliders.store') }}">
                    @csrf

                    {{-- Información Básica --}}
                    <h5 class="mb-3 text-primary border-bottom pb-2">
                        <i class="fas fa-info-circle me-2"></i>{{ __rs('slider.basic_information') }}
                    </h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __rs('slider.name') }} <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       id="name"
                                       name="name"
                                       value="{{ old('name') }}"
                                       placeholder="{{ __rs('slider.name_placeholder') }}"
                                       required>
                                <small class="text-muted">{{ __rs('slider.name_placeholder') }}</small>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="identifier" class="form-label">{{ __rs('slider.identifier') }} <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control font-monospace @error('identifier') is-invalid @enderror"
                                       id="identifier"
                                       name="identifier"
                                       value="{{ old('identifier') }}"
                                       placeholder="{{ __rs('slider.identifier_placeholder') }}"
                                       required>
                                <small class="text-muted">{{ __rs('slider.identifier_help') }}</small>
                                @error('identifier')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="engine" class="form-label">{{ __rs('slider.engine') }} <span class="text-danger">*</span></label>
                                <select class="form-select @error('engine') is-invalid @enderror"
                                        id="engine"
                                        name="engine"
                                        required>
                                    <option value="swiper" {{ old('engine', 'swiper') == 'swiper' ? 'selected' : '' }}>Swiper.js (Recomendado)</option>
                                    <option value="slick" {{ old('engine') == 'slick' ? 'selected' : '' }}>Slick Slider</option>
                                    <option value="glide" {{ old('engine') == 'glide' ? 'selected' : '' }}>Glide.js</option>
                                </select>
                                @error('engine')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check mt-4 pt-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="is_active"
                                           name="is_active"
                                           value="1"
                                           {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        {{ __rs('slider.is_active') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Configuración del Slider --}}
                    <h5 class="mb-3 mt-4 text-primary border-bottom pb-2">
                        <i class="fas fa-cog me-2"></i>{{ __rs('slider.settings') }}
                    </h5>

                    {{-- Opciones de Comportamiento --}}
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       id="autoplay"
                                                       name="autoplay"
                                                       value="1"
                                                       {{ old('autoplay', true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="autoplay">
                                                    {{ __rs('slider.autoplay') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       id="loop"
                                                       name="loop"
                                                       value="1"
                                                       {{ old('loop', true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="loop">
                                                    {{ __rs('slider.loop') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       id="navigation"
                                                       name="navigation"
                                                       value="1"
                                                       {{ old('navigation', true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="navigation">
                                                    {{ __rs('slider.navigation') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       id="pagination"
                                                       name="pagination"
                                                       value="1"
                                                       {{ old('pagination', true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="pagination">
                                                    {{ __rs('slider.pagination') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Parámetros Numéricos --}}
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="animation" class="form-label">{{ __rs('slider.animation') }}</label>
                                <select class="form-select" id="animation" name="animation">
                                    <option value="slide" {{ old('animation', 'slide') == 'slide' ? 'selected' : '' }}>Slide</option>
                                    <option value="fade" {{ old('animation') == 'fade' ? 'selected' : '' }}>Fade</option>
                                    <option value="cube" {{ old('animation') == 'cube' ? 'selected' : '' }}>Cube</option>
                                    <option value="coverflow" {{ old('animation') == 'coverflow' ? 'selected' : '' }}>Coverflow</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="autoplay_delay" class="form-label">{{ __rs('slider.autoplay_delay') }}</label>
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control"
                                           id="autoplay_delay"
                                           name="autoplay_delay"
                                           value="{{ old('autoplay_delay', 3000) }}"
                                           min="1000"
                                           step="100">
                                    <span class="input-group-text">ms</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="speed" class="form-label">{{ __rs('slider.speed') }}</label>
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control"
                                           id="speed"
                                           name="speed"
                                           value="{{ old('speed', 300) }}"
                                           min="100"
                                           step="100">
                                    <span class="input-group-text">ms</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="slides_per_view" class="form-label">{{ __rs('slider.slides_per_view') }}</label>
                                <input type="number"
                                       class="form-control"
                                       id="slides_per_view"
                                       name="slides_per_view"
                                       value="{{ old('slides_per_view', 1) }}"
                                       min="1"
                                       max="10">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="space_between" class="form-label">{{ __rs('slider.space_between') }}</label>
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control"
                                           id="space_between"
                                           name="space_between"
                                           value="{{ old('space_between', 0) }}"
                                           min="0"
                                           step="10">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Botones de Acción --}}
                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>{{ __rs('slider.save') }}
                        </button>
                        <a href="{{ route('react-sliders.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>{{ __rs('slider.cancel') }}
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
// Auto-generate identifier from name
document.getElementById('name').addEventListener('input', function(e) {
    const identifier = document.getElementById('identifier');
    if (!identifier.dataset.modified) {
        identifier.value = e.target.value
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Remove accents
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
});

document.getElementById('identifier').addEventListener('input', function() {
    this.dataset.modified = 'true';
});
</script>
@endpush
