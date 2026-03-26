{{-- modules/MediaManager/Views/admin/index.blade.php --}}
@extends('layouts.app')
@section('title', $title ?? 'Biblioteca de Medios')

@push('styles')
<link rel="stylesheet" href="/assets/modules/MediaManager/css/admin-media.css?v={{ time() }}">
<style>
    /* === FILE MANAGER LAYOUT === */
    .file-manager-container {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }

    /* Panel de Carpetas (Izquierda) */
    .folders-panel {
        width: 250px;
        min-width: 250px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 1rem;
        max-height: 600px;
        overflow-y: auto;
    }

    .folders-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e0e0e0;
    }

    .folders-panel-header h5 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
    }

    /* Árbol de Carpetas */
    .folder-tree {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .folder-tree li {
        margin: 0;
        padding: 0;
    }

    .folder-item {
        padding: 0.4rem 0.6rem;
        cursor: pointer;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: background-color 0.2s;
        font-size: 0.9rem;
    }

    .folder-item:hover {
        background-color: #f8f9fa;
    }

    .folder-item.active {
        background-color: #e7f1ff;
        color: #0d6efd;
        font-weight: 500;
    }

    .folder-item .folder-icon {
        font-size: 1rem;
        color: #ffc107;
    }

    .folder-item .folder-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .folder-item .folder-actions {
        opacity: 0;
        display: flex;
        gap: 0.25rem;
    }

    .folder-item:hover .folder-actions {
        opacity: 1;
    }

    .folder-item .folder-actions button {
        background: none;
        border: none;
        padding: 0.1rem 0.3rem;
        cursor: pointer;
        font-size: 0.8rem;
        color: #6c757d;
    }

    .folder-item .folder-actions button:hover {
        color: #0d6efd;
    }

    .folder-item.folder-root {
        margin-bottom: 0.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e9ecef;
    }

    .folder-item.folder-root .folder-icon {
        color: #6c757d;
    }

    .folder-item.folder-root.active .folder-icon {
        color: #0d6efd;
    }

    .folder-children {
        list-style: none;
        padding-left: 1rem;
        margin-left: 0.5rem;
        border-left: 1px solid #e0e0e0;
    }

    /* Panel de Archivos (Derecha) */
    .files-panel {
        flex: 1;
        min-width: 0;
    }

    /* Toolbar de Acciones */
    .files-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding: 0.75rem 1rem;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
    }

    .files-toolbar .toolbar-left,
    .files-toolbar .toolbar-right {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    /* Breadcrumb de carpetas */
    .folder-breadcrumb {
        display: inline-flex;
        align-items: center;
        gap: 0;
        font-size: 0.85rem;
    }

    .folder-breadcrumb .breadcrumb-segment {
        color: #495057;
        font-weight: 500;
    }

    .folder-breadcrumb .breadcrumb-segment a {
        color: #0d6efd;
        text-decoration: none;
        font-weight: 400;
    }

    .folder-breadcrumb .breadcrumb-segment a:hover {
        text-decoration: underline;
    }

    .folder-breadcrumb .breadcrumb-separator {
        margin: 0 0.4rem;
        color: #adb5bd;
    }

    #btn-folder-back {
        padding: 0.2rem 0.45rem;
        line-height: 1;
        font-size: 0.8rem;
    }

    /* Estilos básicos para la biblioteca */
    .media-library-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 1rem;
        margin-top: 0;
    }

    .media-item {
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 0.5rem;
        text-align: center;
        position: relative;
        background-color: #fff;
        overflow: hidden;
        cursor: pointer;
        transition: box-shadow 0.2s ease;
    }

    .media-item:hover {
        box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
    }

    .media-item.selected {
        border-color: #0d6efd;
        box-shadow: 0 0 8px rgba(0, 123, 255, 0.7);
    }

    .media-item-thumbnail {
        width: 100%;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        margin-bottom: 0.5rem;
    }

    .media-item-thumbnail img {
        max-height: 100%;
        max-width: 100%;
        display: block;
        object-fit: contain;
    }

    .media-item-thumbnail .file-icon {
        font-size: 2.5rem;
        color: #6c757d;
    }

    .media-item-filename {
        font-size: 0.75em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 0.25rem;
        color: #333;
    }

    .media-item-actions {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(255, 255, 255, 0.8);
        border-radius: 3px;
        padding: 2px;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .media-item:hover .media-item-actions {
        opacity: 1;
    }

    .media-item-actions button {
        background: none;
        border: none;
        padding: 1px 3px;
        cursor: pointer;
        font-size: 0.9em;
        color: #dc3545;
    }

    /* === Vista Lista === */
    .media-library-grid[data-view="list"] {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .media-library-grid[data-view="list"] .media-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-align: left;
        border-radius: 0;
        border-bottom: 1px solid #e9ecef;
        border-left: none;
        border-right: none;
        border-top: none;
        padding: 0.4rem 0.6rem;
    }

    .media-library-grid[data-view="list"] .media-item:first-child {
        border-top: 1px solid #e9ecef;
    }

    .media-library-grid[data-view="list"] .media-item-thumbnail {
        width: 40px;
        height: 40px;
        min-width: 40px;
        margin-bottom: 0;
        border-radius: 3px;
    }

    .media-library-grid[data-view="list"] .media-item-thumbnail .file-icon {
        font-size: 1.2rem;
    }

    .media-library-grid[data-view="list"] .media-item-filename {
        flex: 1;
        font-size: 0.85rem;
        margin-top: 0;
    }

    .media-library-grid[data-view="list"] .media-item-actions {
        position: static;
        opacity: 0;
        background: none;
    }

    .media-library-grid[data-view="list"] .media-item:hover .media-item-actions {
        opacity: 1;
    }

    .media-library-grid[data-view="list"] .media-item .media-item-checkbox {
        position: static;
    }

    /* Switch de vista activo */
    .btn-group .btn.active {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    /* Paginación - página activa */
    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    .pagination .page-item .page-link {
        color: #0d6efd;
        border: 1px solid #dee2e6;
        padding: 0.25rem 0.6rem;
    }

    .pagination .page-item.disabled .page-link {
        color: #adb5bd;
    }

    /* Estilos para el uploader */
    #media-uploader {
        border: 2px dashed #ccc;
        padding: 2rem;
        text-align: center;
        margin-bottom: 1.5rem;
        background-color: #f8f9fa;
    }

    #media-uploader.is-dragover {
        border-color: #0d6efd;
        background-color: #e6f2ff;
    }

    #upload-progress-bar {
        display: none;
    }

    /* Storage Quota Indicator */
    .storage-quota-bar {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .storage-quota-bar .quota-icon {
        font-size: 1.5rem;
        color: #6c757d;
    }

    .storage-quota-bar .quota-info {
        flex: 1;
    }

    .storage-quota-bar .quota-label {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .storage-quota-bar .quota-progress {
        height: 8px;
        border-radius: 4px;
        background-color: #e9ecef;
    }

    .storage-quota-bar .quota-text {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .file-manager-container {
            flex-direction: column;
        }
        .folders-panel {
            width: 100%;
            max-height: 300px;
        }
    }
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ $title }}</h2>
        </div>

        @include('partials.alerts-sweetalert2')

        {{-- === Storage Quota Indicator === --}}
        <div id="storage-quota-container" class="storage-quota-bar" style="display: none;">
            <div class="quota-icon">
                <i class="bi bi-hdd-stack"></i>
            </div>
            <div class="quota-info">
                <div class="quota-label">Almacenamiento utilizado</div>
                <div class="progress quota-progress">
                    <div id="quota-progress-bar" class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="quota-text">
                    <span id="quota-used">0 MB</span> / <span id="quota-total">0 MB</span>
                    (<span id="quota-percentage">0</span>%)
                </div>
            </div>
        </div>

        {{-- === Uploader === --}}
        <div id="media-uploader" class="mb-4">
            <form id="upload-form" action="{{ route('tenant.media.upload') }}" method="POST" enctype="multipart/form-data">
                 @csrf
                 <input type="file" name="file" id="file-input" multiple style="display: none;">
                 <input type="hidden" name="folder_id" id="upload-folder-id" value="">
                 <button type="button" id="browse-button" class="btn btn-outline-primary mb-2">
                     <i class="bi bi-upload me-1"></i> Seleccionar Archivos
                 </button>
                 <p class="text-muted small mb-0">o arrastra los archivos aquí</p>
                 <div class="progress mt-3" id="upload-progress-bar" style="height: 5px;">
                     <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                 </div>
                 <div id="upload-status" class="small mt-1 text-muted"></div>
            </form>
        </div>

        {{-- === FILE MANAGER LAYOUT === --}}
        <div class="file-manager-container">

            {{-- Panel de Carpetas (Izquierda) --}}
            <div class="folders-panel">
                <div class="folders-panel-header">
                    <h5><i class="bi bi-folder-fill me-2"></i>Carpetas</h5>
                    <button id="btn-create-folder" class="btn btn-sm btn-outline-primary" title="Nueva Carpeta">
                        <i class="bi bi-folder-plus"></i>
                    </button>
                </div>
                <div id="folder-tree-container">
                    <div class="text-center text-muted py-3">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <div class="small mt-2">Cargando carpetas...</div>
                    </div>
                </div>
            </div>

            {{-- Panel de Archivos (Derecha) --}}
            <div class="files-panel">

                {{-- Toolbar de Acciones --}}
                <div class="files-toolbar">
                    <div class="toolbar-left">
                        <button id="btn-folder-back" class="btn btn-sm btn-outline-secondary me-1" title="Volver" style="display: none;">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <div class="folder-breadcrumb" id="folder-breadcrumb">
                            <span class="breadcrumb-segment active"><i class="bi bi-folder-fill text-warning me-1"></i>Raíz</span>
                        </div>
                        <button id="btn-select-all" class="btn btn-sm btn-outline-secondary ms-2" title="Seleccionar todo" style="display: none;">
                            <i class="bi bi-check2-square"></i>
                        </button>
                        <span class="text-muted small ms-2" id="files-count">0 archivos</span>
                    </div>
                    <div class="toolbar-right">
                        <div class="btn-group btn-group-sm me-2" role="group" aria-label="Vista">
                            <button id="btn-view-grid" class="btn btn-outline-secondary active" title="Vista cuadrícula">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </button>
                            <button id="btn-view-list" class="btn btn-outline-secondary" title="Vista lista">
                                <i class="bi bi-list-ul"></i>
                            </button>
                        </div>
                        <button id="btn-create-folder-new" class="btn btn-sm btn-outline-primary" title="Nueva Carpeta">
                            <i class="bi bi-folder-plus"></i> Nueva Carpeta
                        </button>
                    </div>
                </div>

                {{-- Contenedor para la Rejilla de Medios --}}
                <div id="media-library-grid" class="media-library-grid" data-view="grid">
                    <div class="text-center p-5 text-muted" id="media-loading">
                         <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Cargando...</span></div>
                         Cargando medios...
                    </div>
                </div>

                 {{-- Paginación --}}
                 <nav aria-label="Media navigation" class="mt-4 d-flex justify-content-center">
                    <ul class="pagination pagination-sm" id="media-pagination"></ul>
                </nav>

            </div>

        </div>

    </div>
