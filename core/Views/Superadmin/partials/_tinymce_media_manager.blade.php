{{-- Media Manager para TinyMCE --}}
{{-- CONFIGURACIÓN GLOBAL PARA MEDIA MANAGER --}}
<script>
// Configuración global del gestor de medios para TinyMCE
if (!window.MediaManagerConfig) {
    window.MediaManagerConfig = {
        uploadUrl: '{{ route("tenant.media.upload") }}',
        dataUrl: '{{ route("tenant.media.data") }}',
        deleteUrlTemplate: '{{ route("tenant.media.delete", ["id" => ":id"]) }}',
        detailsUrlTemplate: '{{ route("tenant.media.details", ["id" => ":id"]) }}',
        updateUrlTemplate: '{{ route("tenant.media.update", ["id" => ":id"]) }}',
        foldersStructureUrl: '{{ route("tenant.media.folders.structure") }}',
        csrfToken: '{{ csrf_token() }}',
        currentDisk: 'media',
        availableDisks: {
            'media': { name: 'Local (Seguro)', icon: 'bi-hdd' },
            'local': { name: 'Local (Legacy)', icon: 'bi-folder' }
        }
    };
    // Fallback si las rutas no se resuelven
    if (window.MediaManagerConfig.foldersStructureUrl.includes('#ruta-no-encontrada')) {
        window.MediaManagerConfig.foldersStructureUrl = '/admin/media/folders/structure';
    }
    if (window.MediaManagerConfig.uploadUrl.includes('#ruta-no-encontrada')) {
        window.MediaManagerConfig.uploadUrl = '/admin/media/upload';
    }
    if (window.MediaManagerConfig.dataUrl.includes('#ruta-no-encontrada')) {
        window.MediaManagerConfig.dataUrl = '/admin/media/data';
    }
}
</script>

