@extends('layouts.app')

@section('title', $title ?? __gallery('gallery.edit'))

@push('styles')
<link rel="stylesheet" href="/modules/image-gallery/css/admin.css">
<style>
.dropzone { border: 2px dashed var(--bs-primary); border-radius: 8px; padding: 40px; text-align: center; transition: all 0.3s; cursor: pointer; }
.dropzone:hover, .dropzone.dragover { background: rgba(var(--bs-primary-rgb), 0.1); }
.image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; }
.image-item { position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: #f8f9fa; cursor: move; }
.image-item img { width: 100%; height: 100%; object-fit: cover; }
.image-item .image-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.3s; display: flex; align-items: center; justify-content: center; gap: 5px; }
.image-item:hover .image-overlay { opacity: 1; }
.image-item .image-overlay .btn { width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; }
.sortable-ghost { opacity: 0.4; }
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('tenant.image-gallery.index') }}">{{ __gallery('gallery.my_galleries') }}</a></li>
                        <li class="breadcrumb-item active">{{ $gallery->name }}</li>
                    </ol>
                </nav>
                <h2 class="mb-0">
                    <i class="bi bi-{{ $canEdit ? 'pencil' : 'eye' }} me-2"></i>{{ $gallery->name }}
                    @if(!$isOwner)
                        <span class="badge bg-info ms-2">{{ __gallery('gallery.global') }}</span>
                    @endif
                </h2>
            </div>
            <button type="button" class="btn btn-outline-secondary" onclick="copyShortcode()">
                <i class="bi bi-code-slash me-1"></i> {{ __gallery('gallery.copy_shortcode') }}
            </button>
        </div>

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

        @if(!$isOwner)
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>{{ __gallery('gallery.global_readonly') }}
            </div>
        @endif

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#images" type="button">
                    <i class="bi bi-images me-1"></i> {{ __gallery('gallery.images') }}
                    <span class="badge bg-primary ms-1" id="imageCount">{{ count($images) }}</span>
                </button>
            </li>
            @if($canEdit)
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#settingsTab" type="button">
                    <i class="bi bi-gear me-1"></i> {{ __gallery('gallery.settings') }}
                </button>
            </li>
            @endif
        </ul>

        <div class="tab-content">
            <!-- Tab: Imágenes -->
            <div class="tab-pane fade show active" id="images" role="tabpanel">
                @if($canEdit)
                <!-- Zona de subida -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="dropzone" id="dropzone">
                            <input type="file" id="fileInput" multiple accept="image/*" class="d-none">
                            <i class="bi bi-cloud-upload display-4 text-primary mb-3"></i>
                            <h5>{{ __gallery('image.drop_files') }}</h5>
                            <p class="text-muted mb-0">{{ __gallery('image.or_click') }}</p>
                        </div>
                        <div id="uploadProgress" class="mt-3 d-none">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Grid de imágenes -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __gallery('image.gallery_images') }}</h5>
                        @if($canEdit)
                        <small class="text-muted">{{ __gallery('image.drag_to_reorder') }}</small>
                        @endif
                    </div>
                    <div class="card-body">
                        @if(!empty($images))
                            <div class="image-grid" id="imageGrid">
                                @foreach($images as $image)
                                    <div class="image-item" data-id="{{ $image->id }}">
                                        <img src="{{ $image->thumbnail_url ?: $image->image_url }}" alt="{{ e($image->alt_text) }}" loading="lazy">
                                        @if($canEdit)
                                        <div class="image-overlay">
                                            <button type="button" class="btn btn-light btn-sm" onclick="editImage({{ $image->id }})" title="{{ __gallery('image.edit') }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-light btn-sm btn-thumbnail" onclick="setThumbnail({{ $image->id }})" title="{{ __gallery('image.set_thumbnail') }}">
                                                <i class="bi {{ $gallery->thumbnail_url && ($image->thumbnail_url ?: $image->image_url) === $gallery->thumbnail_url ? 'bi-star-fill text-warning' : 'bi-star' }}"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteImage({{ $image->id }})" title="{{ __gallery('image.delete') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-5" id="noImagesMessage">
                                <i class="bi bi-image display-1 text-muted"></i>
                                <h5 class="mt-3">{{ __gallery('image.no_images') }}</h5>
                                @if($canEdit)
                                <p class="text-muted">{{ __gallery('image.upload_first') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($canEdit)
            <!-- Tab: Configuración -->
            <div class="tab-pane fade" id="settingsTab" role="tabpanel">
                <form action="{{ route('tenant.image-gallery.update', ['id' => $gallery->id]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="_method" value="PUT">

                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header"><h5 class="mb-0">{{ __gallery('gallery.basic_info') }}</h5></div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">{{ __gallery('gallery.name') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $gallery->name) }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="slug" class="form-label">{{ __gallery('gallery.slug') }}</label>
                                        <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $gallery->slug) }}" pattern="[a-z0-9\-]+">
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">{{ __gallery('gallery.description') }}</label>
                                        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $gallery->description) }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div class="card-header"><h5 class="mb-0">{{ __gallery('gallery.layout_settings') }}</h5></div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        @foreach($layouts as $key => $layout)
                                            <div class="col-6 col-md-4">
                                                <input type="radio" class="btn-check" name="layout_type" id="layout_{{ $key }}" value="{{ $key }}" {{ old('layout_type', $gallery->layout_type) === $key ? 'checked' : '' }}>
                                                <label class="btn btn-outline-primary w-100 py-3" for="layout_{{ $key }}">
                                                    <i class="bi {{ $layout['icon'] }} d-block mb-2 fs-4"></i>
                                                    <span class="d-block fw-medium">{{ $layout['name'] }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="row g-3 mt-3">
                                        <div class="col-md-6">
                                            <label class="form-label">{{ __gallery('gallery.columns') }}</label>
                                            <select class="form-select" name="columns">
                                                @for($i = 1; $i <= 6; $i++)
                                                    <option value="{{ $i }}" {{ old('columns', $gallery->columns) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">{{ __gallery('gallery.gap') }}</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="gap" value="{{ old('gap', $gallery->gap) }}" min="0" max="50">
                                                <span class="input-group-text">px</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header"><h5 class="mb-0">{{ __gallery('gallery.status') }}</h5></div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ $gallery->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">{{ __gallery('gallery.is_active') }}</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1" {{ $gallery->featured ? 'checked' : '' }}>
                                        <label class="form-check-label" for="featured">{{ __gallery('gallery.featured') }}</label>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div class="card-header"><h5 class="mb-0">{{ __gallery('gallery.shortcode') }}</h5></div>
                                <div class="card-body">
                                    <div class="bg-light p-3 rounded mb-2">
                                        <code id="shortcodeId">[gallery id={{ $gallery->id }}]</code>
                                    </div>
                                    <div class="bg-light p-3 rounded">
                                        <code>[gallery slug="{{ $gallery->slug }}"]</code>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-check-lg me-1"></i> {{ __gallery('gallery.save') }}
                                        </button>
                                        <a href="{{ route('tenant.image-gallery.index') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-left me-1"></i> {{ __gallery('gallery.back') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- SweetAlert2 modal implementation (no HTML needed) -->

<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toast" class="toast" role="alert">
        <div class="toast-header">
            <i class="bi bi-check-circle text-success me-2" id="toastIcon"></i>
            <strong class="me-auto" id="toastTitle"></strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const galleryId = {{ $gallery->id }};
const canEdit = {{ $canEdit ? 'true' : 'false' }};
const csrfToken = '{{ csrf_token() }}';
let imagesData = @json(array_map(fn($img) => $img->toArray(), $images));

@if($canEdit)
// Upload handlers
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');

dropzone.addEventListener('click', () => fileInput.click());
dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
dropzone.addEventListener('drop', (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); handleFiles(e.dataTransfer.files); });
fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

function handleFiles(files) {
    if (!files.length) return;

    const formData = new FormData();
    for (let file of files) formData.append('images[]', file);

    const progressDiv = document.getElementById('uploadProgress');
    const progressBar = progressDiv.querySelector('.progress-bar');
    progressDiv.classList.remove('d-none');
    progressBar.style.width = '0%';

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '{{ route("tenant.image-gallery.images.upload", ["gallery_id" => $gallery->id]) }}');
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);

    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = pct + '%';
            progressBar.textContent = pct + '%';
        }
    });

    xhr.addEventListener('load', () => {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                response.images.forEach(img => {
                    imagesData.push(img);
                    addImageToGrid(img);
                });
                document.getElementById('imageCount').textContent = imagesData.length;
                const noImagesMsg = document.getElementById('noImagesMessage');
                if (noImagesMsg) noImagesMsg.remove();
                showToast('success', '{{ __gallery("image.uploaded") }}', response.uploaded + ' {{ __gallery("image.images_uploaded") }}');
            } else {
                showToast('error', '{{ __gallery("image.upload_error") }}', response.error);
            }
        }
        progressDiv.classList.add('d-none');
        fileInput.value = '';
    });

    xhr.send(formData);
}