</div>

{{-- Menú Contextual para Archivos --}}
<div id="media-context-menu" class="context-menu" style="display: none;">
    <div class="context-menu-content">
        <a href="#" class="context-menu-item" data-action="rename"><i class="bi bi-pencil"></i> Renombrar</a>
        <a href="#" class="context-menu-item" data-action="copy"><i class="bi bi-files"></i> Copiar</a>
        <a href="#" class="context-menu-item" data-action="cut"><i class="bi bi-scissors"></i> Cortar</a>
        <a href="#" class="context-menu-item" data-action="paste" style="display: none;"><i class="bi bi-clipboard"></i> Pegar</a>
        <hr class="context-menu-divider" style="display: none;" data-divider="paste">
        <a href="#" class="context-menu-item" data-action="delete"><i class="bi bi-trash text-danger"></i> Eliminar</a>
    </div>
</div>

<script>
// Función helper para obtener CSRF token dinámicamente
function getCsrfToken() {
    const token = document.querySelector('input[name="_token"]');
    if (!token) {
        console.warn('No CSRF token found in page');
        return '';
    }
    return token.value;
}

// Función helper para construir URLs de rutas si route() falla
function buildFolderUrl(pattern, id) {
    if (pattern && !pattern.includes('#ruta-no-encontrada')) {
        return pattern.replace(':id', id);
    }
    // Fallback: construir URL manualmente
    return pattern.replace(':id', id);
}