{{-- Verificar si el modal ya existe (para evitar duplicados) --}}
<script>
if (!document.getElementById('md-media-manager-tinymce')) {
    // Crear el modal del Media Manager para TinyMCE
    const modalHTML = `
    <div id="md-media-manager-tinymce" class="md-modal-overlay">
        <div class="md-modal">
            <div class="md-modal-header">
                <h3>Seleccionar Imagen</h3>
                <div class="md-modal-tabs">
                    <button type="button" class="md-tab active" data-tab="library">Biblioteca</button>
                    <button type="button" class="md-tab" data-tab="upload">Subir</button>
                </div>
                <div class="md-disk-selector">
                    <select id="md-disk-select-tinymce" class="md-disk-dropdown" title="Seleccionar almacenamiento">
                        <option value="media" selected>Local (Seguro)</option>
                        <option value="local">Local (Legacy)</option>
                    </select>
                </div>
                <button type="button" class="md-close">&times;</button>
            </div>

            <div class="md-modal-content">
                <!-- Pestaña de Biblioteca -->
                <div class="md-tab-content active" id="library-tab-tinymce">
                    <div class="md-library-layout">
                        <!-- Panel de Carpetas -->
                        <div class="md-folders-panel" id="md-folders-panel-tinymce">
                            <div class="md-folders-header">
                                <span>Carpetas</span>
                            </div>
                            <div class="md-folders-tree" id="md-folders-tree-tinymce">
                                <div class="md-folders-loading">Cargando...</div>
                            </div>
                        </div>

                        <!-- Panel de Archivos -->
                        <div class="md-files-panel">
                            <div class="md-search-bar">
                                <div class="md-current-folder" id="md-current-folder-tinymce">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                                    <span>Raíz</span>
                                </div>
                                <div class="md-search-container">
                                    <input type="text" id="md-search-input-tinymce" placeholder="Buscar...">
                                    <button type="button" id="md-search-button-tinymce">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    </button>
                                </div>
                                <!-- Filtro de tipo eliminado - mostramos todos los archivos -->
                            </div>

                            <div class="md-grid-container">
                                <div class="md-loader">
                                    <div class="md-spinner"></div>
                                    <p>Cargando...</p>
                                </div>
                                <div class="md-grid" id="md-media-grid-tinymce"></div>
                            </div>

                            <div class="md-pagination" id="md-pagination-tinymce"></div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña de Subida -->
                <div class="md-tab-content" id="upload-tab-tinymce">
                    <div class="md-upload-folder-indicator" id="md-upload-folder-indicator-tinymce">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#ffc107"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        <span>Subir a: <strong id="md-upload-folder-name-tinymce">Raíz</strong></span>
                    </div>
                    <div class="md-dropzone" id="md-dropzone-tinymce">
                        <div class="md-dropzone-content">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            <h3>Arrastra imágenes aquí</h3>
                            <p>o</p>
                            <button type="button" class="md-button" id="md-browse-button-tinymce">Seleccionar archivos</button>
                            <input type="file" id="md-file-input-tinymce" multiple accept="image/*" style="display: none;">
                            <p class="md-small">JPG, PNG, GIF, WebP. Máx 10MB.</p>
                        </div>
                    </div>
                    <div class="md-upload-progress-tinymce" style="display: none;">
                        <div class="md-progress-container">
                            <div class="md-progress-bar"></div>
                        </div>
                        <p class="md-progress-status">Subiendo...</p>
                    </div>
                </div>
            </div>

            <div class="md-modal-footer">
                <div class="md-file-details" style="display: none;">
                    <div class="md-preview">
                        <img id="md-selected-preview-tinymce" src="" alt="">
                    </div>
                    <div class="md-details">
                        <p class="md-filename" id="md-selected-filename-tinymce"></p>
                        <p class="md-dimensions" id="md-selected-dimensions-tinymce"></p>
                    </div>
                </div>
                <div class="md-actions">
                    <button type="button" class="md-button md-secondary md-cancel">Cancelar</button>
                    <button type="button" class="md-button md-primary md-select" disabled>Insertar imagen</button>
                </div>
            </div>
        </div>
    </div>
    `;

    // Añadir al DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}
</script>

{{-- Estilos del Media Manager (solo si no están ya incluidos) --}}
<style>
/* Solo incluir estilos si no existen */
.md-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

/* Asegurar que SweetAlert aparezca sobre el modal de TinyMCE Media Manager */
.swal2-tinymce-top-layer {
    z-index: 999999 !important;
}
.swal2-container {
    z-index: 999999 !important;
}
body .swal2-container.swal2-tinymce-top-layer {
    z-index: 999999 !important;
}
.md-modal-overlay.active { display: flex; animation: mdFadeIn 0.3s ease; }
.md-modal {
    background: white;
    border-radius: 8px;
    width: 100%;
    max-width: 900px;
    height: 80vh;
    max-height: 600px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.md-modal-header {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
}
.md-modal-header h3 { margin: 0; font-size: 18px; font-weight: 600; }
.md-modal-tabs { display: flex; margin-left: 20px; flex: 1; }
.md-tab {
    background: none;
    border: none;
    padding: 10px 15px;
    font-size: 14px;
    color: #666;
    cursor: pointer;
    border-bottom: 2px solid transparent;
}
.md-tab:hover { color: #2271b1; }
.md-tab.active { color: #2271b1; border-bottom-color: #2271b1; }
.md-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #666;
    cursor: pointer;
    line-height: 1;
}
.md-close:hover { color: #d63638; }
.md-disk-selector { margin-left: auto; margin-right: 15px; }
.md-disk-dropdown {
    padding: 6px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
}
.md-modal-content { flex: 1; overflow: hidden; }
.md-tab-content { display: none; height: 100%; }
.md-tab-content.active { display: flex; flex-direction: column; }
.md-library-layout { display: flex; height: 100%; }
.md-folders-panel {
    width: 180px;
    border-right: 1px solid #e0e0e0;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
}
.md-folders-header {
    padding: 12px 15px;
    font-weight: 600;
    font-size: 13px;
    border-bottom: 1px solid #e0e0e0;
    background: white;
}
.md-folders-tree { flex: 1; overflow-y: auto; padding: 10px; }
.md-folder-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 13px;
}
.md-folder-item:hover { background: #e9ecef; }
.md-folder-item.active { background: #2271b1; color: white; }
.md-folder-item svg { width: 16px; height: 16px; fill: #ffc107; }
.md-folder-item.active svg { fill: white; }
.md-files-panel { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.md-search-bar {
    padding: 12px 15px;
    display: flex;
    gap: 10px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}
.md-current-folder {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: white;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
}
.md-current-folder svg { fill: #ffc107; }
.md-search-container { flex: 1; display: flex; position: relative; }
.md-search-container input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
}
.md-search-container button {
    position: absolute;
    right: 0;
    top: 0;
    height: 100%;
    width: 36px;
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
}
.md-filter select {
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
}
.md-grid-container { flex: 1; overflow: auto; padding: 15px; position: relative; }
.md-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
}
.md-item {
    cursor: pointer;
    border-radius: 6px;
    overflow: hidden;
    border: 2px solid transparent;
    transition: all 0.2s;
}
.md-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.md-item.selected { border-color: #2271b1; box-shadow: 0 0 0 2px rgba(34,113,177,0.3); }
.md-item-thumbnail {
    height: 100px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.md-item-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
.md-item-filename {
    padding: 6px 8px;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: white;
}
.md-loader {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.9);
}
.md-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid rgba(34,113,177,0.2);
    border-top-color: #2271b1;
    border-radius: 50%;
    animation: mdSpin 0.8s linear infinite;
}
.md-pagination { display: flex; justify-content: center; padding: 10px; border-top: 1px solid #e0e0e0; }
.md-pagination ul { display: flex; list-style: none; padding: 0; margin: 0; gap: 4px; }
.md-pagination button {
    min-width: 28px;
    height: 28px;
    border: 1px solid #ccc;
    background: white;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
}
.md-pagination button.active { background: #2271b1; color: white; border-color: #2271b1; }
.md-pagination button.disabled { opacity: 0.5; cursor: not-allowed; }
.md-dropzone { flex: 1; padding: 20px; display: flex; align-items: center; justify-content: center; }
.md-dropzone-content {
    width: 100%;
    max-width: 400px;
    padding: 40px 20px;
    border: 2px dashed #ccc;
    border-radius: 8px;
    text-align: center;
}
.md-dropzone-content svg { color: #999; margin-bottom: 15px; }
.md-dropzone-content h3 { margin: 0 0 10px; font-size: 18px; color: #333; }
.md-dropzone-content p { margin: 5px 0; color: #666; }
.md-dropzone.dragover .md-dropzone-content { border-color: #2271b1; background: rgba(34,113,177,0.05); }
.md-small { font-size: 12px; color: #999; margin-top: 15px !important; }
.md-upload-folder-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
    font-size: 13px;
}
.md-upload-folder-indicator strong { color: #2271b1; }
.md-upload-progress-tinymce { padding: 20px; }
.md-progress-container { height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden; }
.md-progress-bar { height: 100%; background: #2271b1; width: 0; transition: width 0.3s; }
.md-progress-status { text-align: center; color: #666; font-size: 13px; margin-top: 10px; }
.md-modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 20px;
    border-top: 1px solid #e0e0e0;
    background: #f8f9fa;
}
.md-file-details { display: flex; align-items: center; gap: 12px; }
.md-preview { width: 50px; height: 50px; border-radius: 4px; overflow: hidden; background: #f0f0f0; }
.md-preview img { width: 100%; height: 100%; object-fit: cover; }
.md-filename { font-weight: 500; font-size: 14px; margin: 0; }
.md-dimensions { font-size: 12px; color: #666; margin: 0; }
.md-actions { display: flex; gap: 10px; }
.md-button {
    padding: 10px 20px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid #ccc;
    background: #f0f0f0;
    color: #333;
}
.md-button:hover { background: #e0e0e0; }
.md-button.md-primary { background: #2271b1; color: white; border-color: #2271b1; }
.md-button.md-primary:hover { background: #135e96; }
.md-button.md-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.md-folders-loading { text-align: center; color: #666; font-size: 13px; padding: 20px; }
.md-empty, .md-error { text-align: center; color: #666; padding: 40px; font-size: 14px; }
@keyframes mdFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes mdSpin { to { transform: rotate(360deg); } }
</style>

{{-- Script del Media Manager para TinyMCE --}}
<script>
(function() {
    // Variables de estado
    let tinymceCallback = null;
    let tinymceMeta = null;
    let currentPage = 1;
    let searchTerm = '';
    let filterType = 'all'; // Mostrar todos los tipos por defecto para evitar confusión
    let currentFolderId = null;
    let currentFolderName = 'Raíz';
    let currentDisk = 'media';
    let selectedMediaData = null;

    // Esperar a que el DOM esté listo
    function initMediaManager() {
        const modal = document.getElementById('md-media-manager-tinymce');
        if (!modal) return;

        const closeBtn = modal.querySelector('.md-close');
        const cancelBtn = modal.querySelector('.md-cancel');
        const selectBtn = modal.querySelector('.md-select');
        const tabs = modal.querySelectorAll('.md-tab');
        const tabContents = modal.querySelectorAll('.md-tab-content');
        const mediaGrid = document.getElementById('md-media-grid-tinymce');
        const loader = modal.querySelector('.md-loader');
        const searchInput = document.getElementById('md-search-input-tinymce');
        const searchButton = document.getElementById('md-search-button-tinymce');
        const diskSelector = document.getElementById('md-disk-select-tinymce');
        const foldersTree = document.getElementById('md-folders-tree-tinymce');
        const currentFolderDisplay = document.getElementById('md-current-folder-tinymce');
        const paginationContainer = document.getElementById('md-pagination-tinymce');
        const fileDetailsContainer = modal.querySelector('.md-file-details');
        const selectedPreview = document.getElementById('md-selected-preview-tinymce');
        const selectedFilename = document.getElementById('md-selected-filename-tinymce');
        const selectedDimensions = document.getElementById('md-selected-dimensions-tinymce');
        const dropzone = document.getElementById('md-dropzone-tinymce');
        const browseButton = document.getElementById('md-browse-button-tinymce');
        const fileInput = document.getElementById('md-file-input-tinymce');
        const progressContainer = modal.querySelector('.md-upload-progress-tinymce');
        const progressBar = modal.querySelector('.md-progress-bar');
        const progressStatus = modal.querySelector('.md-progress-status');

        // Función para abrir el modal
        function openModal(callback, value, meta) {
            tinymceCallback = callback;
            tinymceMeta = meta;
            selectedMediaData = null;
            if (selectBtn) selectBtn.disabled = true;
            if (fileDetailsContainer) fileDetailsContainer.style.display = 'none';
            showTab('library');
            modal.classList.add('active');
            loadFolders();
            loadMedia(1);
        }

        // Función para cerrar el modal
        function closeModal() {
            modal.classList.remove('active');
            tinymceCallback = null;
            tinymceMeta = null;
        }

        // Función para mostrar pestaña
        function showTab(tabId) {
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            const tab = Array.from(tabs).find(t => t.dataset.tab === tabId);
            if (tab) tab.classList.add('active');
            const content = document.getElementById(tabId + '-tab-tinymce');
            if (content) content.classList.add('active');
            if (tabId === 'upload' && progressContainer) {
                progressContainer.style.display = 'none';
            }
        }

        // Función para cargar medios
        function loadMedia(page = 1) {
            if (!mediaGrid || !window.MediaManagerConfig) return;
            currentPage = page;
            loader.style.display = 'flex';
            mediaGrid.innerHTML = '';
            if (paginationContainer) paginationContainer.innerHTML = '';

            const url = new URL(window.MediaManagerConfig.dataUrl, window.location.origin);
            url.searchParams.append('page', page);
            if (searchTerm) url.searchParams.append('search', searchTerm);
            if (filterType && filterType !== 'all') url.searchParams.append('type', filterType);
            if (currentFolderId) url.searchParams.append('folder_id', currentFolderId);
            if (currentDisk) url.searchParams.append('disk', currentDisk);

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    loader.style.display = 'none';
                    if (data.success && data.media && data.media.length > 0) {
                        data.media.forEach(item => {
                            const div = createMediaItem(item);
                            mediaGrid.appendChild(div);
                        });
                        if (data.pagination) renderPagination(data.pagination);
                    } else {
                        mediaGrid.innerHTML = '<div class="md-empty">No se encontraron imágenes</div>';
                    }
                })
                .catch(err => {
                    loader.style.display = 'none';
                    mediaGrid.innerHTML = '<div class="md-error">Error al cargar</div>';
                    console.error(err);
                });
        }

        // Función para cargar carpetas
        function loadFolders() {
            if (!foldersTree || !window.MediaManagerConfig) return;
            foldersTree.innerHTML = '<div class="md-folders-loading">Cargando...</div>';

            const url = new URL(window.MediaManagerConfig.foldersStructureUrl, window.location.origin);
            if (currentDisk) url.searchParams.append('disk', currentDisk);

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.folders) {
                        renderFolders(data.folders);
                    } else {
                        foldersTree.innerHTML = '<div class="md-folders-loading">Sin carpetas</div>';
                    }
                })
                .catch(err => {
                    foldersTree.innerHTML = '<div class="md-folders-loading">Error</div>';
                    console.error(err);
                });
        }

        // Función para renderizar carpetas
        function renderFolders(folders) {
            const rootFolder = folders.find(f => f.path === '/' || f.parent_id === null);
            let html = `<div class="md-folder-item ${!currentFolderId ? 'active' : ''}" data-folder-id="" data-folder-name="Raíz">
                <svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                <span>Raíz</span>
            </div>`;

            function buildTree(parentId) {
                return folders.filter(f => f.parent_id === parentId).map(f => ({
                    ...f,
                    children: buildTree(f.id)
                }));
            }

            function renderFolder(folder, level = 0) {
                const isActive = currentFolderId == folder.id;
                let h = `<div class="md-folder-item ${isActive ? 'active' : ''}" data-folder-id="${folder.id}" data-folder-name="${escapeHtml(folder.name)}" style="padding-left: ${10 + (level + 1) * 15}px">
                    <svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                    <span>${escapeHtml(folder.name)}</span>
                </div>`;
                if (folder.children) {
                    folder.children.forEach(c => { h += renderFolder(c, level + 1); });
                }
                return h;
            }

            if (rootFolder) {
                const tree = buildTree(rootFolder.id);
                tree.forEach(f => { html += renderFolder(f); });
            }

            foldersTree.innerHTML = html;

            // Event listeners para carpetas
            foldersTree.querySelectorAll('.md-folder-item').forEach(item => {
                item.addEventListener('click', function() {
                    const folderId = this.dataset.folderId;
                    currentFolderId = folderId ? parseInt(folderId) : null;
                    currentFolderName = this.dataset.folderName;
                    foldersTree.querySelectorAll('.md-folder-item').forEach(f => f.classList.remove('active'));
                    this.classList.add('active');
                    updateFolderDisplay();
                    loadMedia(1);
                });
            });
        }

        // Función para actualizar display de carpeta
        function updateFolderDisplay() {
            if (currentFolderDisplay) {
                currentFolderDisplay.querySelector('span').textContent = currentFolderName;
            }
            const uploadFolderName = document.getElementById('md-upload-folder-name-tinymce');
            if (uploadFolderName) uploadFolderName.textContent = currentFolderName;
        }

        // Función para crear item de media
        function createMediaItem(item) {
            const div = document.createElement('div');
            div.className = 'md-item';
            div.dataset.id = item.id;
            div.dataset.url = item.url;
            div.dataset.filename = item.filename;
            div.dataset.dimensions = (item.width && item.height) ? `${item.width} × ${item.height}` : '';

            const isImage = item.mime_type && item.mime_type.startsWith('image/');
            const thumb = item.thumbnail_url || item.url;

            div.innerHTML = `
                <div class="md-item-thumbnail">
                    ${isImage ? `<img src="${escapeHtml(thumb)}" alt="${escapeHtml(item.filename)}" loading="lazy">` :
                    `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path></svg>`}
                </div>
                <div class="md-item-filename">${escapeHtml(item.filename)}</div>
            `;

            div.addEventListener('click', function() {
                mediaGrid.querySelectorAll('.md-item').forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
                selectedMediaData = {
                    url: this.dataset.url,
                    filename: this.dataset.filename,
                    dimensions: this.dataset.dimensions
                };
                updateFileDetails();
                selectBtn.disabled = false;
            });

            return div;
        }

        // Función para actualizar detalles
        function updateFileDetails() {
            if (!fileDetailsContainer) return;
            if (selectedMediaData) {
                fileDetailsContainer.style.display = 'flex';
                if (selectedPreview) selectedPreview.src = selectedMediaData.url;
                if (selectedFilename) selectedFilename.textContent = selectedMediaData.filename;
                if (selectedDimensions) selectedDimensions.textContent = selectedMediaData.dimensions || '';
            } else {
                fileDetailsContainer.style.display = 'none';
            }
        }

        // Función para renderizar paginación
        function renderPagination(pagination) {
            if (!paginationContainer || pagination.last_page <= 1) return;
            const ul = document.createElement('ul');

            // Prev
            const prevBtn = document.createElement('button');
            prevBtn.innerHTML = '&laquo;';
            prevBtn.className = pagination.current_page === 1 ? 'disabled' : '';
            prevBtn.onclick = () => { if (pagination.current_page > 1) loadMedia(pagination.current_page - 1); };
            const prevLi = document.createElement('li');
            prevLi.appendChild(prevBtn);
            ul.appendChild(prevLi);

            // Pages
            for (let i = 1; i <= pagination.last_page; i++) {
                if (i <= 3 || i > pagination.last_page - 3 || Math.abs(i - pagination.current_page) <= 1) {
                    const btn = document.createElement('button');
                    btn.textContent = i;
                    btn.className = i === pagination.current_page ? 'active' : '';
                    btn.onclick = () => loadMedia(i);
                    const li = document.createElement('li');
                    li.appendChild(btn);
                    ul.appendChild(li);
                }
            }

            // Next
            const nextBtn = document.createElement('button');
            nextBtn.innerHTML = '&raquo;';
            nextBtn.className = pagination.current_page === pagination.last_page ? 'disabled' : '';
            nextBtn.onclick = () => { if (pagination.current_page < pagination.last_page) loadMedia(pagination.current_page + 1); };
            const nextLi = document.createElement('li');
            nextLi.appendChild(nextBtn);
            ul.appendChild(nextLi);

            paginationContainer.innerHTML = '';
            paginationContainer.appendChild(ul);
        }

        // Función para subir archivos
        function handleUpload(files) {
            if (!files || !files.length) return;
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressStatus.textContent = 'Subiendo...';

            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('file[]', files[i]);
            }
            formData.append('_csrf', window.MediaManagerConfig.csrfToken);
            if (currentFolderId) formData.append('folder_id', currentFolderId);
            if (currentDisk) formData.append('disk', currentDisk);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.MediaManagerConfig.uploadUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = pct + '%';
                    progressStatus.textContent = 'Subiendo... ' + pct + '%';
                }
            };

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        if (resp.success) {
                            progressStatus.textContent = 'Subida completada';

                            // Mostrar mensaje de éxito con SweetAlert
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Éxito',
                                    text: 'Archivo(s) subido(s) correctamente',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    customClass: { container: 'swal2-tinymce-top-layer' },
                                    didOpen: () => {
                                        const swalContainer = document.querySelector('.swal2-container');
                                        if (swalContainer) swalContainer.style.zIndex = '999999';
                                    }
                                });
                            }

                            setTimeout(() => {
                                showTab('library');
                                loadMedia(1);
                            }, 1000);
                        } else {
                            progressStatus.textContent = 'Error: ' + (resp.message || 'Error desconocido');

                            // LOG DETALLADO DEL ERROR
                            console.error('═══════════════════════════════════════════');
                            console.error('❌ ERROR DE SUBIDA - TinyMCE Media Manager');
                            console.error('═══════════════════════════════════════════');
                            console.error('Disco seleccionado:', currentDisk);
                            console.error('Folder ID:', currentFolderId);
                            console.error('Respuesta completa:', resp);
                            console.error('Mensaje de error:', resp.message || 'Sin mensaje específico');
                            console.error('Detalles adicionales:', resp.error || resp.errors || 'No disponible');
                            console.error('═══════════════════════════════════════════');

                            // Mostrar error con SweetAlert
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: resp.message || 'Error al subir archivo(s)',
                                    customClass: { container: 'swal2-tinymce-top-layer' },
                                    didOpen: () => {
                                        const swalContainer = document.querySelector('.swal2-container');
                                        if (swalContainer) {
                                            swalContainer.style.zIndex = '999999';
                                            console.log('✅ Z-index del modal de error establecido a 999999');
                                        }
                                    }
                                });
                            }
                        }
                    } catch (e) {
                        progressStatus.textContent = 'Error al procesar respuesta';

                        // LOG DE ERROR DE PARSING
                        console.error('═══════════════════════════════════════════');
                        console.error('❌ ERROR AL PARSEAR RESPUESTA DEL SERVIDOR');
                        console.error('═══════════════════════════════════════════');
                        console.error('Error de parsing:', e);
                        console.error('Respuesta raw del servidor:', xhr.responseText);
                        console.error('═══════════════════════════════════════════');

                        // Mostrar error con SweetAlert
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error al procesar la respuesta del servidor. Revisa la consola (F12) para más detalles.',
                                customClass: { container: 'swal2-tinymce-top-layer' },
                                didOpen: () => {
                                    const swalContainer = document.querySelector('.swal2-container');
                                    if (swalContainer) swalContainer.style.zIndex = '999999';
                                }
                            });
                        }
                    }
                } else {
                    progressStatus.textContent = 'Error de subida';

                    // LOG DE ERROR HTTP
                    console.error('═══════════════════════════════════════════');
                    console.error('❌ ERROR HTTP - Código de estado:', xhr.status);
                    console.error('═══════════════════════════════════════════');
                    console.error('Status:', xhr.status, xhr.statusText);
                    console.error('Respuesta del servidor:', xhr.responseText);
                    console.error('Headers:', xhr.getAllResponseHeaders());
                    console.error('═══════════════════════════════════════════');

                    // Mostrar error con SweetAlert
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error HTTP ' + xhr.status,
                            text: 'Error al subir archivo. Revisa la consola (F12) para más detalles.',
                            customClass: { container: 'swal2-tinymce-top-layer' },
                            didOpen: () => {
                                const swalContainer = document.querySelector('.swal2-container');
                                if (swalContainer) swalContainer.style.zIndex = '999999';
                            }
                        });
                    }
                }
            };

            xhr.onerror = function() {
                progressStatus.textContent = 'Error de red';

                // LOG DE ERROR DE RED
                console.error('═══════════════════════════════════════════');
                console.error('❌ ERROR DE RED - No se pudo conectar al servidor');
                console.error('═══════════════════════════════════════════');
                console.error('URL destino:', window.MediaManagerConfig.uploadUrl);
                console.error('Posible causa: Problema de conexión, CORS, o servidor no disponible');
                console.error('═══════════════════════════════════════════');

                // Mostrar error con SweetAlert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Red',
                        text: 'No se pudo conectar al servidor. Verifica tu conexión a Internet.',
                        customClass: { container: 'swal2-tinymce-top-layer' },
                        didOpen: () => {
                            const swalContainer = document.querySelector('.swal2-container');
                            if (swalContainer) swalContainer.style.zIndex = '999999';
                        }
                    });
                }
            };

            xhr.send(formData);
        }

        // Utilidad
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Event Listeners
        if (closeBtn) closeBtn.onclick = closeModal;
        if (cancelBtn) cancelBtn.onclick = closeModal;
        modal.onclick = function(e) { if (e.target === modal) closeModal(); };
        modal.querySelector('.md-modal').onclick = function(e) { e.stopPropagation(); };

        tabs.forEach(tab => {
            tab.onclick = function() { showTab(this.dataset.tab); };
        });

        if (searchButton && searchInput) {
            searchButton.onclick = function() { searchTerm = searchInput.value; loadMedia(1); };
            searchInput.onkeypress = function(e) { if (e.key === 'Enter') { e.preventDefault(); searchTerm = this.value; loadMedia(1); } };
        }

        // Filtro de tipo eliminado - siempre mostramos todos los archivos

        if (diskSelector) {
            diskSelector.onchange = function() {
                currentDisk = this.value;
                currentFolderId = null;
                currentFolderName = 'Raíz';
                updateFolderDisplay();
                loadFolders();
                loadMedia(1);
            };
        }

        if (selectBtn) {
            selectBtn.onclick = function() {
                if (selectedMediaData && tinymceCallback) {
                    tinymceCallback(selectedMediaData.url, { title: selectedMediaData.filename });
                    closeModal();
                }
            };
        }

        // Upload events
        if (browseButton && fileInput) {
            browseButton.onclick = function() { fileInput.click(); };
            fileInput.onchange = function() { if (this.files.length) handleUpload(this.files); };
        }

        if (dropzone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
                dropzone.addEventListener(evt, function(e) { e.preventDefault(); e.stopPropagation(); });
            });
            ['dragenter', 'dragover'].forEach(evt => {
                dropzone.addEventListener(evt, function() { dropzone.classList.add('dragover'); });
            });
            ['dragleave', 'drop'].forEach(evt => {
                dropzone.addEventListener(evt, function() { dropzone.classList.remove('dragover'); });
            });
            dropzone.addEventListener('drop', function(e) {
                if (e.dataTransfer.files.length) handleUpload(e.dataTransfer.files);
            });
        }

        // Exponer función global
        window.openMediaManagerForTinyMCE = openModal;
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMediaManager);
    } else {
        initMediaManager();
    }
})();
</script>
