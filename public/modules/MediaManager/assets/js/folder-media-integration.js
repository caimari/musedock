/**
 * Integración entre Folder Manager y Media Manager
 * Este script modifica la función loadMedia para soportar filtrado por carpeta
 */

(function() {
    'use strict';

    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {

        // Sobrescribir la función loadMedia original para incluir folder_id
        const originalLoadMedia = window.loadMedia;

        if (typeof originalLoadMedia === 'function') {
            window.loadMedia = function(page = 1) {
                const gridContainer = document.getElementById('media-library-grid');
                const loadingIndicator = document.getElementById('media-loading');
                const paginationContainer = document.getElementById('media-pagination');
                const filesCount = document.getElementById('files-count');

                if (!gridContainer || !loadingIndicator || !paginationContainer) return;

                loadingIndicator.style.display = 'block';
                gridContainer.innerHTML = '';
                gridContainer.appendChild(loadingIndicator);
                paginationContainer.innerHTML = '';

                // Construir URL con parámetros
                const dataUrl = window.MediaManagerConfig.dataUrl;
                const url = new URL(dataUrl, window.location.origin);
                url.searchParams.append('page', page);

                // AGREGAR FOLDER_ID SI EXISTE
                const currentFolderId = window.FolderManager?.currentFolderId || window.currentFolderId;
                if (currentFolderId) {
                    url.searchParams.append('folder_id', currentFolderId);
                }

                fetch(url)
                    .then(response => {
                        if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                        return response.json();
                    })
                    .then(data => {
                        loadingIndicator.style.display = 'none';

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
                                window.renderPagination(data.pagination, paginationContainer, window.loadMedia);
                            }

                            window.currentPage = data.pagination.current_page;
                        } else if (data.success) {
                            gridContainer.innerHTML = '<div class="text-center text-muted w-100 p-5"><i class="bi bi-folder2-open" style="font-size: 3rem; opacity: 0.3;"></i><p class="mt-3">Esta carpeta está vacía</p></div>';
                            if (filesCount) {
                                filesCount.textContent = '0 archivos';
                            }
                        } else {
                            if (window.showError) {
                                window.showError('Error al cargar medios: ' + (data.message || 'Respuesta inválida'));
                            }
                        }
                    })
                    .catch(error => {
                        loadingIndicator.style.display = 'none';
                        console.error('Error fetching media:', error);
                        if (window.showError) {
                            window.showError('No se pudieron cargar los medios. ' + error.message);
                        }
                    });
            };
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
                <div class="media-item-thumbnail">
                    ${isImage
                        ? `<img src="${thumbnailUrl}" alt="${item.alt_text || item.filename}" loading="lazy">`
                        : `<i class="bi bi-file-earmark file-icon"></i>`
                    }
                </div>
                <div class="media-item-filename" title="${item.filename}">${item.filename}</div>
                <div class="media-item-actions">
                    <button type="button" class="btn-delete-media" data-media-id="${item.id}" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;

            return div;
        }

        // Cargar medios iniciales (carpeta raíz)
        setTimeout(() => {
            if (window.loadMedia) {
                window.loadMedia(1);
            }
        }, 500);
    });

})();
