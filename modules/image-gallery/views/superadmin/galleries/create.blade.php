@extends('layouts::app')

@section('title', $title ?? __gallery('gallery.create'))

@push('styles')
<link rel="stylesheet" href="/modules/image-gallery/css/admin.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="breadcrumb mb-0">
                <a href="{{ route('image-gallery.index') }}">{{ __gallery('gallery.galleries') }}</a>
                <span class="mx-2">/</span>
                <span>{{ __gallery('gallery.create') }}</span>
            </div>
        </div>

        <!-- Alertas manejadas por SweetAlert2 -->
        @if(session('error'))
            <script>
                window.galleryError = @json(session('error'));
            </script>
        @endif
        @if(session('success'))
            <script>
                window.gallerySuccess = @json(session('success'));
            </script>
        @endif

        <!-- Formulario -->
        <form action="{{ route('image-gallery.store') }}" method="POST" id="galleryForm">
            @csrf

            <div class="row g-4">
                <!-- Columna principal -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __gallery('gallery.basic_info') }}</h5>
                        </div>
                        <div class="card-body">
                            <!-- Nombre -->
                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __gallery('gallery.name') }} <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="name"
                                       name="name"
                                       value="{{ old('name') }}"
                                       required
                                       placeholder="{{ __gallery('gallery.name_placeholder') }}">
                            </div>

                            <!-- Slug -->
                            <div class="mb-3">
                                <label for="slug" class="form-label">{{ __gallery('gallery.slug') }}
                                    <span id="slug-check-result" class="ms-2 fw-bold"></span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text"
                                           class="form-control"
                                           id="slug"
                                           name="slug"
                                           value="{{ old('slug') }}"
                                           pattern="[a-z0-9\-]+"
                                           placeholder="{{ __gallery('gallery.slug_placeholder') }}">
                                </div>
                                <div class="form-text">{{ __gallery('gallery.slug_help') }}</div>
                            </div>

                            <!-- Descripción -->
                            <div class="mb-3">
                                <label for="description" class="form-label">{{ __gallery('gallery.description') }}</label>
                                <textarea class="form-control"
                                          id="description"
                                          name="description"
                                          rows="3"
                                          placeholder="{{ __gallery('gallery.description_placeholder') }}">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de layout -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __gallery('gallery.layout_settings') }}</h5>
                        </div>
                        <div class="card-body">
                            <!-- Tipo de layout -->
                            <div class="mb-4">
                                <label class="form-label">{{ __gallery('gallery.layout_type') }}</label>
                                <div class="row g-3">
                                    @foreach($layouts as $key => $layout)
                                        <div class="col-6 col-md-4">
                                            <input type="radio"
                                                   class="btn-check"
                                                   name="layout_type"
                                                   id="layout_{{ $key }}"
                                                   value="{{ $key }}"
                                                   {{ old('layout_type', 'grid') === $key ? 'checked' : '' }}>
                                            <label class="btn btn-outline-primary w-100 py-3" for="layout_{{ $key }}">
                                                <i class="bi {{ $layout['icon'] }} d-block mb-2 fs-4"></i>
                                                <span class="d-block fw-medium">{{ $layout['name'] }}</span>
                                                <small class="d-block text-muted">{{ $layout['description'] }}</small>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Columnas y Gap -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="columns" class="form-label">{{ __gallery('gallery.columns') }}</label>
                                    <select class="form-select" id="columns" name="columns">
                                        @for($i = 1; $i <= 6; $i++)
                                            <option value="{{ $i }}" {{ old('columns', 3) == $i ? 'selected' : '' }}>
                                                {{ $i }} {{ $i === 1 ? __gallery('gallery.column') : __gallery('gallery.columns_label') }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="gap" class="form-label">{{ __gallery('gallery.gap') }}</label>
                                    <div class="input-group">
                                        <input type="number"
                                               class="form-control"
                                               id="gap"
                                               name="gap"
                                               value="{{ old('gap', 10) }}"
                                               min="0"
                                               max="50">
                                        <span class="input-group-text">px</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración avanzada -->
                    <div class="card mt-4">
                        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#advancedSettings" style="cursor: pointer;">
                            <h5 class="mb-0">
                                <i class="bi bi-gear me-2"></i>{{ __gallery('gallery.advanced_settings') }}
                                <i class="bi bi-chevron-down float-end"></i>
                            </h5>
                        </div>
                        <div class="collapse" id="advancedSettings">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __gallery('gallery.hover_effect') }}</label>
                                        <select class="form-select" name="settings[hover_effect]">
                                            <option value="none">{{ __gallery('gallery.effect_none') }}</option>
                                            <option value="zoom" selected>{{ __gallery('gallery.effect_zoom') }}</option>
                                            <option value="fade">{{ __gallery('gallery.effect_fade') }}</option>
                                            <option value="slide">{{ __gallery('gallery.effect_slide') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __gallery('gallery.image_fit') }}</label>
                                        <select class="form-select" name="settings[image_fit]">
                                            <option value="cover" selected>{{ __gallery('gallery.fit_cover') }}</option>
                                            <option value="contain">{{ __gallery('gallery.fit_contain') }}</option>
                                            <option value="fill">{{ __gallery('gallery.fit_fill') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __gallery('gallery.aspect_ratio') }}</label>
                                        <select class="form-select" name="settings[aspect_ratio]">
                                            <option value="1:1">1:1 ({{ __gallery('gallery.ratio_square') }})</option>
                                            <option value="4:3">4:3</option>
                                            <option value="16:9">16:9</option>
                                            <option value="3:2">3:2</option>
                                            <option value="auto">{{ __gallery('gallery.ratio_auto') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __gallery('gallery.border_radius') }}</label>
                                        <div class="input-group">
                                            <input type="number"
                                                   class="form-control"
                                                   name="settings[border_radius]"
                                                   value="8"
                                                   min="0"
                                                   max="50">
                                            <span class="input-group-text">px</span>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="settings[show_title]" id="show_title" value="1" checked>
                                            <label class="form-check-label" for="show_title">{{ __gallery('gallery.show_title') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="settings[show_caption]" id="show_caption" value="1" checked>
                                            <label class="form-check-label" for="show_caption">{{ __gallery('gallery.show_caption') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="settings[enable_lightbox]" id="enable_lightbox" value="1" checked>
                                            <label class="form-check-label" for="enable_lightbox">{{ __gallery('gallery.enable_lightbox') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="settings[enable_lazy_loading]" id="enable_lazy_loading" value="1" checked>
                                            <label class="form-check-label" for="enable_lazy_loading">{{ __gallery('gallery.enable_lazy_loading') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna lateral -->
                <div class="col-lg-4">
                    <!-- Estado -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __gallery('gallery.status') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                    <label class="form-check-label" for="is_active">{{ __gallery('gallery.is_active') }}</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1">
                                    <label class="form-check-label" for="featured">{{ __gallery('gallery.featured') }}</label>
                                </div>
                            </div>
                            <div class="mb-0">
                                <label for="sort_order" class="form-label">{{ __gallery('gallery.sort_order') }}</label>
                                <input type="number"
                                       class="form-control"
                                       id="sort_order"
                                       name="sort_order"
                                       value="{{ old('sort_order', 0) }}"
                                       min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg me-1"></i> {{ __gallery('gallery.create') }}
                                </button>
                                <a href="{{ route('image-gallery.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-1"></i> {{ __gallery('gallery.cancel') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Ayuda -->
                    <div class="card mt-4 bg-light">
                        <div class="card-body">
                            <h6><i class="bi bi-lightbulb me-2"></i>{{ __gallery('gallery.tip') }}</h6>
                            <p class="small text-muted mb-0">{{ __gallery('gallery.create_tip') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar errores con SweetAlert2
    if (window.galleryError) {
        Swal.fire({
            icon: 'error',
            title: '{{ __gallery("gallery.error") ?? "Error" }}',
            html: window.galleryError,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#dc3545'
        });
    }

    // Mostrar éxito con SweetAlert2
    if (window.gallerySuccess) {
        Swal.fire({
            icon: 'success',
            title: '{{ __gallery("gallery.success") ?? "Éxito" }}',
            text: window.gallerySuccess,
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#198754',
            timer: 3000,
            timerProgressBar: true
        });
    }

    // Variables para validación de slug
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const resultSpan = document.getElementById('slug-check-result');
    let timeoutId = null;
    let slugManuallyEdited = false;

    function getCsrfToken() {
        return document.querySelector('input[name="_token"]')?.value ||
               document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
               document.querySelector('input[name="_csrf"]')?.value ||
               '';
    }

    function setCsrfToken(token) {
        if (!token) return;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        document.querySelectorAll('input[name="_token"], input[name="_csrf"]').forEach((input) => {
            input.value = token;
        });
    }

    // Función para generar slug
    function slugify(text) {
        return text.toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/--+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    // Auto-generar slug desde el nombre
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            if (!slugManuallyEdited) {
                const cleanSlug = slugify(this.value);
                slugInput.value = cleanSlug;
                checkSlug(cleanSlug);
            }
        });
    }

    // Event listener para el slug
    if (slugInput) {
        slugInput.addEventListener('input', function() {
            slugManuallyEdited = this.value.length > 0;
            const clean = slugify(this.value);
            if (this.value !== clean) { this.value = clean; }
            checkSlug(clean);
        });
    }

    // Función para verificar slug (igual que páginas)
    function checkSlug(slug) {
        if (!slug || !slugInput || !resultSpan) {
            if (resultSpan) {
                resultSpan.textContent = '';
                resultSpan.style.color = '';
            }
            return;
        }

        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            const formData = new URLSearchParams();
            formData.append('slug', slug);
            formData.append('module', 'galleries');

            const csrfToken = getCsrfToken();

            if (csrfToken) {
                // Compatibilidad: el middleware acepta _token y _csrf
                formData.append('_token', csrfToken);
            }

            const headers = {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            const doRequest = () => fetch('/ajax/check-slug', {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: formData.toString()
            });

            doRequest()
            .then(async (res) => {
                const data = await res.json().catch(() => null);

                if (!res.ok) {
                    // Si la sesión/token expiró, el middleware devuelve un nuevo token para reintentar.
                    if (res.status === 419 && data && data.new_csrf_token) {
                        setCsrfToken(data.new_csrf_token);
                        headers['X-CSRF-TOKEN'] = data.new_csrf_token;
                        formData.set('_token', data.new_csrf_token);
                        return doRequest().then(r => r.json());
                    }
                    throw new Error(data?.message || 'Network response was not ok');
                }

                return data;
            })
            .then(data => {
                if (resultSpan) {
                    if (data.exists) {
                        resultSpan.textContent = '{{ __gallery("validation.slug_exists") }}';
                        resultSpan.style.color = '#dc3545';
                    } else {
                        resultSpan.textContent = '{{ __gallery("validation.slug_available") }}';
                        resultSpan.style.color = '#28a745';
                    }
                }
            })
            .catch((error) => {
                if (resultSpan) {
                    resultSpan.textContent = 'Error al verificar';
                    resultSpan.style.color = '#fd7e14';
                }
            });
        }, 400);
    }

    // Validación de slug en el formulario
    const form = document.getElementById('galleryForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const slug = slugInput.value.trim();
            if (slug && !/^[a-z0-9\-]+$/.test(slug)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Slug inválido',
                    text: 'El slug solo puede contener letras minúsculas, números y guiones.',
                    confirmButtonText: 'Corregir',
                    confirmButtonColor: '#ffc107'
                }).then(() => {
                    slugInput.focus();
                });
            }
        });
    }

    // Verificación inicial si hay un slug
    if (slugInput && slugInput.value) {
        checkSlug(slugInput.value);
    }
});
</script>
@endpush