function addImageToGrid(img) {
    let grid = document.getElementById('imageGrid');
    if (!grid) {
        document.querySelector('#images .card-body').innerHTML = '<div class="image-grid" id="imageGrid"></div>';
        grid = document.getElementById('imageGrid');
    }

    const item = document.createElement('div');
    item.className = 'image-item';
    item.dataset.id = img.id;
    item.innerHTML = `
        <img src="${img.thumbnail_url || img.image_url}" alt="${escapeHtml(img.alt_text)}" loading="lazy">
        <div class="image-overlay">
            <button type="button" class="btn btn-light btn-sm" onclick="editImage(${img.id})"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-light btn-sm btn-thumbnail" onclick="setThumbnail(${img.id})"><i class="bi bi-star"></i></button>
            <button type="button" class="btn btn-danger btn-sm" onclick="deleteImage(${img.id})"><i class="bi bi-trash"></i></button>
        </div>
    `;
    grid.appendChild(item);
}

// Sortable
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('imageGrid');
    if (grid && canEdit) {
        new Sortable(grid, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const order = Array.from(grid.children).map(item => parseInt(item.dataset.id));
                fetch('{{ route("tenant.image-gallery.images.reorder", ["gallery_id" => $gallery->id]) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ order: order })
                });
            }
        });
    }
});

