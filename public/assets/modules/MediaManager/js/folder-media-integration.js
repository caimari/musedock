/**
 * Integración entre Folder Manager y Media Manager
 * Este script modifica la función loadMedia para soportar filtrado por carpeta
 */

(function() {
    'use strict';

    // Este archivo era un "parche" para añadir folder_id a loadMedia, pero ahora
    // admin-media.js ya soporta folder_id y controla cargas concurrentes.
    // Mantenerlo como no-op para evitar dobles cargas/condiciones de carrera.

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

})();