// Función helper para hacer fetch con CSRF token automático
function fetchWithCsrf(url, options = {}) {
    const csrfToken = getCsrfToken();
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken,
        ...options.headers
    };

    return fetch(url, {
        ...options,
        headers
    });
}

window.MediaManagerConfig = {
    uploadUrl: "{{ route('tenant.media.upload') }}",
    dataUrl: "{{ route('tenant.media.data') }}",
    deleteUrlTemplate: "{{ route('tenant.media.delete', ['id' => ':id']) }}",
    detailsUrlTemplate: "{{ route('tenant.media.details', ['id' => ':id']) }}",
    updateUrlTemplate: "{{ route('tenant.media.update', ['id' => ':id']) }}",
    renameUrlTemplate: "{{ route('tenant.media.rename', ['id' => ':id']) }}",
    foldersStructureUrl: "{{ route('tenant.media.folders.structure') }}",
    createFolderUrl: "{{ route('tenant.media.folders.create') }}",
    renameFolderUrl: "{{ route('tenant.media.folders.rename', ['id' => ':id']) }}",
    deleteFolderUrl: "{{ route('tenant.media.folders.delete', ['id' => ':id']) }}",
    emptyFolderUrl: "{{ route('tenant.media.folders.empty', ['id' => ':id']) }}",
    moveUrl: "{{ route('tenant.media.move') }}",
    copyUrl: "{{ route('tenant.media.copy') }}",
    quotaUrl: "{{ route('tenant.media.quota') }}"
};

