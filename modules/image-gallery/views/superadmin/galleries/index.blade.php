@extends('layouts::app')

@section('title', $title ?? __gallery('gallery.galleries'))

@push('styles')
<link rel="stylesheet" href="/modules/image-gallery/css/admin.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-images me-2"></i>{{ $title ?? __gallery('gallery.galleries') }}</h2>
                <p class="text-muted mb-0">{{ __gallery('gallery.manage_galleries') }}</p>
            </div>
            <a href="{{ route('image-gallery.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> {{ __gallery('gallery.create') }}
            </a>
        </div>

        <!-- Alertas manejadas por SweetAlert2 -->
        @if(session('success'))
            <script>window.gallerySuccess = @json(session('success'));</script>
        @endif
        @if(session('error'))
            <script>window.galleryError = @json(session('error'));</script>
        @endif

        <!-- Lista de galerías -->
        @if(!empty($galleries))
            <div class="row g-4">
                @foreach($galleries as $gallery)
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="card gallery-card h-100">
                            <div class="gallery-thumbnail">
                                @if($gallery->thumbnail_url)
                                    <img src="{{ $gallery->thumbnail_url }}" alt="{{ e($gallery->name) }}" loading="lazy">
                                @else
                                    <div class="gallery-placeholder">
                                        <i class="bi bi-images"></i>
                                    </div>
                                @endif
                                <div class="gallery-overlay">
                                    <span class="badge bg-{{ $gallery->is_active ? 'success' : 'secondary' }}">
                                        {{ $gallery->is_active ? __gallery('gallery.active') : __gallery('gallery.inactive') }}
                                    </span>
                                    @if($gallery->featured)
                                        <span class="badge bg-warning ms-1">
                                            <i class="bi bi-star-fill"></i>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title mb-1">{{ $gallery->name }}</h5>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-link-45deg"></i> {{ $gallery->slug }}
                                </p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-{{ $layouts[$gallery->layout_type]['icon'] ?? 'grid' }}"></i>
                                        {{ $layouts[$gallery->layout_type]['name'] ?? $gallery->layout_type }}
                                    </span>
                                    <small class="text-muted">
                                        <i class="bi bi-image me-1"></i>{{ $gallery->imageCount() }}
                                    </small>
                                </div>
                                <p class="card-text small text-muted">
                                    {{ $gallery->description ? (mb_strlen($gallery->description) > 80 ? mb_substr($gallery->description, 0, 80) . '...' : $gallery->description) : '' }}
                                </p>
                            </div>
                            <div class="card-footer bg-transparent border-top-0">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('image-gallery.edit', ['id' => $gallery->id]) }}"
                                       class="btn btn-sm btn-outline-primary flex-grow-1">
                                        <i class="bi bi-pencil"></i> {{ __gallery('gallery.edit') }}
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            onclick="copyShortcode('{{ $gallery->id }}', '{{ $gallery->slug }}')"
                                            title="{{ __gallery('gallery.copy_shortcode') }}">
                                        <i class="bi bi-code-slash"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="confirmDelete({{ $gallery->id }}, '{{ e($gallery->name) }}')"
                                            title="{{ __gallery('gallery.delete') }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-images display-1 text-muted mb-3"></i>
                    <h4>{{ __gallery('gallery.no_galleries') }}</h4>
                    <p class="text-muted mb-4">{{ __gallery('gallery.no_galleries_desc') }}</p>
                    <a href="{{ route('image-gallery.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> {{ __gallery('gallery.create_first') }}
                    </a>
                </div>
            </div>
        @endif

        <!-- Información de uso -->
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>{{ __gallery('gallery.usage_info') }}</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">{{ __gallery('gallery.shortcode_info') }}</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <code>[gallery id=1]</code>
                            <small class="d-block text-muted mt-1">{{ __gallery('gallery.shortcode_by_id') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <code>[gallery slug="mi-galeria"]</code>
                            <small class="d-block text-muted mt-1">{{ __gallery('gallery.shortcode_by_slug') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 modal implementation (no HTML needed) -->

<!-- Toast para copiar shortcode -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="copyToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi bi-check-circle text-success me-2"></i>
            <strong class="me-auto">{{ __gallery('gallery.copied') }}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="copyToastBody"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Limpiar inmediatamente al cargar el script (antes del DOMContentLoaded)
(function() {
    // Cerrar cualquier instancia de Swal
    if (typeof Swal !== 'undefined') {
        Swal.close();
    }

    // Eliminar todos los elementos de SweetAlert2 del DOM
    const removeAllSwalElements = () => {
        const elements = document.querySelectorAll('.swal2-container, .swal2-popup, [class*="swal2"]');
        elements.forEach(el => {
            if (el && el.parentNode) {
                el.parentNode.removeChild(el);
            }
        });
    };

    // Ejecutar limpieza inmediatamente y después del DOM
    removeAllSwalElements();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeAllSwalElements);
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    // Mostrar mensajes flash con SweetAlert2
    if (window.gallerySuccess) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: window.gallerySuccess,
            confirmButtonColor: '#198754',
            timer: 3000,
            timerProgressBar: true
        });
    }

    if (window.galleryError) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: window.galleryError,
            confirmButtonColor: '#dc3545'
        });
    }
});

function confirmDelete(id, name) {
    Swal.fire({
        icon: 'warning',
        title: '{{ __gallery("gallery.confirm_delete") }}',
        html: '{{ __gallery("gallery.delete_warning") }}<br><br><strong>' + name + '</strong>',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> {{ __gallery("gallery.delete") }}',
        cancelButtonText: '{{ __gallery("gallery.cancel") }}'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("image-gallery.index") }}/' + id + '/delete';

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            document.body.appendChild(form);
            form.submit();
        }
    });
}

function copyShortcode(id, slug) {
    const shortcode = '[gallery id=' + id + ']';

    // Copiar al portapapeles
    navigator.clipboard.writeText(shortcode).then(function() {
        showCopyToast(shortcode);
    }).catch(function() {
        // Fallback para navegadores antiguos
        const textArea = document.createElement('textarea');
        textArea.value = shortcode;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showCopyToast(shortcode);
    });
}

function showCopyToast(shortcode) {
    const toastElement = document.getElementById('copyToast');
    const toastBody = document.getElementById('copyToastBody');
    toastBody.textContent = shortcode;

    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000
    });

    toast.show();
}
</script>
@endpush