function editImage(id) {
    const img = imagesData.find(i => i.id === id);
    if (!img) return;

    Swal.fire({
        title: '{{ __gallery("image.edit") }}',
        html: `
            <div class="text-center mb-3">
                <img src="${img.medium_url || img.image_url}" alt="" class="img-fluid rounded" style="max-height: 200px;">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label">{{ __gallery("image.title") }}</label>
                <input type="text" id="swal-title" class="swal2-input" value="${img.title || ''}" style="width: 100%; margin: 0;">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label">{{ __gallery("image.alt_text") }}</label>
                <input type="text" id="swal-alt" class="swal2-input" value="${img.alt_text || ''}" style="width: 100%; margin: 0;">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label">{{ __gallery("image.caption") }}</label>
                <textarea id="swal-caption" class="swal2-textarea" rows="2" style="width: 100%; margin: 0;">${img.caption || ''}</textarea>
            </div>
            <div class="mb-3 text-start">
                <label class="form-label">{{ __gallery("image.link_url") }}</label>
                <input type="url" id="swal-link-url" class="swal2-input" value="${img.link_url || ''}" placeholder="https://" style="width: 100%; margin: 0;">
            </div>
            <div class="form-check form-switch text-start">
                <input class="form-check-input" type="checkbox" id="swal-active" ${img.is_active ? 'checked' : ''}>
                <label class="form-check-label" for="swal-active">{{ __gallery("image.is_active") }}</label>
            </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: '{{ __gallery("gallery.save") }}',
        cancelButtonText: '{{ __gallery("gallery.cancel") }}',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        focusConfirm: false,
        preConfirm: () => {
            const title = document.getElementById('swal-title').value;
            const alt_text = document.getElementById('swal-alt').value;
            const caption = document.getElementById('swal-caption').value;
            const link_url = document.getElementById('swal-link-url').value;
            const is_active = document.getElementById('swal-active').checked;

            return {
                id: id,
                title: title,
                alt_text: alt_text,
                caption: caption,
                link_url: link_url,
                is_active: is_active
            };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            saveImageEdit(result.value);
        }
    });
}

function saveImageEdit(data) {
    const id = data.id;
    const payload = {
        title: data.title,
        alt_text: data.alt_text,
        caption: data.caption,
        link_url: data.link_url,
        is_active: data.is_active
    };

    Swal.fire({
        title: '{{ __gallery("gallery.saving") }}',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('/{{ admin_path() }}/image-gallery/images/' + id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(response => {
        if (response.success) {
            const idx = imagesData.findIndex(i => i.id == id);
            if (idx !== -1) imagesData[idx] = response.image;

            Swal.fire({
                icon: 'success',
                title: '{{ __gallery("gallery.success") }}',
                text: '{{ __gallery("image.updated") }}',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '{{ __gallery("gallery.error") }}',
                text: response.error || 'Error',
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#0d6efd'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: '{{ __gallery("gallery.error") }}',
            text: error.message || 'Error',
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#0d6efd'
        });
    });
}

function setThumbnail(id) {
    fetch('/{{ admin_path() }}/image-gallery/images/' + id + '/thumbnail', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
    })
    .then(r => r.json())
    .then(response => {
        if (response.success) {
            // Remover clase de estrella activa de todas las imágenes
            document.querySelectorAll('.image-item .btn-thumbnail').forEach(btn => {
                const icon = btn.querySelector('i');
                icon.classList.remove('bi-star-fill', 'text-warning');
                icon.classList.add('bi-star');
            });

            // Marcar la estrella de la imagen seleccionada
            const selectedBtn = document.querySelector(`.image-item[data-id="${id}"] .btn-thumbnail`);
            if (selectedBtn) {
                const icon = selectedBtn.querySelector('i');
                icon.classList.remove('bi-star');
                icon.classList.add('bi-star-fill', 'text-warning');
            }

            Swal.fire({
                icon: 'success',
                title: '{{ __gallery("gallery.success") }}',
                text: '{{ __gallery("image.thumbnail_set") }}',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '{{ __gallery("gallery.error") }}',
                text: response.error || 'Error',
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#0d6efd'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: '{{ __gallery("gallery.error") }}',
            text: error.message || 'Error',
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#0d6efd'
        });
    });
}

function deleteImage(id) {
    Swal.fire({
        title: '{!! __gallery("image.delete") !!}',
        text: '{!! __gallery("image.confirm_delete") !!}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '{{ __gallery("gallery.deleting") }}',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('/{{ admin_path() }}/image-gallery/images/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(response => {
                if (response.success) {
                    const item = document.querySelector(`.image-item[data-id="${id}"]`);
                    if (item) item.remove();
                    imagesData = imagesData.filter(i => i.id != id);
                    document.getElementById('imageCount').textContent = imagesData.length;

                    Swal.fire({
                        icon: 'success',
                        title: '{{ __gallery("gallery.success") }}',
                        text: '{{ __gallery("image.deleted") }}',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });

                    if (imagesData.length === 0) {
                        document.querySelector('#images .card-body').innerHTML = `
                            <div class="text-center py-5" id="noImagesMessage">
                                <i class="bi bi-image display-1 text-muted"></i>
                                <h5 class="mt-3">{{ __gallery('image.no_images') }}</h5>
                                <p class="text-muted">{{ __gallery('image.upload_first') }}</p>
                            </div>
                        `;
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __gallery("gallery.error") }}',
                        text: response.error || 'Error',
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#0d6efd'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: '{{ __gallery("gallery.error") }}',
                    text: error.message || 'Error',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#0d6efd'
                });
            });
        }
    });
}
@endif

function copyShortcode() {
    navigator.clipboard.writeText(document.getElementById('shortcodeId').textContent).then(() => {
        showToast('success', '{{ __gallery("gallery.copied") }}', document.getElementById('shortcodeId').textContent);
    });
}

function showToast(type, title, message) {
    const icon = document.getElementById('toastIcon');
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastBody').innerHTML = message;
    icon.className = 'bi me-2 ' + (type === 'success' ? 'bi-check-circle text-success' : 'bi-exclamation-circle text-danger');
    new bootstrap.Toast(document.getElementById('toast')).show();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}
</script>
@endpush