// Function to format bytes to human readable format
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Function to load and display storage quota
function loadStorageQuota() {
    if (!window.MediaManagerConfig.quotaUrl) return;

    fetch(window.MediaManagerConfig.quotaUrl, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.quota) {
            const quota = data.quota;

            // Don't show quota bar if unlimited
            if (quota.unlimited) {
                document.getElementById('storage-quota-container').style.display = 'none';
                return;
            }

            const quotaBytes = quota.quota_mb * 1024 * 1024;
            const usedBytes = quota.used_bytes || 0;
            const percentage = quota.percentage || 0;

            // Update UI elements
            document.getElementById('quota-used').textContent = formatBytes(usedBytes);
            document.getElementById('quota-total').textContent = quota.quota_mb >= 1024
                ? (quota.quota_mb / 1024).toFixed(1) + ' GB'
                : quota.quota_mb + ' MB';
            document.getElementById('quota-percentage').textContent = percentage.toFixed(1);

            // Update progress bar
            const progressBar = document.getElementById('quota-progress-bar');
            progressBar.style.width = Math.min(percentage, 100) + '%';
            progressBar.setAttribute('aria-valuenow', percentage);

            // Change color based on usage
            progressBar.classList.remove('bg-success', 'bg-warning', 'bg-danger');
            if (percentage > 90) {
                progressBar.classList.add('bg-danger');
            } else if (percentage > 70) {
                progressBar.classList.add('bg-warning');
            } else {
                progressBar.classList.add('bg-success');
            }

            // Show the quota container
            document.getElementById('storage-quota-container').style.display = 'flex';
        }
    })
    .catch(error => {
        console.warn('Could not load storage quota:', error);
    });
}

// Load quota on page load
document.addEventListener('DOMContentLoaded', function() {
    loadStorageQuota();
});

// Verificar si las rutas se generaron correctamente, si no usar fallback
if (window.MediaManagerConfig.deleteFolderUrl.includes('#ruta-no-encontrada')) {
    console.warn('Las rutas nombradas no se pudieron resolver. Usando URLs hardcodeadas como fallback.');
    window.MediaManagerConfig.renameFolderUrl = '/admin/media/folders/:id/rename';
    window.MediaManagerConfig.deleteFolderUrl = '/admin/media/folders/:id/delete';
    window.MediaManagerConfig.emptyFolderUrl = '/admin/media/folders/:id/empty';
}
</script>
<script src="/assets/modules/MediaManager/js/folder-manager.js?v={{ time() }}"></script>
<script src="/assets/modules/MediaManager/js/folder-media-integration.js?v={{ time() }}"></script>
<script src="/assets/modules/MediaManager/js/admin-media.js?v={{ time() }}"></script>
@include('MediaManager::admin._meta_modal')

@endsection

@push('scripts')
{{-- JS Media Manager --}}
@endpush
