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

        {{-- === Uploader === --}}
        <div id="media-uploader" class="mb-4">
            <form id="upload-form" action="{{ route('superadmin.media.upload') }}" method="POST" enctype="multipart/form-data">
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
                        <select id="folder-selector" class="form-control form-control-sm" style="width: auto; display: inline-block;">
                            <option value="">📁 Raíz</option>
                        </select>
                        <button id="btn-create-folder-new" class="btn btn-sm btn-outline-primary ms-2" title="Nueva Carpeta">
                            <i class="bi bi-folder-plus"></i> Nueva Carpeta
                        </button>
                        <span class="text-muted small ms-3" id="files-count">0 archivos</span>
                    </div>
                    <div class="toolbar-right">
                        {{-- Botones de acciones dinámicos --}}
                    </div>
                </div>

                {{-- Contenedor para la Rejilla de Medios --}}
                <div id="media-library-grid" class="media-library-grid">
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
    uploadUrl: "{{ route('superadmin.media.upload') }}",
    dataUrl: "{{ route('superadmin.media.data') }}",
    deleteUrlTemplate: "{{ route('superadmin.media.delete', ['id' => ':id']) }}",
    foldersStructureUrl: "{{ route('superadmin.media.folders.structure') }}",
    createFolderUrl: "{{ route('superadmin.media.folders.create') }}",
    renameFolderUrl: "{{ route('superadmin.media.folders.rename', ['id' => ':id']) }}",
    deleteFolderUrl: "{{ route('superadmin.media.folders.delete', ['id' => ':id']) }}"
};

// Verificar si las rutas se generaron correctamente, si no usar fallback
if (window.MediaManagerConfig.deleteFolderUrl.includes('#ruta-no-encontrada')) {
    console.warn('Las rutas nombradas no se pudieron resolver. Usando URLs hardcodeadas como fallback.');
    window.MediaManagerConfig.renameFolderUrl = '/musedock/media/folders/:id/rename';
    window.MediaManagerConfig.deleteFolderUrl = '/musedock/media/folders/:id/delete';
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
