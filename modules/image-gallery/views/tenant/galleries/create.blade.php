@extends('layouts::app')

@section('title', $title ?? __gallery('gallery.create'))

@push('styles')
<link rel="stylesheet" href="/modules/image-gallery/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <a href="{{ route('tenant.image-gallery.index') }}">{{ __gallery('gallery.my_galleries') }}</a>
                        </li>
                        <li class="breadcrumb-item active">{{ __gallery('gallery.create') }}</li>
                    </ol>
                </nav>
                <h2 class="mb-0"><i class="bi bi-plus-circle me-2"></i>{{ $title ?? __gallery('gallery.create') }}</h2>
            </div>
        </div>

        <!-- Alertas -->
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>{!! session('error') !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Formulario -->
        <form action="{{ route('tenant.image-gallery.store') }}" method="POST">
            @csrf

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __gallery('gallery.basic_info') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __gallery('gallery.name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="slug" class="form-label">{{ __gallery('gallery.slug') }}
                                    <span id="slug-check-result" class="ms-2 fw-bold"></span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug') }}" pattern="[a-z0-9\-]+">
                                </div>
                                <div class="form-text">{{ __gallery('gallery.slug_help') }}</div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">{{ __gallery('gallery.description') }}</label>
                                <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Layout -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __gallery('gallery.layout_settings') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label class="form-label">{{ __gallery('gallery.layout_type') }}</label>
                                <div class="row g-3">
                                    @foreach($layouts as $key => $layout)
                                        <div class="col-6 col-md-4">
                                            <input type="radio" class="btn-check" name="layout_type" id="layout_{{ $key }}" value="{{ $key }}" {{ old('layout_type', 'grid') === $key ? 'checked' : '' }}>
                                            <label class="btn btn-outline-primary w-100 py-3" for="layout_{{ $key }}">
                                                <i class="bi {{ $layout['icon'] }} d-block mb-2 fs-4"></i>
                                                <span class="d-block fw-medium">{{ $layout['name'] }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="columns" class="form-label">{{ __gallery('gallery.columns') }}</label>
                                    <select class="form-select" id="columns" name="columns">
                                        @for($i = 1; $i <= 6; $i++)
                                            <option value="{{ $i }}" {{ old('columns', 3) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="gap" class="form-label">{{ __gallery('gallery.gap') }}</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="gap" name="gap" value="{{ old('gap', 10) }}" min="0" max="50">
                                        <span class="input-group-text">px</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
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
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg me-1"></i> {{ __gallery('gallery.create') }}
                                </button>
                                <a href="{{ route('tenant.image-gallery.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-1"></i> {{ __gallery('gallery.cancel') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
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

    // Verificación inicial si hay un slug
    if (slugInput && slugInput.value) {
        checkSlug(slugInput.value);
    }
});
</script>
@endpush
