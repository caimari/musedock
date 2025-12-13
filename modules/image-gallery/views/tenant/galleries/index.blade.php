@extends('layouts.app')

@section('title', $title ?? __gallery('gallery.my_galleries'))

@push('styles')
<link rel="stylesheet" href="/modules/image-gallery/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-images me-2"></i>{{ $title ?? __gallery('gallery.my_galleries') }}</h2>
                <p class="text-muted mb-0">{{ __gallery('gallery.manage_galleries') }}</p>
            </div>
            <a href="{{ route('tenant.image-gallery.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> {{ __gallery('gallery.create') }}
            </a>
        </div>

        <!-- Alertas -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>{!! session('error') !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Lista de galerías -->
        @if(!empty($galleries))
            <div class="row g-4">
                @foreach($galleries as $gallery)
                    @php $isOwner = $gallery->tenant_id !== null; @endphp
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="card gallery-card h-100 {{ !$isOwner ? 'border-info' : '' }}">
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
                                    @if(!$isOwner)
                                        <span class="badge bg-info ms-1" title="{{ __gallery('gallery.global') }}">
                                            <i class="bi bi-globe"></i>
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
                            </div>
                            <div class="card-footer bg-transparent border-top-0">
                                <div class="d-flex gap-2">
                                    @if($isOwner)
                                        <a href="{{ route('tenant.image-gallery.edit', ['id' => $gallery->id]) }}"
                                           class="btn btn-sm btn-outline-primary flex-grow-1">
                                            <i class="bi bi-pencil"></i> {{ __gallery('gallery.edit') }}
                                        </a>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="confirmDelete({{ $gallery->id }}, '{{ e($gallery->name) }}')"
                                                title="{{ __gallery('gallery.delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @else
                                        <a href="{{ route('tenant.image-gallery.edit', ['id' => $gallery->id]) }}"
                                           class="btn btn-sm btn-outline-info flex-grow-1">
                                            <i class="bi bi-eye"></i> {{ __gallery('gallery.view') }}
                                        </a>
                                    @endif
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            onclick="copyShortcode('{{ $gallery->id }}', '{{ $gallery->slug }}')"
                                            title="{{ __gallery('gallery.copy_shortcode') }}">
                                        <i class="bi bi-code-slash"></i>
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
                    <a href="{{ route('tenant.image-gallery.create') }}" class="btn btn-primary">
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

<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="copyToast" class="toast" role="alert">
        <div class="toast-header">
            <i class="bi bi-check-circle text-success me-2"></i>
            <strong class="me-auto">{{ __gallery('gallery.copied') }}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="copyToastBody"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
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
            form.action = '{{ route("tenant.image-gallery.index") }}/' + id + '/delete';

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
    navigator.clipboard.writeText(shortcode).then(function() {
        const toast = document.getElementById('copyToast');
        document.getElementById('copyToastBody').textContent = shortcode;
        new bootstrap.Toast(toast).show();
    });
}
</script>
@endpush
