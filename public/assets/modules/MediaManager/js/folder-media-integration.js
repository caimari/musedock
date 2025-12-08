/**
 * Integración entre Folder Manager y Media Manager
 * Este script modifica la función loadMedia para soportar filtrado por carpeta
 */

(function() {
    'use strict';

    // Helper para escapar HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Función principal que se ejecuta cuando todo está listo
    function initFolderMediaIntegration() {
        const gridContainer = document.getElementById('media-library-grid');
        const loadingIndicator = document.getElementById('media-loading');
        const paginationContainer = document.getElementById('media-pagination');
        const filesCount = document.getElementById('files-count');
        const folderPathIndicator = document.getElementById('current-folder-path');

        if (!gridContainer || !paginationContainer) {
            console.warn('[Folder-Media] Contenedores no encontrados');
            return;
        }

        // Crear nueva función loadMedia con soporte de carpetas
        function loadMediaWithFolders(page = 1) {
            // Mostrar loading
            if (loadingIndicator) {
                loadingIndicator.style.display = 'block';
                gridContainer.innerHTML = '';
                gridContainer.appendChild(loadingIndicator);
            }
            paginationContainer.innerHTML = '';

            // Construir URL con parámetros
            const dataUrl = window.MediaManagerConfig?.dataUrl || '/musedock/media/data';
            const url = new URL(dataUrl, window.location.origin);
            url.searchParams.append('page', page);

            // AGREGAR FOLDER_ID SI EXISTE
            const currentFolderId = window.FolderManager?.currentFolderId || window.currentFolderId;
            if (currentFolderId) {
                url.searchParams.append('folder_id', currentFolderId);
            }

            // AGREGAR DISCO ACTUAL SI EXISTE
            if (window.MediaManagerConfig?.currentDisk) {
                url.searchParams.append('disk', window.MediaManagerConfig.currentDisk);
            }

            console.log('[Folder-Media] Cargando medios:', url.toString());

            fetch(url)
                .then(response => {
                    if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                    return response.json();
                })
                .then(data => {
                    if (loadingIndicator) {
                        loadingIndicator.style.display = 'none';
                    }
                    gridContainer.innerHTML = '';

                    // Actualizar indicador de ruta de carpeta
                    if (folderPathIndicator) {
                        if (data.current_folder) {
                            folderPathIndicator.innerHTML = `<i class="bi bi-folder-fill text-warning me-1"></i> <strong>${escapeHtml(data.current_folder.name)}</strong> <small class="text-muted">(${escapeHtml(data.folder_path)})</small>`;
                        } else {
                            folderPathIndicator.innerHTML = '<i class="bi bi-folder-fill text-warning me-1"></i> <strong>Raíz</strong> <small class="text-muted">(/)</small>';
                        }
                    }

                    if (data.success && data.media && data.media.length > 0) {
                        // Actualizar contador
                        if (filesCount) {
                            filesCount.textContent = `${data.pagination.total} archivo${data.pagination.total !== 1 ? 's' : ''}`;
                        }

                        data.media.forEach(item => {
                            const element = window.createMediaItemElement ? window.createMediaItemElement(item) : createFallbackMediaElement(item);
                            gridContainer.appendChild(element);
                        });

                        if (window.renderPagination) {
                            window.renderPagination(data.pagination, paginationContainer, loadMediaWithFolders);
                        }

                        window.currentPage = data.pagination.current_page;
                    } else if (data.success) {
                        const folderName = data.current_folder ? data.current_folder.name : 'Raíz';
                        gridContainer.innerHTML = `<div class="text-center text-muted w-100 p-5"><i class="bi bi-folder2-open" style="font-size: 3rem; opacity: 0.3;"></i><p class="mt-3">La carpeta "<strong>${escapeHtml(folderName)}</strong>" está vacía</p></div>`;
                        if (filesCount) {
                            filesCount.textContent = '0 archivos';
                        }
                    } else {
                        if (window.showError) {
                            window.showError('Error al cargar medios: ' + (data.message || 'Respuesta inválida'));
                        }
                    }

                    // Actualizar botones de acciones (para mostrar Pegar si hay algo en clipboard)
                    if (window.updateActionButtons) {
                        window.updateActionButtons();
                    }
                })
                .catch(error => {
                    if (loadingIndicator) {
                        loadingIndicator.style.display = 'none';
                    }
                    console.error('Error fetching media:', error);
                    gridContainer.innerHTML = `<div class="text-center text-danger w-100 p-5">Error al cargar medios: ${escapeHtml(error.message)}</div>`;
                });
        }

        // Función fallback para crear elementos de media si no existe la original
        function createFallbackMediaElement(item) {
            const div = document.createElement('div');
            div.className = 'media-item';
            div.dataset.mediaId = item.id;
            div.dataset.url = item.url;

            const thumbnailUrl = item.thumbnail_url || item.url;
            const isImage = item.mime_type && item.mime_type.startsWith('image/');

            div.innerHTML = `
                <input type="checkbox" class="media-item-checkbox" data-id="${item.id}" style="position: absolute; top: 5px; left: 5px; z-index: 10;">
                <div class="media-item-thumbnail">
                    ${isImage
                        ? `<img src="${escapeHtml(thumbnailUrl)}" alt="${escapeHtml(item.alt_text || item.filename)}" loading="lazy">`
                        : `<i class="bi bi-file-earmark file-icon"></i>`
                    }
                </div>
                <div class="media-item-filename" title="${escapeHtml(item.filename)}">${escapeHtml(item.filename)}</div>
            `;

            return div;
        }

        // Reemplazar la función global loadMedia
        window.loadMedia = loadMediaWithFolders;

        // Cargar medios iniciales
        console.log('[Folder-Media] Iniciando carga de medios...');
        loadMediaWithFolders(1);
    }

    // Esperar a que el DOM y admin-media.js estén listos
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Esperar un poco más para que admin-media.js termine
            setTimeout(initFolderMediaIntegration, 100);
        });
    } else {
        // DOM ya está listo, esperar a admin-media.js
        setTimeout(initFolderMediaIntegration, 100);
    }

})();
