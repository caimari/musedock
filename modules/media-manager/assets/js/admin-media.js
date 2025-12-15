document.addEventListener('DOMContentLoaded', function() {
    // ========================================================
    // SECTION 1: ELEMENT REFERENCES - MAIN LIBRARY
    // ========================================================
    const gridContainer = document.getElementById('media-library-grid');
    const loadingIndicator = document.getElementById('media-loading');
    const paginationContainer = document.getElementById('media-pagination');
    const uploaderDiv = document.getElementById('media-uploader');
    const fileInput = document.getElementById('file-input');
    const browseButton = document.getElementById('browse-button');
    const uploadForm = document.getElementById('upload-form');
    const progressBar = document.querySelector('#upload-progress-bar .progress-bar');
    const progressContainer = document.getElementById('upload-progress-bar');
    const uploadStatus = document.getElementById('upload-status');
    
    // ========================================================
    // SECTION 2: ELEMENT REFERENCES - MODAL LIBRARY
    // ========================================================
    const modalElement = document.getElementById('mediaManagerModal');
    const modalGridContainer = document.getElementById('modal-media-library-grid');
    const modalLoadingIndicator = document.getElementById('modal-media-loading');
    const modalPaginationContainer = document.getElementById('modal-media-pagination');
    const selectMediaButton = document.getElementById('selectMediaButton');
    
    // ========================================================
    // SECTION 3: ELEMENT REFERENCES - EDIT META MODAL
    // ========================================================
    const editMetaModal = document.getElementById('editMediaMetaModal');
    const editMetaImagePreview = document.getElementById('editMetaImagePreview');
    const editMetaUrl = document.getElementById('editMetaUrl');
    const editMetaAlt = document.getElementById('editMetaAlt');
    const editMetaCaption = document.getElementById('editMetaCaption');
    const saveMetaButton = document.getElementById('saveMediaMetaButton');
    
    // ========================================================
    // SECTION 4: STATE VARIABLES
    // ========================================================
    let currentPage = 1;
    let selectedMediaItem = null;
    let targetInputElement = null;
    let targetPreviewElement = null;
    let currentEditMediaId = null;
    
    // ========================================================
    // SECTION 5: CONFIGURATION
    // ========================================================
    const uploadUrl = window.MediaManagerConfig.uploadUrl;
    const dataUrl = window.MediaManagerConfig.dataUrl;
    const deleteUrlBase = window.MediaManagerConfig.deleteUrlTemplate;
    const detailsUrlTemplate = window.MediaManagerConfig?.detailsUrlTemplate || '/musedock/media/:id/details';
    const updateUrlTemplate = window.MediaManagerConfig?.updateUrlTemplate || '/musedock/media/:id/update';
    const renameUrlTemplate = window.MediaManagerConfig?.renameUrlTemplate || '/musedock/media/:id/rename';
    const moveUrl = window.MediaManagerConfig?.moveUrl || '/musedock/media/move';

    function buildUrl(template, id) {
        return (template || '').replace(':id', id);
    }
    
    // ========================================================
    // SECTION 6: MAIN LIBRARY FUNCTIONS
    // ========================================================
    
    // --- Función para Cargar Medios (Main Library) ---
    function loadMedia(page = 1) {
        if (!gridContainer || !loadingIndicator || !paginationContainer) return;

        loadingIndicator.style.display = 'block'; // Mostrar cargando
        gridContainer.innerHTML = ''; // Limpiar rejilla (excepto cargando)
        gridContainer.appendChild(loadingIndicator); // Re-añadir por si acaso
        paginationContainer.innerHTML = ''; // Limpiar paginación

        // Construir URL con parámetros
        const url = new URL(dataUrl, window.location.origin);
        url.searchParams.append('page', page);
        // TODO: Añadir per_page, search, type, tenant_id si se implementan filtros

        fetch(url)
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                loadingIndicator.style.display = 'none'; // Ocultar cargando
                if (data.success && data.media && data.media.length > 0) {
                    data.media.forEach(item => gridContainer.appendChild(createMediaItemElement(item)));
                    renderPagination(data.pagination, paginationContainer, loadMedia);
                    currentPage = data.pagination.current_page;
                } else if (data.success) {
                    gridContainer.innerHTML = '<p class="text-center text-muted w-100">No se encontraron medios.</p>'; // Mensaje si está vacío
                } else {
                    showError('Error al cargar medios: ' + (data.message || 'Respuesta inválida'));
                }
            })
            .catch(error => {
                loadingIndicator.style.display = 'none';
                console.error('Error fetching media:', error);
                showError('No se pudieron cargar los medios. ' + error.message);
            });
    }

    // --- Función para Crear Elemento HTML de Media ---
    function createMediaItemElement(item) {
        const div = document.createElement('div');
        div.className = 'media-item';
        div.draggable = true; // Hacer draggable
        div.dataset.mediaId = item.id; // Guardar ID
        div.dataset.url = item.url; // Guardar URL para uso en modal

        const thumbnailUrl = item.thumbnail_url || item.url; // Usar thumb si existe
        const isImage = item.mime_type && item.mime_type.startsWith('image/');

        div.innerHTML = `
            <input type="checkbox" class="media-item-checkbox" data-id="${item.id}" style="position: absolute; top: 5px; left: 5px; z-index: 10;">
            <div class="media-item-thumbnail">
                ${isImage ?
                    `<img src="${escapeHtml(thumbnailUrl)}" alt="${escapeHtml(item.alt_text || item.filename)}" loading="lazy" style="cursor: pointer;">` :
                    `<i class="bi bi-file-earmark file-icon" style="cursor: pointer;"></i>` /* Icono genérico */
                }
            </div>
            <div class="media-item-filename" title="${escapeHtml(item.filename)}">${escapeHtml(item.filename)}</div>
        `;

        // Evento: Click en checkbox
        const checkbox = div.querySelector('.media-item-checkbox');
        checkbox.addEventListener('click', function(e) {
            e.stopPropagation();
            if (this.checked) {
                div.classList.add('selected');
                updateActionButtons();
            } else {
                div.classList.remove('selected');
                updateActionButtons();
            }
        });

        // Evento: Click en imagen abre preview (modal)
        const thumbnail = div.querySelector('.media-item-thumbnail img, .media-item-thumbnail .file-icon');
        if (thumbnail) {
            thumbnail.addEventListener('click', function(e) {
                e.stopPropagation();
                // Simular click en el contenedor .media-item para que el manejador global lo procese
                // El manejador global (línea 643) buscará el botón .edit-media-meta-btn dentro
                const clickEvent = new MouseEvent('click', {
                    bubbles: true,
                    cancelable: true,
                    view: window
                });
                div.dispatchEvent(clickEvent);
            });
        }

        // Crear botón oculto con los datos del media para que el manejador global pueda encontrarlo
        const hiddenEditBtn = document.createElement('button');
        hiddenEditBtn.className = 'edit-media-meta-btn';
        hiddenEditBtn.dataset.id = item.id;
        hiddenEditBtn.dataset.url = item.url;
        hiddenEditBtn.dataset.alt = item.alt_text || '';
        hiddenEditBtn.dataset.caption = item.caption || '';
        hiddenEditBtn.style.display = 'none';
        div.appendChild(hiddenEditBtn);
        
        return div;
    }
    
    // ========================================================
    // SECTION 7: MODAL LIBRARY FUNCTIONS
    // ========================================================
    
    // --- Función para Cargar Medios en Modal ---
    function loadMediaModal(page = 1) {
        if (!modalGridContainer) return;
        
        if (modalLoadingIndicator) {
            modalLoadingIndicator.style.display = 'block';
        }
        
        modalGridContainer.innerHTML = '';
        if (modalLoadingIndicator) {
            modalGridContainer.appendChild(modalLoadingIndicator);
        }
        
        if (modalPaginationContainer) {
            modalPaginationContainer.innerHTML = '';
        }

        // Construir URL con parámetros (usando la misma URL base que la biblioteca principal)
        const url = new URL(dataUrl, window.location.origin);
        url.searchParams.append('page', page);

        fetch(url)
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                if (modalLoadingIndicator) {
                    modalLoadingIndicator.style.display = 'none';
                }
                
                modalGridContainer.innerHTML = '';
                
                if (data.success && data.media && data.media.length > 0) {
                    data.media.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'col-2 media-item text-center';
                        div.style.cursor = "pointer";
                        div.dataset.url = item.url;
                        
                        const thumbnailUrl = item.thumbnail_url || item.url;
                        const isImage = item.mime_type && item.mime_type.startsWith('image/');
                        
                        div.innerHTML = `
                            ${isImage ?
                                `<img src="${escapeHtml(thumbnailUrl)}" class="img-fluid mb-2 border rounded" alt="${escapeHtml(item.filename)}">` :
                                `<div class="file-icon-container mb-2 border rounded"><i class="bi bi-file-earmark file-icon"></i></div>`
                            }
                            <div class="small text-truncate">${escapeHtml(item.filename)}</div>
                        `;
                        
                        div.addEventListener('click', () => {
                            if (selectedMediaItem) selectedMediaItem.classList.remove('border-primary');
                            selectedMediaItem = div;
                            div.classList.add('border-primary');
                            if (selectMediaButton) selectMediaButton.disabled = false;
                        });
                        
                        modalGridContainer.appendChild(div);
                    });
                    
                    // Renderizar paginación para el modal si existe el contenedor
                    if (modalPaginationContainer) {
                        renderPagination(data.pagination, modalPaginationContainer, loadMediaModal);
                    }
                } else if (data.success) {
                    modalGridContainer.innerHTML = '<p class="text-center text-muted w-100">No se encontraron medios.</p>';
                } else {
                    modalGridContainer.innerHTML = '<p class="text-center text-danger w-100">Error al cargar medios.</p>';
                    console.error('Error loading modal media:', data.message);
                }
            })
            .catch(error => {
                if (modalLoadingIndicator) {
                    modalLoadingIndicator.style.display = 'none';
                }
                modalGridContainer.innerHTML = '<p class="text-center text-danger w-100">Error de conexión.</p>';
                console.error('Error fetching modal media:', error);
            });
    }
    
   // --- Inicializar Modal Media Selector ---
    document.body.addEventListener('click', function(e) {
        // Detectar si se ha hecho clic en un botón para abrir el modal de medios
        const button = e.target.closest('.open-media-modal-button');
        if (button) {
            e.preventDefault();

            // Guardar referencias al input y a la imagen de previsualización
            targetInputElement = document.querySelector(button.dataset.inputTarget);
            targetPreviewElement = document.querySelector(button.dataset.previewTarget);
            selectedMediaItem = null;

            // Desactivar botón de seleccionar imagen hasta que se elija una
            if (selectMediaButton) {
                selectMediaButton.disabled = true;
            }

            // Mostrar mensaje de carga mientras se obtienen los medios
            if (modalGridContainer) {
                modalGridContainer.innerHTML = '<p class="text-center">Cargando medios...</p>';
            }

            // Cargar la primera página de medios en el modal
            loadMediaModal(1);

            // Asegurarse que el modal se abra (en caso de que se abra manualmente por JS)
            if (modalElement) {
                const mediaModalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                mediaModalInstance.show();
            }
        }
    });

    
    // --- Escuchar botón de selección en modal ---
    if (selectMediaButton) {
        selectMediaButton.addEventListener('click', () => {
            if (selectedMediaItem && targetInputElement) {
                targetInputElement.value = selectedMediaItem.dataset.url;
                if (targetPreviewElement) {
                    targetPreviewElement.src = selectedMediaItem.dataset.url;
                }
                bootstrap.Modal.getInstance(modalElement).hide();
            }
        });
    }
    
    // ========================================================
    // SECTION 8: UTILITY FUNCTIONS
    // ========================================================
    
    // --- Función para Escapar HTML ---
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // --- Función para Renderizar Paginación (Generalizada) ---
    function renderPagination(pagination, container, loadFunction) {
        if (!pagination || !container || pagination.last_page <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';
        const current = pagination.current_page;
        const last = pagination.last_page;

        // Botón Anterior
        html += `<li class="page-item ${current === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${current - 1}" aria-label="Previous"><span aria-hidden="true">«</span></a>
                 </li>`;

        // Números de página (lógica simple)
        // TODO: Mejorar para mostrar rangos y elipsis (...)
        for (let i = 1; i <= last; i++) {
            html += `<li class="page-item ${i === current ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                     </li>`;
        }

        // Botón Siguiente
        html += `<li class="page-item ${current === last ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${current + 1}" aria-label="Next"><span aria-hidden="true">»</span></a>
                 </li>`;

        container.innerHTML = html;

        // Añadir listeners a los nuevos botones de paginación
        container.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const pageElement = e.target.closest('[data-page]');
                if (pageElement) {
                    const pageNum = pageElement.dataset.page;
                    const parentLi = pageElement.closest('.page-item');
                    if (pageNum && !parentLi.classList.contains('disabled') && !parentLi.classList.contains('active')) {
                        loadFunction(parseInt(pageNum));
                    }
                }
            });
        });
    }
    
    // --- Funciones Helper para Notificaciones (usando SweetAlert2) ---
    function showSuccess(message) {
        Swal.fire({ icon: 'success', title: 'Éxito', text: message, timer: 2000, showConfirmButton: false });
    }
    
    function showError(message) {
        Swal.fire({ icon: 'error', title: 'Error', text: message });
    }

// ========================================================
// SECTION 9: FILE UPLOAD HANDLING - SOPORTE PARA MÚLTIPLES ARCHIVOS
// ========================================================

// --- Manejo de Subida de Archivos ---
if (fileInput) {
    // Habilitar selección múltiple
    fileInput.setAttribute('multiple', 'multiple');
}

if (browseButton && fileInput) {
    browseButton.addEventListener('click', () => fileInput.click()); // Abrir selector al hacer clic en botón
    fileInput.addEventListener('change', handleFilesUpload); // Manejar archivos seleccionados
}

// Drag and Drop (Básico)
if (uploaderDiv) {
    uploaderDiv.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploaderDiv.classList.add('is-dragover');
    });

    uploaderDiv.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploaderDiv.classList.remove('is-dragover');
    });

    uploaderDiv.addEventListener('drop', (e) => {
        e.preventDefault();
        uploaderDiv.classList.remove('is-dragover');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files; // Asignar archivos al input
            handleFilesUpload(); // Procesar subida
        }
    });
}

function handleFilesUpload() {
    if (!fileInput || !fileInput.files || !fileInput.files.length) return;

    const files = Array.from(fileInput.files);
    const totalFiles = files.length;

    uploadStatus.textContent = `Subiendo ${totalFiles} archivo(s)...`;
    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    progressBar.setAttribute('aria-valuenow', 0);

    const csrfToken = document.querySelector('input[name="_token"]')?.value;
    const uploadFolderInput = document.getElementById('upload-folder-id');
    const currentFolderId = uploadFolderInput?.value || window.FolderManager?.currentFolderId || window.currentFolderId;
    const currentDisk = window.MediaManagerConfig?.currentDisk;

    let uploadedCount = 0;
    let errorCount = 0;

    if (browseButton) browseButton.disabled = true;
    fileInput.disabled = true;

    function finishQueue() {
        progressContainer.style.display = 'none';
        uploadStatus.textContent = '';
        fileInput.value = '';
        if (browseButton) browseButton.disabled = false;
        fileInput.disabled = false;

        if (uploadedCount > 0 && errorCount === 0) {
            showSuccess(`${uploadedCount} archivo(s) subido(s) correctamente.`);
        } else if (uploadedCount > 0 && errorCount > 0) {
            showSuccess(`${uploadedCount} archivo(s) subido(s). ${errorCount} con errores.`);
        } else if (errorCount > 0) {
            showError(`No se pudo subir ningún archivo (${errorCount} error(es)).`);
        }
    }

    function uploadOne(index) {
        if (index >= totalFiles) {
            finishQueue();
            return;
        }

        const file = files[index];
        uploadStatus.textContent = `Subiendo ${index + 1}/${totalFiles}: ${file.name}`;

        const formData = new FormData();
        formData.append('file[]', file);
        if (csrfToken) formData.append('_token', csrfToken);
        if (currentDisk) formData.append('disk', currentDisk);
        if (currentFolderId && currentFolderId !== '' && currentFolderId !== '1') {
            formData.append('folder_id', currentFolderId);
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl, true);

        xhr.upload.onprogress = function(event) {
            if (event.lengthComputable) {
                const fileProgress = event.total > 0 ? (event.loaded / event.total) : 0;
                const overall = Math.round(((index + fileProgress) / totalFiles) * 100);
                progressBar.style.width = overall + '%';
                progressBar.setAttribute('aria-valuenow', overall);
            }
        };

        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        const uploaded = (response.files && Array.isArray(response.files) ? response.files : (response.media ? [response.media] : []));
                        uploaded.forEach(media => addMediaToGrid(media));
                        uploadedCount += uploaded.length || 1;
                    } else {
                        errorCount += 1;
                        console.error('[Upload] Error:', response);
                    }
                } catch (e) {
                    errorCount += 1;
                    console.error("Error parsing upload response:", e, xhr.responseText);
                }
            } else {
                errorCount += 1;
                console.error(`[Upload] HTTP ${xhr.status} ${xhr.statusText}`, xhr.responseText);
            }

            uploadOne(index + 1);
        };

        xhr.onerror = function() {
            errorCount += 1;
            console.error('[Upload] Network error');
            uploadOne(index + 1);
        };

        xhr.send(formData);
    }

    uploadOne(0);
}

// Función auxiliar para añadir un medio a la cuadrícula
function addMediaToGrid(media) {
    if (!media || !gridContainer) return;
    
    const newItemElement = createMediaItemElement(media);
    
    // Limpiar mensajes si es necesario
    if (loadingIndicator && loadingIndicator.parentNode === gridContainer) {
        loadingIndicator.remove();
    }
    
    const noMediaMsg = gridContainer.querySelector('p');
    if (noMediaMsg) {
        noMediaMsg.remove();
    }
    
    // Añadir al principio de la cuadrícula
    gridContainer.prepend(newItemElement);
}
    // ========================================================
    // SECTION 10: MEDIA DELETE HANDLING
    // ========================================================
    
    // --- Manejo de Borrado ---
    function handleDeleteMedia(event) {
        event.stopPropagation(); // Evitar que se seleccione el item al borrar
        const button = event.currentTarget;
        const mediaId = button.dataset.id;
        const itemElement = button.closest('.media-item');

        if (!mediaId || !itemElement) return;

        Swal.fire({
            title: '¿Eliminar este archivo?',
            text: "Se eliminará permanentemente del servidor y la biblioteca.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const deleteUrl = deleteUrlBase.replace(':id', mediaId);
                // Añadir CSRF si es necesario para DELETE (mejor usar header)
                const csrfToken = document.querySelector('input[name="_token"]')?.value;

                fetch(deleteUrl, {
                    method: 'POST', // O 'DELETE' si tu router y servidor lo soportan
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        // Incluir CSRF en headers es más estándar para AJAX DELETE/POST
                        'X-CSRF-TOKEN': csrfToken,
                        // Si usas POST con _method:
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    // Route is POST, no _method needed
                    body: new URLSearchParams({'_token': csrfToken})
                })
                .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
                .then(({ ok, status, data }) => {
                    if (ok && data.success) {
                        itemElement.remove(); // Quitar del DOM
                        showSuccess(data.message || 'Medio eliminado.');
                        // Comprobar si la rejilla quedó vacía
                        if (!gridContainer.querySelector('.media-item')) {
                            gridContainer.innerHTML = '<p class="text-center text-muted w-100">No se encontraron medios.</p>';
                        }
                        
                        // También refrescar el modal si está abierto
                        if (modalElement && modalElement.classList.contains('show')) {
                            loadMediaModal(1);
                        }
                    } else {
                        showError(data.message || `Error ${status}`);
                    }
                })
                .catch(error => {
                    console.error("Error deleting media:", error);
                    showError('Error de red al eliminar.');
                });
            }
        });
    }

// ========================================================
// SECTION 11: EDIT METADATA HANDLING - MODAL MEJORADO CON AJAX NAV (v2 - Debugging)
// ========================================================

// --- INICIO: Polyfill para :contains (Añadir una sola vez, fuera de la función) ---
// (Código del polyfill :contains como en la versión anterior - sin cambios)
if (!Element.prototype.hasOwnProperty(':contains')) {
    const originalQsa = document.querySelectorAll;
    document.querySelectorAll = function(selector) {
        try {
            return originalQsa.call(this, selector);
        } catch (e) {
            if (selector.includes(':contains(')) {
                const match = selector.match(/:contains\(["']?([^)"']+)["']?\)/);
                if (match) {
                    const searchText = match[1];
                    const plainSelector = selector.replace(/:contains\(["']?[^)"']+["']?\)/, '').trim() || '*';
                    const elements = originalQsa.call(this, plainSelector);
                    return Array.from(elements).filter(el => el.textContent.includes(searchText));
                }
            }
            throw e;
        }
    };
    const originalQs = document.querySelector;
    document.querySelector = function(selector) {
         try {
            return originalQs.call(this, selector);
        } catch (e) {
            if (selector.includes(':contains(')) {
                const match = selector.match(/:contains\(["']?([^)"']+)["']?\)/);
                if (match) {
                    const searchText = match[1];
                    const plainSelector = selector.replace(/:contains\(["']?[^)"']+["']?\)/, '').trim() || '*';
                    const elements = originalQsa.call(this, plainSelector);
                    return Array.from(elements).find(el => el.textContent.includes(searchText)) || null;
                }
            }
            throw e;
        }
    };
}
// --- FIN: Polyfill para :contains ---


// Manejador principal para edición de metadatos
document.body.addEventListener('click', function(e) {
    // Primero buscar click en botón de edición directo
    let editBtn = e.target.closest('.edit-media-meta-btn');

    // Si no, buscar si se hizo clic en la imagen o su contenedor
    if (!editBtn) {
        const mediaItem = e.target.closest('.media-item, .attachment');
        if (mediaItem && !e.target.closest('.media-item-actions')) {
            // Si no se hizo clic en las acciones, buscar el botón de edición dentro del elemento
            editBtn = mediaItem.querySelector('.edit-media-meta-btn');
        }
    }

    if (editBtn) {
        e.preventDefault();
        console.log('[Modal Trigger] Edición activada (click en imagen o botón):', editBtn);

        const mediaId = editBtn.dataset.id || editBtn.dataset.mediaId;
        if (!mediaId) {
            console.error("[Modal Trigger] No se encontró mediaId en el botón:", editBtn);
            showError("No se pudo identificar el elemento a editar.");
            return;
        }
        const mediaUrl = editBtn.dataset.url;
        const mediaAlt = editBtn.dataset.alt || '';
        const mediaCaption = editBtn.dataset.caption || '';

        Swal.fire({
            title: 'Cargando...',
            text: 'Obteniendo información del archivo',
            allowOutsideClick: false, showConfirmButton: false,
            willOpen: () => { Swal.showLoading(); }
        });

        fetch(buildUrl(detailsUrlTemplate, mediaId))
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error ${response.status}`);
                return response.json();
            })
            .then(data => {
                Swal.close();
                if (data.success && data.media) {
                    console.log("[Modal Trigger] Detalles obtenidos vía AJAX:", data.media);
                    createMediaModal(data.media);
                } else {
                    console.warn("[Modal Trigger] AJAX falló o no trajo datos, usando fallback:", data?.message);
                    showError(data?.message || "Error al obtener detalles del archivo (usando datos básicos).");
                    const basicInfo = { id: mediaId, url: mediaUrl, alt_text: mediaAlt, caption: mediaCaption, filename: mediaUrl ? mediaUrl.split('/').pop() : 'Archivo' };
                    createMediaModal(basicInfo);
                }
            })
            .catch(err => {
                console.error("[Modal Trigger] Error en fetch:", err);
                Swal.close();
                // No bloquear UX por fallo de detalles: abrir modal con datos básicos sin alerta extra.
                console.warn(`[Modal Trigger] No se pudieron obtener detalles (HTTP). Usando datos básicos.`, err);
                const basicInfo = { id: mediaId, url: mediaUrl, alt_text: mediaAlt, caption: mediaCaption, filename: mediaUrl ? mediaUrl.split('/').pop() : 'Archivo' };
                createMediaModal(basicInfo);
            });

        function createMediaModal(mediaInfo) {
             console.log("[Modal Init] Creando modal para ID:", mediaInfo.id, mediaInfo);
            // Llamada a la función principal que ahora contiene toda la lógica AJAX
            let modalInstance;
            modalInstance = createCustomWPModal({
                mediaId: mediaInfo.id,
                mediaUrl: mediaInfo.url,
                mediaAlt: mediaInfo.alt_text || '',
                mediaCaption: mediaInfo.caption || '',
                mediaInfo: mediaInfo, // Pasar toda la info

                // --- Callbacks (sin cambios) ---
              onSave: function(data) {
    console.log("[Modal Callback] onSave llamado para ID:", data.id);
    const formData = new URLSearchParams();
    formData.append('alt_text', data.alt);
    formData.append('caption', data.caption);
    const csrfToken = document.querySelector('input[name="_token"]')?.value;
    if (csrfToken) formData.append('_token', csrfToken);

	    fetch(buildUrl(updateUrlTemplate, data.id), {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData
    })
    .then(response => response.json())
    .then(responseData => {
        if (responseData.success) {
            // Actualizar datos locales
            if (typeof loadMedia === 'function' && typeof currentPage !== 'undefined') {
                loadMedia(currentPage);
            } else { console.warn("loadMedia o currentPage no definidos globalmente."); }
            
            // Actualizar modal de selección si está abierto
            if (window.modalElement && window.modalElement.classList.contains('show') && typeof loadMediaModal === 'function') {
                loadMediaModal(1);
            }
            
            // Actualizar botón original si existe
            const originalButton = document.querySelector(`.edit-media-meta-btn[data-id="${data.id}"]`);
            if (originalButton) {
                originalButton.dataset.alt = data.alt;
                originalButton.dataset.caption = data.caption;
            }
            
            // Mostrar mensaje de éxito sin cerrar el modal
            const swalConfig = {
                icon: 'success',
                title: 'Éxito',
                text: responseData.message || "Metadatos guardados correctamente",
                timer: 2000,
                position: 'top-end',
                toast: true,
                showConfirmButton: false,
                customClass: { container: 'swal-modal-top-right' }
            };
            
            // Crear estilo para posicionar Swal correctamente si no existe
            if (!document.getElementById('swal-modal-top-right-style')) {
                const swalStyle = document.createElement('style');
                swalStyle.id = 'swal-modal-top-right-style';
                swalStyle.textContent = `.swal-modal-top-right { z-index: 999999 !important; position: fixed !important; top: 10px !important; right: 10px !important; }`;
                document.head.appendChild(swalStyle);
            }
            
            // Mostrar toast de éxito
            Swal.fire(swalConfig);
            
            // Aplicar efecto visual al elemento editado
            const editedItem = document.querySelector(`.media-item[data-media-id="${data.id}"], .attachment[data-id="${data.id}"]`);
            if (editedItem) {
                editedItem.classList.add('edit-success-flash');
                setTimeout(() => editedItem.classList.remove('edit-success-flash'), 1500);
            }
            
            // Restaurar botón de guardar sin cerrar el modal
            if (data.onSuccess) {
                // En lugar de llamar directamente a onSuccess (que cerraría el modal),
                // restauramos el botón de guardar manualmente
                const saveButton = document.getElementById('modalSaveBtn');
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = 'Guardar cambios';
                    saveButton.style.cursor = 'pointer';
                }
                
                // También actualizamos los datos del modal para reflejar los cambios
                const altText = document.getElementById('modalAltText');
                const caption = document.getElementById('modalCaption');
                if (altText) altText.value = data.alt;
                if (caption) caption.value = data.caption;
            }
        } else {
            // Error al guardar
            showError(responseData.message || "Error al guardar los metadatos.");
            if (data.onError) data.onError(); // Restaura el botón de guardar
        }
    })
    .catch(err => {
        showError(`Error de red al guardar: ${err.message}`);
        console.error("[Modal Callback] Error guardando metadatos:", err);
        if (data.onError) data.onError();
    });
},
onDelete: function(id) {
    console.log("[Modal Callback] onDelete llamado para ID:", id);
    const csrfToken = document.querySelector('input[name="_token"]')?.value;
    const deleteUrlTemplate = window.MediaManagerConfig?.deleteUrlTemplate || '/musedock/media/:id/delete';
    const deleteUrl = deleteUrlTemplate.replace(':id', id);

    const extractItemId = (item) => {
        const raw = item?.dataset?.mediaId || item?.dataset?.id || item?.getAttribute?.('data-id') || item?.id || '';
        const match = String(raw).match(/(\d+)/);
        return match ? match[1] : raw;
    };

    // Verificar si hay más elementos para navegar
    const galleryItemsSelector = '.media-item[data-media-id], .attachment[data-id], .attachment[data-media-id]';
    const allMediaItems = Array.from(document.querySelectorAll(galleryItemsSelector));
    const currentIndex = allMediaItems.findIndex(item => {
        const itemId = extractItemId(item);
        return itemId == id;
    });
    
    // Determinar el siguiente elemento a mostrar (si existe)
    let nextItemId = null;
    if (allMediaItems.length > 1) {
        // Si hay un elemento siguiente, usarlo
        if (currentIndex < allMediaItems.length - 1) {
            const nextItem = allMediaItems[currentIndex + 1];
            nextItemId = extractItemId(nextItem);
        } 
        // Si no hay siguiente pero hay anterior, usar el anterior
        else if (currentIndex > 0) {
            const prevItem = allMediaItems[currentIndex - 1];
            nextItemId = extractItemId(prevItem);
        } else if (currentIndex < 0) {
            // Fallback si no encontramos el actual en DOM: tomar el primero distinto
            const fallbackItem = allMediaItems.find(item => extractItemId(item) != id);
            nextItemId = fallbackItem ? extractItemId(fallbackItem) : null;
        }
    }

    fetch(deleteUrl, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({'_token': csrfToken})
    })
    .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
    .then(({ ok, status, data }) => {
        if (ok && data.success) {
            // Eliminar del DOM
            const itemElement = document.querySelector(`.media-item[data-media-id="${id}"], .attachment[data-id="${id}"], .attachment[data-media-id="${id}"]`);
            if (itemElement) itemElement.remove();
            
            // Actualizar la vista de grid si quedó vacía
            const gridContainer = document.getElementById('media-grid-container');
            if (gridContainer && !gridContainer.querySelector('.media-item, .attachment')) {
                gridContainer.innerHTML = '<p class="text-center text-muted w-100">No se encontraron medios.</p>';
            }
            
            // Actualizar modal de selección si está abierto
            if (window.modalElement && window.modalElement.classList.contains('show') && typeof loadMediaModal === 'function') {
                loadMediaModal(1);
            }
            
            // Mostrar mensaje de éxito en una posición que no tape el modal
            const swalConfig = {
                icon: 'success',
                title: 'Éxito',
                text: data.message || 'Medio eliminado.',
                timer: 2000,
                position: 'top-end',
                toast: true,
                showConfirmButton: false,
                customClass: { container: 'swal-modal-top-right' }
            };
            
            // Crear estilo para posicionar Swal correctamente
            if (!document.getElementById('swal-modal-top-right-style')) {
                const swalStyle = document.createElement('style');
                swalStyle.id = 'swal-modal-top-right-style';
                swalStyle.textContent = `.swal-modal-top-right { z-index: 999999 !important; position: fixed !important; top: 10px !important; right: 10px !important; }`;
                document.head.appendChild(swalStyle);
            }
            
            // Si hay un elemento al que navegar, mostrar ese en lugar de cerrar el modal
            if (nextItemId) {
                console.log("[Modal Delete] Navegando al siguiente elemento ID:", nextItemId);
                // Mostrar mensaje de éxito
                Swal.fire(swalConfig);
                
                // Cargar el siguiente elemento
                setTimeout(() => {
                    if (modalInstance && typeof modalInstance.loadMedia === 'function') {
                        modalInstance.loadMedia(nextItemId);
                    } else {
                        console.warn("[Modal Delete] No se pudo acceder a la instancia del modal para navegar.");
                    }
                }, 100);
                
                return; // No cerrar el modal
            }
            
            // Si no hay más elementos, cerrar modal y mostrar mensaje
            Swal.fire(swalConfig);
            if (modalInstance && typeof modalInstance.close === 'function') {
                setTimeout(() => modalInstance.close(), 150);
            }
        } else {
            // Error al eliminar
            showError(data?.message || `Error al eliminar (${status})`);
        }
    })
    .catch(error => {
        console.error("[Modal Callback] Error deleting media:", error);
        showError(`Error de red al eliminar: ${error.message}`);
    });
}
            });
        }
    }
});

// Función principal para crear el modal (CON NAVEGACIÓN AJAX y LOGGING)
function createCustomWPModal(options) {
    // --- Configuración inicial y helpers (sin cambios funcionales importantes) ---
    const config = {
        mediaId: '', mediaUrl: '', mediaAlt: '', mediaCaption: '', mediaInfo: {},
        onSave: null, onClose: null, onDelete: null, ...options
    };
    const currentUsername = document.querySelector('meta[name="current-user-name"]')?.content || 'Usuario';
    const formatFileSize = (bytes) => { /* ... (código formatFileSize sin cambios) ... */
        if (bytes === 0 || !bytes || isNaN(bytes)) return '-';
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        if (i >= sizes.length) return (bytes / Math.pow(1024, sizes.length - 1)).toFixed(1) + ' ' + sizes[sizes.length - 1];
        return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + sizes[i];
    };
    const initialMediaInfo = { /* ... (valores por defecto como antes) ... */
        filename: config.mediaUrl ? config.mediaUrl.split('/').pop() : 'archivo',
        mime_type: '', size: 0, dimensions: '',
        upload_date: new Date().toLocaleDateString('es-ES'),
        uploader: currentUsername, upload_path: '', type_description: '',
        ...config.mediaInfo
    };
    const initialFullUrl = config.mediaUrl ? new URL(config.mediaUrl, window.location.origin).href : '';

    // --- Crear estructura del Modal (Overlay, Container, InnerHTML) ---
    // (Código de creación del overlay y modalContainer como en la versión anterior - sin cambios)
     const overlay = document.createElement('div');
     overlay.id = 'wpCustomModalOverlay';
     overlay.style.cssText = `position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.7); z-index: 999999; display: flex; align-items: center; justify-content: center; overflow: hidden;`;
     const modalContainer = document.createElement('div');
     modalContainer.id = 'wpCustomModal';
     modalContainer.style.cssText = `background: #fff; border-radius: 3px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); width: 950px; max-width: 90vw; max-height: 90vh; position: relative; display: flex; flex-direction: column; margin: 0 auto; overflow: hidden;`;

    // Inner HTML - Asegúrate que los 'data-modal-info', 'data-modal-row', 'data-modal-link' coinciden con los usados en updateModalContent
    modalContainer.innerHTML = `
        <div class="modal-header" style="padding: 0; border-bottom: 1px solid #dcdcde; position: relative; display: flex; align-items: center; background-color: #fff;">
            <h3 class="modal-title" style="padding: 15px; margin: 0; font-size: 18px; font-weight: 600; line-height: 1.3; flex-grow: 1;">
                Detalles del adjunto: <span data-modal-title-filename>${initialMediaInfo.filename || 'Archivo'}</span>
            </h3>
            <div style="display: flex; height: 56px;">
                <button type="button" id="modalPrevBtn" class="modal-nav-btn" title="Anterior (←)" style="width: 50px; height: 100%; border: none; background: transparent; color: #3c434a; cursor: pointer; font-size: 24px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-chevron-left"></i></button>
                <button type="button" id="modalNextBtn" class="modal-nav-btn" title="Siguiente (→)" style="width: 50px; height: 100%; border: none; background: transparent; color: #3c434a; cursor: pointer; font-size: 24px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-chevron-right"></i></button>
                <button type="button" id="modalCloseBtn" class="modal-close-btn" title="Cerrar (Esc)" style="width: 50px; height: 100%; border: none; background: transparent; color: #3c434a; cursor: pointer; font-size: 24px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-x"></i></button>
            </div>
        </div>
        <div class="modal-body" style="display: flex; flex-direction: row; overflow: hidden; height: calc(90vh - 56px);">
            <div class="attachment-media-view" style="width: 65%; padding: 16px; border-right: 1px solid #dcdcde; text-align: center; display: flex; align-items: center; justify-content: center; background: #f0f0f1; overflow: hidden; position: relative;">
                 <div style="max-height: 100%; max-width: 100%; overflow: auto;">
                     <img src="${config.mediaUrl || ''}" class="attachment-preview" alt="${config.mediaAlt || ''}" style="max-height: calc(90vh - 150px); width: auto; max-width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,.2); display: block; margin: auto;">
                 </div>
            </div>
            <div class="attachment-details" style="width: 35%; padding: 16px; overflow-y: auto; background-color: #fff;">
                <h2 style="font-size: 14px; font-weight: 600; margin: 0 0 12px; color: #646970;">Detalles del archivo</h2>
                <div class="file-info" style="margin-bottom: 20px; font-size: 13px; color: #3c434a;">
                    <table style="width: 100%; border-collapse: collapse;"><tbody>
                        <tr><td style="padding: 4px 0; color: #646970; width: 130px;"><strong>Subido el:</strong></td><td style="padding: 4px 0;" data-modal-info="upload_date">${initialMediaInfo.upload_date || '-'}</td></tr>
                        <tr><td style="padding: 4px 0; color: #646970;"><strong>Subido por:</strong></td><td style="padding: 4px 0;" data-modal-info="uploader">${initialMediaInfo.uploader || '-'}</td></tr>
                        <tr data-modal-row="upload_path" style="${initialMediaInfo.upload_path ? '' : 'display: none;'}" ><td style="padding: 4px 0; color: #646970;"><strong>Subido a:</strong></td><td style="padding: 4px 0;" data-modal-info="upload_path">${initialMediaInfo.upload_path || '-'}</td></tr>
                        <tr><td style="padding: 4px 0; color: #646970;"><strong>Nombre:</strong></td><td style="padding: 4px 0;" data-modal-info="filename">${initialMediaInfo.filename || '-'}</td></tr>
                        <tr><td style="padding: 4px 0; color: #646970;"><strong>Tipo:</strong></td><td style="padding: 4px 0;" data-modal-info="type">${initialMediaInfo.type_description || initialMediaInfo.mime_type || '-'}</td></tr>
                        <tr><td style="padding: 4px 0; color: #646970;"><strong>Tamaño:</strong></td><td style="padding: 4px 0;" data-modal-info="size">${formatFileSize(initialMediaInfo.size) || '-'}</td></tr>
                        <tr data-modal-row="dimensions" style="${initialMediaInfo.dimensions ? '' : 'display: none;'}" ><td style="padding: 4px 0; color: #646970;"><strong>Dimensiones:</strong></td><td style="padding: 4px 0;" data-modal-info="dimensions">${initialMediaInfo.dimensions || '-'}</td></tr>
                    </tbody></table>
                    <div style="margin-top: 10px; display: flex; gap: 10px; font-size: 12px;">
                        <a href="${initialFullUrl}" download="${initialMediaInfo.filename || 'archivo'}" data-modal-link="download" style="color: #0073aa; text-decoration: none; display: inline-flex; align-items: center;"><i class="bi bi-download" style="margin-right: 4px;"></i> Descargar archivo</a>
                        ${config.onDelete ? `<a href="#" id="modalDeleteBtn" style="color: #cc1818; text-decoration: none; display: inline-flex; align-items: center;"><i class="bi bi-trash" style="margin-right: 4px;"></i> Borrar permanentemente</a>` : ''}
                    </div>
                </div>
                <hr style="margin: 15px 0; border: none; border-top: 1px solid #dcdcde;">
                <div class="url-field attachment-info" style="margin-bottom: 18px;"><label style="display: block; font-weight: 500; margin-bottom: 5px;"><i class="bi bi-link-45deg"></i> URL del archivo</label><div class="input-group" style="display: flex;"><input type="text" id="modalUrlInput" class="form-control" value="${initialFullUrl}" readonly style="flex-grow: 1; background-color: #f0f0f1; font-size: 13px; padding: 6px 12px; border: 1px solid #ced4da; border-radius: 4px 0 0 4px;"><button class="btn-copy" id="modalCopyBtn" title="Copiar URL" style="cursor: pointer; padding: 6px 12px; background-color: #f8f9fa; border: 1px solid #ced4da; border-left: none; border-radius: 0 4px 4px 0;"><i class="bi bi-clipboard"></i></button></div></div>
                <div class="alt-text-field" style="margin-bottom: 18px;"><label for="modalAltText" style="display: block; font-weight: 500; margin-bottom: 5px;"><i class="bi bi-tag"></i> Texto alternativo</label><input type="text" id="modalAltText" class="form-control" value="${config.mediaAlt}" style="width: 100%; font-size: 13px; padding: 6px 12px; border: 1px solid #ced4da; border-radius: 4px;" placeholder="Describe el propósito de la imagen..."><p class="help-text" style="margin-top: 5px; font-size: 12px; color: #646970;">Dejar vacío si la imagen es puramente decorativa.</p></div>
                <div class="caption-field" style="margin-bottom: 18px;"><label for="modalCaption" style="display: block; font-weight: 500; margin-bottom: 5px;"><i class="bi bi-card-text"></i> Leyenda</label><textarea id="modalCaption" class="form-control" rows="3" style="width: 100%; font-size: 13px; padding: 6px 12px; border: 1px solid #ced4da; border-radius: 4px;" placeholder="Aparece debajo del contenido...">${config.mediaCaption}</textarea></div>
                <div class="actions" style="margin-top: 24px; text-align: right;"><button type="button" id="modalCancelBtn" class="btn-cancel" style="margin-right: 8px; padding: 6px 12px; font-size: 13px; background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; cursor: pointer;">Cancelar</button><button type="button" id="modalSaveBtn" class="btn-save" style="padding: 6px 12px; font-size: 13px; background-color: #0d6efd; color: white; border: 1px solid #0d6efd; border-radius: 4px; cursor: pointer;">Guardar</button></div>
            </div>
        </div>
    `;

    // --- Añadir al DOM y animación de entrada ---
    // (Código de añadir al DOM y animación como en la versión anterior - sin cambios)
    overlay.appendChild(modalContainer);
    document.body.appendChild(overlay);
    overlay.style.opacity = '0';
    modalContainer.style.transform = 'scale(0.9)';
    modalContainer.style.transition = 'transform 0.2s ease-out, opacity 0.2s ease-out'; // Añadir opacity a la transición
    overlay.style.transition = 'opacity 0.2s ease-out';
    requestAnimationFrame(() => { // Usar requestAnimationFrame para asegurar el inicio de la transición
        overlay.style.opacity = '1';
        modalContainer.style.transform = 'scale(1)';
    });


    // --- OBTENER REFERENCIAS A ELEMENTOS DEL MODAL ---
    const prevButton = document.getElementById('modalPrevBtn');
    const nextButton = document.getElementById('modalNextBtn');
    const closeButton = document.getElementById('modalCloseBtn');
    const cancelButton = document.getElementById('modalCancelBtn');
    const saveButton = document.getElementById('modalSaveBtn');
    const copyButton = document.getElementById('modalCopyBtn');
    const deleteButton = document.getElementById('modalDeleteBtn');
    const altText = document.getElementById('modalAltText');
    const caption = document.getElementById('modalCaption');
    const urlInput = document.getElementById('modalUrlInput');
    const previewImg = modalContainer.querySelector('.attachment-preview');
    const previewContainer = modalContainer.querySelector('.attachment-media-view > div'); // Contenedor scrolleable de la imagen
    const modalTitleFilename = modalContainer.querySelector('[data-modal-title-filename]');

    // --- FUNCIONES AUXILIARES INTERNAS ---

    function showModalMessage(message, type = 'info') { /* ... (código showMessage como antes, renombrado a showModalMessage) ... */
        if (typeof Swal !== 'undefined') {
            Swal.fire({ text: message, icon: type, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true, customClass: { container: 'swal-on-modal' } });
            if (!document.getElementById('swal-on-modal-style')) {
                const swalStyle = document.createElement('style');
                swalStyle.id = 'swal-on-modal-style';
                swalStyle.textContent = `.swal-on-modal { z-index: ${parseInt(overlay.style.zIndex || 999999) + 10} !important; }`;
                document.head.appendChild(swalStyle);
            }
        } else { console.log(`Modal Mensaje (${type}): ${message}`); }
    }

    function updateModalContent(mediaData) {
        console.log("[Modal Update] Actualizando modal con datos:", mediaData);

        if (!mediaData || !mediaData.id) {
            console.error("[Modal Update] Datos inválidos para actualizar.", mediaData);
            showModalMessage("Error interno al actualizar datos.", "error");
            return;
        }

        // --- Actualizar config global del modal ---
        config.mediaId = mediaData.id;
        config.mediaUrl = mediaData.url || '';
        config.mediaAlt = mediaData.alt_text || '';
        config.mediaCaption = mediaData.caption || '';
        config.mediaInfo = mediaData; // Guardar toda la info nueva

        // --- Actualizar Imagen ---
        if (previewImg) {
            let scrollPosition = null;
            if (previewContainer) { scrollPosition = { top: previewContainer.scrollTop, left: previewContainer.scrollLeft }; }

            previewImg.style.opacity = '0.5';
            previewImg.style.transition = 'opacity 0.2s ease-out';

            // Usar requestAnimationFrame para asegurar que la opacidad cambie antes de cambiar src
            requestAnimationFrame(() => {
                previewImg.src = config.mediaUrl;
                previewImg.alt = config.mediaAlt;

                previewImg.onload = () => {
                    console.log("[Modal Update] Imagen cargada:", config.mediaUrl);
                    previewImg.style.opacity = '1';
                    if (scrollPosition && previewContainer) {
                        previewContainer.scrollTop = scrollPosition.top;
                        previewContainer.scrollLeft = scrollPosition.left;
                    }
                    previewImg.onload = null; // Limpiar handler
                    previewImg.onerror = null;
                };
                previewImg.onerror = () => {
                    console.error("[Modal Update] Error cargando imagen:", config.mediaUrl);
                    previewImg.style.opacity = '1'; // Restaurar opacidad
                    previewImg.alt = "Error al cargar imagen";
                     // Podrías poner una imagen placeholder aquí: previewImg.src = 'ruta/error.png';
                    previewImg.onload = null;
                    previewImg.onerror = null;
                };
                 // Si la URL está vacía, forzar error
                 if (!config.mediaUrl) {
                     previewImg.onerror();
                 }
            });
        } else { console.warn("[Modal Update] Elemento .attachment-preview no encontrado."); }

        // --- Actualizar Campos de Texto y Detalles ---
// --- SOLUCIÓN PARA LA FECHA INVÁLIDA AL NAVEGAR ---
// Función auxiliar para obtener el nombre del mes en español
function obtenerNombreMes(numero) {
    const meses = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    return meses[numero - 1];
}

// Formatear fecha correctamente en español
let uploadDate = '-';
if (mediaData.upload_date) {
    try {
        // Intentar parsear la fecha que viene del servidor
        if (mediaData.upload_date.includes('de')) {
            // Si ya tiene el formato correcto (ej: "4 de mayo de 2025"), usarla directamente
            uploadDate = mediaData.upload_date;
        } else {
            // Si es una fecha estándar, convertirla al formato español
            const fecha = new Date(mediaData.upload_date);
            if (!isNaN(fecha.getTime())) {
                const dia = fecha.getDate();
                const mes = obtenerNombreMes(fecha.getMonth() + 1);
                const año = fecha.getFullYear();
                uploadDate = `${dia} de ${mes} de ${año}`;
            } else {
                // Si la fecha es inválida, usar la fecha actual
                const hoy = new Date();
                const dia = hoy.getDate();
                const mes = obtenerNombreMes(hoy.getMonth() + 1);
                const año = hoy.getFullYear();
                uploadDate = `${dia} de ${mes} de ${año}`;
            }
        }
    } catch (e) {
        console.error("[Modal Update] Error formateando fecha:", e);
        // En caso de error, usar fecha actual
        const hoy = new Date();
        const dia = hoy.getDate();
        const mes = obtenerNombreMes(hoy.getMonth() + 1);
        const año = hoy.getFullYear();
        uploadDate = `${dia} de ${mes} de ${año}`;
    }
} else {
    // Si no hay fecha, usar fecha actual
    const hoy = new Date();
    const dia = hoy.getDate();
    const mes = obtenerNombreMes(hoy.getMonth() + 1);
    const año = hoy.getFullYear();
    uploadDate = `${dia} de ${mes} de ${año}`;
}

// MEJORA PARA EL NOMBRE DE USUARIO
let uploader = mediaData.uploader || currentUsername || '-';
// Si es el ID 1, siempre mostrar "Super Admin"
if (mediaData.user_id == 1) {
    uploader = "Super Admin";
}

// --- Actualizar Campos de Texto y Detalles ---
const detailFields = {
    upload_date: uploadDate,
    uploader: uploader,
    upload_path: mediaData.upload_path,
    filename: mediaData.filename || '-',
    type: mediaData.type_description || mediaData.mime_type || '-',
    size: formatFileSize(mediaData.size) || '-',
    dimensions: mediaData.dimensions
};

for (const key in detailFields) {
    const element = modalContainer.querySelector(`[data-modal-info="${key}"]`);
    if (element) {
        element.textContent = detailFields[key] || '-';
    } else { 
        // Intento alternativo para elementos sin data-attributes
        if (key === 'upload_date') {
            const dateElement = modalContainer.querySelector('td:contains("Subido el:")');
            if (dateElement && dateElement.nextElementSibling) {
                dateElement.nextElementSibling.textContent = uploadDate;
            }
        } else if (key === 'uploader') {
            const uploaderElement = modalContainer.querySelector('td:contains("Subido por:")');
            if (uploaderElement && uploaderElement.nextElementSibling) {
                uploaderElement.nextElementSibling.textContent = uploader;
            }
        }
        
        console.warn(`[Modal Update] Elemento [data-modal-info="${key}"] no encontrado.`); 
    }
}

        // --- Actualizar Visibilidad de Filas Opcionales ---
        const uploadPathRow = modalContainer.querySelector('[data-modal-row="upload_path"]');
        if (uploadPathRow) { uploadPathRow.style.display = detailFields.upload_path ? '' : 'none'; }
         else { console.warn(`[Modal Update] Elemento [data-modal-row="upload_path"] no encontrado.`); }

        const dimensionsRow = modalContainer.querySelector('[data-modal-row="dimensions"]');
        if (dimensionsRow) { dimensionsRow.style.display = detailFields.dimensions ? '' : 'none'; }
         else { console.warn(`[Modal Update] Elemento [data-modal-row="dimensions"] no encontrado.`); }


        // --- Actualizar URL y Enlace Descarga ---
        const newFullUrl = config.mediaUrl ? new URL(config.mediaUrl, window.location.origin).href : '';
        if (urlInput) { urlInput.value = newFullUrl; }
        else { console.warn(`[Modal Update] Elemento #modalUrlInput no encontrado.`); }

        const downloadLink = modalContainer.querySelector('[data-modal-link="download"]');
        if (downloadLink) {
            downloadLink.href = newFullUrl;
            downloadLink.download = detailFields.filename || 'archivo';
        } else { console.warn(`[Modal Update] Elemento [data-modal-link="download"] no encontrado.`); }

        // --- Actualizar Campos Editables ---
        if (altText) { altText.value = config.mediaAlt; }
        else { console.warn(`[Modal Update] Elemento #modalAltText no encontrado.`); }

        if (caption) { caption.value = config.mediaCaption; }
         else { console.warn(`[Modal Update] Elemento #modalCaption no encontrado.`); }

        // --- Actualizar Título del Modal ---
        if (modalTitleFilename) { modalTitleFilename.textContent = detailFields.filename; }
         else { console.warn(`[Modal Update] Elemento [data-modal-title-filename] no encontrado.`); }

        // --- Actualizar Estado Botones Navegación ---
        checkNavigationLimits();
    }

    function checkNavigationLimits() {
        // Selector más robusto para encontrar los items en la vista principal O en el modal de selección
        const galleryItemsSelector = '.media-item[data-media-id], .attachment[data-id], .attachment[data-media-id]';
        const allMediaItems = Array.from(document.querySelectorAll(galleryItemsSelector));

        console.log(`[Modal Nav Check] Buscando items con selector: "${galleryItemsSelector}". Encontrados: ${allMediaItems.length}`);

        if (!allMediaItems.length) {
            console.warn("[Modal Nav Check] No se encontraron elementos para navegación.");
            if(prevButton) prevButton.classList.add('disabled-nav');
            if(nextButton) nextButton.classList.add('disabled-nav');
            return;
        }

        const currentIndex = allMediaItems.findIndex(item => {
            const itemId = item.dataset.mediaId || item.dataset.id || item.id;
            // Comparación flexible por si uno es número y otro string
            return itemId == config.mediaId;
        });

        console.log(`[Modal Nav Check] ID Actual: ${config.mediaId}. Índice encontrado: ${currentIndex}. Total Items: ${allMediaItems.length}`);

        if (prevButton) {
            prevButton.classList.toggle('disabled-nav', currentIndex <= 0);
             console.log(`[Modal Nav Check] Botón Prev ${currentIndex <= 0 ? 'Deshabilitado' : 'Habilitado'}`);
        }
        if (nextButton) {
            nextButton.classList.toggle('disabled-nav', currentIndex < 0 || currentIndex >= allMediaItems.length - 1);
             console.log(`[Modal Nav Check] Botón Next ${currentIndex < 0 || currentIndex >= allMediaItems.length - 1 ? 'Deshabilitado' : 'Habilitado'}`);
        }
    }

    function loadMediaData(mediaId) {
        console.log(`[Modal Load] Iniciando carga para ID: ${mediaId}`);
        if (!mediaId) {
            console.error("[Modal Load] ID de medio inválido.");
            showModalMessage("Error: ID de medio no válido.", "error");
            return;
        }

        // --- Mostrar Indicador de Carga ---
        if (previewImg) previewImg.style.opacity = '0.5';
        let loadingIndicator = document.getElementById('modal-loading-indicator');
        if (!loadingIndicator) {
            loadingIndicator = document.createElement('div');
            loadingIndicator.id = 'modal-loading-indicator';
             // Estilos del spinner (sin cambios)
             loadingIndicator.style.cssText = `position: absolute; top: 10px; right: 10px; width: 20px; height: 20px; border: 3px solid rgba(0,0,0,0.2); border-top-color: #3498db; border-radius: 50%; animation: modal-spin 1s linear infinite; z-index: 1051;`;
            if (!document.getElementById('modal-spin-style')) {
                 const animStyle = document.createElement('style'); animStyle.id = 'modal-spin-style';
                 animStyle.textContent = `@keyframes modal-spin { 100% { transform: rotate(360deg); } }`;
                 document.head.appendChild(animStyle);
            }
            const mediaView = modalContainer.querySelector('.attachment-media-view');
             if(mediaView) mediaView.appendChild(loadingIndicator); else modalContainer.appendChild(loadingIndicator); // Fallback
        }
        loadingIndicator.style.display = 'block';

        // --- Petición AJAX ---
        fetch(buildUrl(detailsUrlTemplate, mediaId))
            .then(response => {
                if (!response.ok) {
                    console.error(`[Modal Load] HTTP error ${response.status} para ID ${mediaId}`);
                    throw new Error(`HTTP error ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (data.success && data.media) {
                     console.log(`[Modal Load] Datos recibidos para ID ${mediaId}:`, data.media);
                    updateModalContent(data.media);
                } else {
                    console.warn(`[Modal Load] Respuesta no exitosa o sin datos para ID ${mediaId}:`, data?.message);
                    showModalMessage(data?.message || "No se pudieron cargar los detalles completos.", "warning");
                    fetchBasicDetailsFromDOM(mediaId); // Intentar fallback
                }
            })
            .catch(err => {
                console.error(`[Modal Load] Error en fetch para ID ${mediaId}:`, err);
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                showModalMessage(`Error de red al cargar (${err.message}).`, "error");
                fetchBasicDetailsFromDOM(mediaId); // Intentar fallback
            });
    }

    function fetchBasicDetailsFromDOM(mediaId) {
         console.log(`[Modal Fallback] Buscando datos básicos en DOM para ID: ${mediaId}`);
         const galleryItemsSelector = '.media-item[data-media-id], .attachment[data-id], .attachment[data-media-id]';
         const allMediaItems = Array.from(document.querySelectorAll(galleryItemsSelector));
         const mediaItem = allMediaItems.find(item => {
            const itemId = item.dataset.mediaId || item.dataset.id || item.id;
            return itemId == mediaId;
        });

        if (mediaItem) {
            console.log("[Modal Fallback] Elemento encontrado en DOM:", mediaItem);
            const mediaUrl = mediaItem.dataset.url || mediaItem.querySelector('img')?.src || '';
            const mediaAlt = mediaItem.dataset.alt || mediaItem.querySelector('img')?.alt || '';
            const mediaCaption = mediaItem.dataset.caption || '';
            const filename = mediaUrl.split('/').pop() || 'archivo';

            const basicInfo = {
                id: mediaId, url: mediaUrl, alt_text: mediaAlt, caption: mediaCaption, filename: filename,
                // Rellenar otros campos con valores por defecto
                 upload_date: new Date().toLocaleDateString('es-ES'), uploader: currentUsername,
                 mime_type: '', size: 0, dimensions: '', type_description: ' (datos básicos)'
            };
            console.log("[Modal Fallback] Datos básicos construidos:", basicInfo);
            updateModalContent(basicInfo);
        } else {
            console.error(`[Modal Fallback] No se encontró elemento en DOM con ID: ${mediaId}`);
            showModalMessage("Error crítico: No se encontró el elemento.", "error");
            // Considerar deshabilitar navegación o mostrar error persistente
            modalContainer.querySelector('.modal-body').innerHTML = `<p style="padding: 20px; text-align: center; color: red;">Error: No se pudo cargar la información del medio solicitado (ID: ${mediaId}).</p>`;
            if(prevButton) prevButton.classList.add('disabled-nav');
            if(nextButton) nextButton.classList.add('disabled-nav');
        }
    }

    // --- Función para cerrar el modal ---
    function closeModal() {
        console.log("[Modal Close] Cerrando modal...");
        document.removeEventListener('keydown', handleKeyDown); // Muy importante remover el listener
        overlay.style.opacity = '0';
        modalContainer.style.transform = 'scale(0.9)';
        // Limpiar estilos globales añadidos por el modal
        const stylesToRemove = ['modal-spin-style', 'modal-nav-styles', 'swal-on-modal-style', 'modal-btn-spinner-style'];
        stylesToRemove.forEach(id => {
            const styleEl = document.getElementById(id);
            if (styleEl) styleEl.remove();
        });

        setTimeout(() => {
            if (document.body.contains(overlay)) {
                document.body.removeChild(overlay);
            }
            if (config.onClose) config.onClose();
             console.log("[Modal Close] Modal eliminado del DOM.");
        }, 200); // Coincidir con duración de transición CSS
    }

    // --- EVENT LISTENERS ---

    // Botones básicos (Cerrar, Cancelar, Copiar, Guardar, Borrar)
    if (closeButton) closeButton.addEventListener('click', closeModal); else console.warn("Botón #modalCloseBtn no encontrado");
    if (cancelButton) cancelButton.addEventListener('click', closeModal); else console.warn("Botón #modalCancelBtn no encontrado");

    if (deleteButton && config.onDelete) { /* ... (código deleteButton sin cambios funcionales) ... */
deleteButton.addEventListener('click', function(e) {
    e.preventDefault();
    const currentZIndex = parseInt(overlay.style.zIndex || 999999);
    const swalConfig = { 
        title: '¿Eliminar este archivo?', 
        text: "Esta acción no se puede deshacer.", 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#d33', 
        cancelButtonColor: '#6c757d', 
        confirmButtonText: 'Sí, eliminar', 
        cancelButtonText: 'Cancelar', 
        customClass: { container: 'swal-higher-z-index' } 
    };
    const styleEl = document.createElement('style'); 
    styleEl.textContent = `.swal-higher-z-index { z-index: ${currentZIndex + 100} !important; }`;
    document.head.appendChild(styleEl);
    
    Swal.fire(swalConfig).then((result) => {
        if (document.head.contains(styleEl)) document.head.removeChild(styleEl);
        if (result.isConfirmed) {
            console.log("[Modal Delete] Confirmado. Llamando onDelete...");
            
            // CAMBIO AQUÍ: Ya no cerramos el modal primero
            // Solo llamamos a la función onDelete con el ID
            if (config.onDelete) {
                config.onDelete(config.mediaId);
            }
        } else {
            console.log("[Modal Delete] Cancelado.");
        }
    });
});
    }

     if (copyButton && urlInput) { /* ... (código copyButton sin cambios funcionales) ... */
         copyButton.addEventListener('click', function() {
            urlInput.select();
            try {
                document.execCommand('copy');
                const originalHTML = copyButton.innerHTML; copyButton.innerHTML = '<i class="bi bi-check2"></i>'; copyButton.disabled = true;
                setTimeout(() => { copyButton.innerHTML = originalHTML; copyButton.disabled = false; }, 1500);
                 console.log("[Modal Copy] URL copiada:", urlInput.value);
            } catch (err) {
                console.error('[Modal Copy] Error al copiar:', err); showModalMessage('Error al copiar la URL.', 'error');
            }
        });
    } else { console.warn("Botón #modalCopyBtn o input #modalUrlInput no encontrados"); }

    if (saveButton) { /* ... (código saveButton sin cambios funcionales) ... */
        saveButton.addEventListener('click', function() {
             console.log("[Modal Save] Botón Guardar clickeado.");
            if (saveButton.disabled) return; // Evitar doble click
            saveButton.disabled = true;
            saveButton.innerHTML = '<span style="display: inline-block; width: 12px; height: 12px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: modal-btn-spinner 0.6s linear infinite; margin-right: 5px;"></span> Guardando...';
            saveButton.style.cursor = 'not-allowed';
            if (!document.getElementById('modal-btn-spinner-style')) {
                const btnSpinnerStyle = document.createElement('style'); btnSpinnerStyle.id = 'modal-btn-spinner-style';
                btnSpinnerStyle.textContent = `@keyframes modal-btn-spinner { to { transform: rotate(360deg); } }`;
                document.head.appendChild(btnSpinnerStyle);
            }
            if (config.onSave) {
                config.onSave({
                    id: config.mediaId, alt: altText ? altText.value : '', caption: caption ? caption.value : '',
                    onSuccess: () => {
                        console.log("[Modal Save Callback] onSuccess llamado (normalmente cierra modal).");
                        // closeModal() es llamado por la lógica externa en este caso
                    },
                    onError: () => {
                        console.log("[Modal Save Callback] onError llamado.");
                        saveButton.disabled = false; saveButton.innerHTML = 'Guardar'; saveButton.style.cursor = 'pointer';
                    }
                });
            } else {
                 console.warn("[Modal Save] No se proporcionó callback onSave.");
                 saveButton.disabled = false; saveButton.innerHTML = 'Guardar'; saveButton.style.cursor = 'pointer';
            }
        });
    } else { console.warn("Botón #modalSaveBtn no encontrado"); }


    // --- Event Listeners para Navegación AJAX (CLAVE) ---
    if (prevButton) {
        prevButton.addEventListener('click', function(e) {
            e.preventDefault();
             console.log("[Modal Nav] Click Botón Anterior.");
            if (prevButton.classList.contains('disabled-nav')) {
                 console.log("[Modal Nav] Botón Anterior deshabilitado, ignorando.");
                 return;
            }
            const galleryItemsSelector = '.media-item[data-media-id], .attachment[data-id], .attachment[data-media-id]';
            const allMediaItems = Array.from(document.querySelectorAll(galleryItemsSelector));
            if (!allMediaItems.length) { console.warn("[Modal Nav] No items para navegar (prev)."); return; }
            const currentIndex = allMediaItems.findIndex(item => (item.dataset.mediaId || item.dataset.id || item.id) == config.mediaId);
             console.log(`[Modal Nav Prev] CurrentIndex: ${currentIndex}`);
            if (currentIndex > 0) {
                const prevItem = allMediaItems[currentIndex - 1];
                const prevId = prevItem.dataset.mediaId || prevItem.dataset.id || prevItem.id;
                if (prevId) {
                    console.log(`[Modal Nav Prev] Navegando a ID: ${prevId}`);
                    loadMediaData(prevId);
                } else { console.error("[Modal Nav Prev] No se pudo obtener ID del elemento anterior:", prevItem); }
            } else { console.log("[Modal Nav Prev] Ya está en el primer elemento."); }
        });
    } else { console.warn("Botón #modalPrevBtn no encontrado"); }

    if (nextButton) {
        nextButton.addEventListener('click', function(e) {
            e.preventDefault();
             console.log("[Modal Nav] Click Botón Siguiente.");
            if (nextButton.classList.contains('disabled-nav')) {
                 console.log("[Modal Nav] Botón Siguiente deshabilitado, ignorando.");
                 return;
            }
            const galleryItemsSelector = '.media-item[data-media-id], .attachment[data-id], .attachment[data-media-id]';
            const allMediaItems = Array.from(document.querySelectorAll(galleryItemsSelector));
            if (!allMediaItems.length) { console.warn("[Modal Nav] No items para navegar (next)."); return; }
            const currentIndex = allMediaItems.findIndex(item => (item.dataset.mediaId || item.dataset.id || item.id) == config.mediaId);
             console.log(`[Modal Nav Next] CurrentIndex: ${currentIndex}`);
            if (currentIndex >= 0 && currentIndex < allMediaItems.length - 1) {
                const nextItem = allMediaItems[currentIndex + 1];
                const nextId = nextItem.dataset.mediaId || nextItem.dataset.id || nextItem.id;
                if (nextId) {
                     console.log(`[Modal Nav Next] Navegando a ID: ${nextId}`);
                    loadMediaData(nextId);
                } else { console.error("[Modal Nav Next] No se pudo obtener ID del elemento siguiente:", nextItem); }
            } else { console.log("[Modal Nav Next] Ya está en el último elemento."); }
        });
    } else { console.warn("Botón #modalNextBtn no encontrado"); }

    // --- Estilo para botones deshabilitados ---
    if (!document.getElementById('modal-nav-styles')) {
        const navButtonStyle = document.createElement('style'); navButtonStyle.id = 'modal-nav-styles';
        navButtonStyle.textContent = `.modal-nav-btn.disabled-nav { opacity: 0.4; cursor: not-allowed !important; pointer-events: none; }`;
        document.head.appendChild(navButtonStyle);
    }

    // --- Manejador de Teclado (CLAVE) ---
    function handleKeyDown(e) {
        // console.log(`[Modal Kbd] Keydown: ${e.key}, Ctrl: ${e.ctrlKey}, Meta: ${e.metaKey}`); // Log para cada tecla
        const activeEl = document.activeElement;
        const isInputFocused = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA');
        const isSwalOpen = document.querySelector('.swal2-container.swal2-shown');

        if (isSwalOpen) {
             console.log("[Modal Kbd] Swal está abierto, ignorando teclas del modal.");
            return; // No hacer nada si Swal está abierto
        }

        if (e.key === 'Escape') {
            e.preventDefault();
             console.log("[Modal Kbd] Escape presionado. Cerrando modal.");
            closeModal();
            return; // Salir después de cerrar
        }

        // Si un input tiene foco, solo procesar Escape (ya manejado arriba) y Guardar
        if (isInputFocused && !((e.ctrlKey || e.metaKey) && e.key === 'Enter')) {
            console.log("[Modal Kbd] Input enfocado, ignorando tecla:", e.key);
            return;
        }

        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
             console.log("[Modal Kbd] Ctrl/Cmd+Enter presionado.");
            if (saveButton && !saveButton.disabled) {
                 console.log("[Modal Kbd] Disparando click en Guardar.");
                saveButton.click();
            } else { console.log("[Modal Kbd] Botón Guardar no disponible o deshabilitado."); }
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
             console.log("[Modal Kbd] Flecha Izquierda presionada.");
            if (prevButton && !prevButton.classList.contains('disabled-nav')) {
                 console.log("[Modal Kbd] Disparando click en Anterior.");
                prevButton.click();
            } else { console.log("[Modal Kbd] Botón Anterior no disponible o deshabilitado."); }
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
             console.log("[Modal Kbd] Flecha Derecha presionada.");
            if (nextButton && !nextButton.classList.contains('disabled-nav')) {
                 console.log("[Modal Kbd] Disparando click en Siguiente.");
                nextButton.click();
            } else { console.log("[Modal Kbd] Botón Siguiente no disponible o deshabilitado."); }
        }
    }
    // Añadir listener de teclado
    document.addEventListener('keydown', handleKeyDown);
     console.log("[Modal Init] Listener Keydown añadido.");


    // --- Otros Listeners (click en overlay, etc.) ---
    modalContainer.addEventListener('click', e => e.stopPropagation()); // Evitar cierre al clickear dentro
    overlay.addEventListener('click', (e) => { // Cierre al clickear fuera (si no hay Swal)
        if (e.target === overlay && !document.querySelector('.swal2-container.swal2-shown')) {
             console.log("[Modal Overlay Click] Cerrando modal.");
            closeModal();
        }
    });

    // --- Inicialización Final ---
    // Enfocar campo Alt Text
    setTimeout(() => {
        if(altText) altText.focus();
         console.log("[Modal Init] Foco puesto en Alt Text (o intentado).");
    }, 300);

    // Comprobar límites de navegación iniciales
    setTimeout(() => {
        console.log("[Modal Init] Comprobando límites de navegación iniciales...");
        checkNavigationLimits();
    }, 100); // Dar tiempo a que el modal se renderice

    // --- Retornar métodos públicos ---
    return {
        close: closeModal,
        getValues: () => ({ alt: altText ? altText.value : '', caption: caption ? caption.value : '' }),
        updateData: (newData) => { console.log("[Modal Method] updateData llamado", newData); updateModalContent({ ...config.mediaInfo, ...newData }); },
        loadMedia: (mediaId) => { console.log("[Modal Method] loadMedia llamado con ID:", mediaId); loadMediaData(mediaId); }
    };
}

// --- Funciones globales de notificación (Asegúrate que existan y funcionen) ---
function showSuccess(message) {
    console.info("showSuccess:", message);
    if (typeof Swal !== 'undefined') { 
        // Verificar si hay un modal abierto
        const isModalOpen = document.getElementById('wpCustomModalOverlay');
        
        if (isModalOpen) {
            // Si hay un modal abierto, mostrar el mensaje en la esquina superior derecha
            // Crear estilo para posicionar Swal correctamente si no existe
            if (!document.getElementById('swal-modal-top-right-style')) {
                const swalStyle = document.createElement('style');
                swalStyle.id = 'swal-modal-top-right-style';
                swalStyle.textContent = `.swal-modal-top-right { z-index: 999999 !important; position: fixed !important; top: 10px !important; right: 10px !important; }`;
                document.head.appendChild(swalStyle);
            }
            
            Swal.fire({ 
                icon: 'success', 
                title: 'Éxito', 
                text: message, 
                timer: 2000, 
                position: 'top-end',
                toast: true,
                showConfirmButton: false,
                customClass: { container: 'swal-modal-top-right' }
            });
        } else {
            // Si no hay modal, mostrar normalmente
            Swal.fire({ 
                icon: 'success', 
                title: 'Éxito', 
                text: message, 
                timer: 2000, 
                showConfirmButton: false 
            });
        }
    }
    else { alert("Éxito: " + message); }
}
function showError(message) {
    console.error("showError:", message);
    if (typeof Swal !== 'undefined') { Swal.fire({ icon: 'error', title: 'Error', text: message }); }
    else { alert("Error: " + message); }
}

// --- Variables globales / Funciones que este código asume que existen ---
// Descomenta y adapta si es necesario, o asegúrate de que estén definidas en otro lugar
/*
let currentPage = 1;
let gridContainer = document.getElementById('media-grid-container');
let modalElement = document.getElementById('mediaSelectionModal'); // O el ID de tu modal de selección
let MediaManagerConfig = { deleteUrlTemplate: '/musedock/media/:id/delete' }; // Ejemplo
function loadMedia(page) { console.log(`Placeholder: loadMedia(${page}) llamada.`); }
function loadMediaModal(page) { console.log(`Placeholder: loadMediaModal(${page}) llamada.`); }
*/

console.log("SECTION 11 (Modal con AJAX Nav v2) cargada.");
    // ========================================================
    // SECTION 12: CSS STYLES FOR VISUAL EFFECTS
    // ========================================================
    
    // Agregar estilos para el efecto visual de edición exitosa
    const styleElement = document.createElement('style');
    styleElement.textContent = `
        @keyframes edit-success-flash {
            0% { background-color: rgba(40, 167, 69, 0); }
            30% { background-color: rgba(40, 167, 69, 0.2); }
            100% { background-color: rgba(40, 167, 69, 0); }
        }
        
        .edit-success-flash {
            animation: edit-success-flash 1.5s ease;
        }
    `;
    document.head.appendChild(styleElement);
    
    // ========================================================
    // SECTION 13: DRAG AND DROP
    // ========================================================

    document.addEventListener('dragstart', function(e) {
        const mediaItem = e.target.closest('.media-item, .attachment');
        if (mediaItem) {
            const mediaId = mediaItem.dataset.mediaId || mediaItem.dataset.id;
            if (mediaId) {
                e.dataTransfer.effectAllowed = 'move';
                // Guardar múltiples IDs si hay selección
                const selectedItems = document.querySelectorAll('.media-item.selected, .attachment.selected');
                const ids = selectedItems.length > 0 ?
                    Array.from(selectedItems).map(item => item.dataset.mediaId || item.dataset.id) :
                    [mediaId];
                e.dataTransfer.setData('media-ids', JSON.stringify(ids));
            }
        }
    });

    // ========================================================
    // SECTION 13B: ACTION BUTTONS
    // ========================================================

    function getSelectedMediaIds() {
        return Array.from(document.querySelectorAll('.media-item-checkbox:checked')).map(cb => cb.dataset.id);
    }

    function updateActionButtons() {
        const selectedCount = getSelectedMediaIds().length;
        const fileCountSpan = document.getElementById('files-count');

        if (fileCountSpan) {
            if (selectedCount > 0) {
                fileCountSpan.textContent = `${selectedCount} archivo(s) seleccionado(s)`;
            } else {
                const totalCount = document.querySelectorAll('.media-item-checkbox').length;
                fileCountSpan.textContent = `${totalCount} archivo(s)`;
            }
        }

        // Mostrar/ocultar botones de acciones según selección
        const toolbar = document.querySelector('.toolbar-right');
        let actionButtons = toolbar?.querySelector('.action-buttons-group');

        // Determinar qué botones mostrar
        let buttonsHtml = '';

        if (selectedCount > 0) {
            buttonsHtml = `
                <button id="btn-copy-selected" class="btn btn-sm btn-outline-secondary" title="Copiar seleccionados">
                    <i class="bi bi-files"></i> Copiar
                </button>
                <button id="btn-cut-selected" class="btn btn-sm btn-outline-secondary" title="Cortar seleccionados">
                    <i class="bi bi-scissors"></i> Cortar
                </button>
                <button id="btn-delete-selected" class="btn btn-sm btn-outline-danger" title="Eliminar seleccionados">
                    <i class="bi bi-trash"></i> Eliminar
                </button>
            `;
        }

        // Agregar botón de pegar si hay algo en el portapapeles
        if (clipboardData.ids && clipboardData.ids.length > 0) {
            if (buttonsHtml) buttonsHtml += '<span style="margin: 0 0.5rem; border-left: 1px solid #e0e0e0;"></span>';
            buttonsHtml += `
                <button id="btn-paste" class="btn btn-sm btn-outline-success" title="Pegar aquí">
                    <i class="bi bi-clipboard"></i> Pegar
                </button>
            `;
        }

        if (buttonsHtml) {
            if (!actionButtons) {
                actionButtons = document.createElement('div');
                actionButtons.className = 'action-buttons-group';
                if (toolbar) {
                    toolbar.appendChild(actionButtons);
                }
            }
            actionButtons.innerHTML = buttonsHtml;

            // Agregar eventos a los botones
            if (selectedCount > 0) {
                document.getElementById('btn-copy-selected')?.addEventListener('click', copySelected);
                document.getElementById('btn-cut-selected')?.addEventListener('click', cutSelected);
                document.getElementById('btn-delete-selected')?.addEventListener('click', deleteSelected);
            }
            document.getElementById('btn-paste')?.addEventListener('click', pasteClipboard);
        } else if (actionButtons) {
            // Remover botones si no hay nada que mostrar
            actionButtons.remove();
        }
    }

    function copySelected() {
        const ids = getSelectedMediaIds();
        if (ids.length === 0) return;
        clipboardData = { action: 'copy', ids };
        showNotification(`${ids.length} archivo(s) copiado(s) al portapapeles`);
    }

    function cutSelected() {
        const ids = getSelectedMediaIds();
        if (ids.length === 0) return;
        clipboardData = { action: 'cut', ids };
        document.querySelectorAll('.media-item-checkbox:checked').forEach(cb => {
            const item = cb.closest('.media-item');
            if (item) item.style.opacity = '0.5';
        });
        showNotification(`${ids.length} archivo(s) cortado(s) al portapapeles`);
    }

    function deleteSelected() {
        const ids = getSelectedMediaIds();
        if (ids.length === 0) return;

        const msg = ids.length === 1 ? '¿Eliminar este archivo?' : `¿Eliminar ${ids.length} archivos?`;

        Swal.fire({
            title: 'Confirmación',
            text: msg,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        }).then(result => {
            if (result.isConfirmed) {
                // Eliminar cada archivo
                let deleted = 0;
                ids.forEach(id => {
                    fetch(buildUrl(deleteUrlTemplate, id), {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': getCsrfToken(),
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            deleted++;
                            if (deleted === ids.length) {
                                showNotification('Archivos eliminados correctamente');
                                loadMedia(1);
                            }
                        }
                    })
                    .catch(err => showError('Error al eliminar: ' + err.message));
                });
            }
        });
    }

    // ========================================================
    // SECTION 14: CONTEXT MENU
    // ========================================================

    let clipboardData = { action: null, ids: [] }; // Copiar/Cortar clipboard
    const contextMenu = document.getElementById('media-context-menu');
    let currentContextItem = null;

    // Mostrar menú contextual
    document.addEventListener('contextmenu', function(e) {
        const mediaItem = e.target.closest('.media-item, .attachment');
        if (mediaItem && gridContainer && gridContainer.contains(mediaItem)) {
            e.preventDefault();
            currentContextItem = mediaItem;
            const mediaId = mediaItem.dataset.mediaId || mediaItem.dataset.id;

            // Posicionar el menú
            contextMenu.style.left = e.pageX + 'px';
            contextMenu.style.top = e.pageY + 'px';
            contextMenu.style.display = 'block';

            // Mostrar/ocultar opción de pegar
            const pasteItem = contextMenu.querySelector('[data-action="paste"]');
            const pasteDivider = contextMenu.querySelector('[data-divider="paste"]');
            if (clipboardData.ids.length > 0) {
                pasteItem.style.display = 'block';
                pasteDivider.style.display = 'block';
            } else {
                pasteItem.style.display = 'none';
                pasteDivider.style.display = 'none';
            }
        }
    });

    // Ocultar menú contextual
    document.addEventListener('click', function(e) {
        if (!contextMenu.contains(e.target)) {
            contextMenu.style.display = 'none';
        }
    });

    // Manejar acciones del menú contextual
    document.querySelectorAll('.context-menu-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const action = this.dataset.action;
            const mediaId = currentContextItem ? (currentContextItem.dataset.mediaId || currentContextItem.dataset.id) : null;

            if (!mediaId && action !== 'paste') return;

            switch(action) {
                case 'rename':
                    showRenameDialog(mediaId);
                    break;
                case 'copy':
                    clipboardData = { action: 'copy', ids: [mediaId] };
                    showNotification('Archivo copiado al portapapeles');
                    break;
                case 'cut':
                    clipboardData = { action: 'cut', ids: [mediaId] };
                    currentContextItem.style.opacity = '0.5';
                    showNotification('Archivo cortado al portapapeles');
                    break;
                case 'paste':
                    pasteClipboard();
                    break;
                case 'delete':
                    confirmDelete(mediaId);
                    break;
            }
            contextMenu.style.display = 'none';
        });
    });

    // Función para renombrar
    function showRenameDialog(mediaId) {
        const mediaItem = document.querySelector(`.media-item[data-media-id="${mediaId}"], .attachment[data-id="${mediaId}"]`);
        if (!mediaItem) return;

        const currentName = mediaItem.querySelector('.media-item-filename')?.textContent || 'archivo';
        const newName = prompt('Renombrar archivo:', currentName);

        if (newName && newName !== currentName) {
            renameMedia(mediaId, newName);
        }
    }

    // Función para renombrar media via AJAX
    function renameMedia(mediaId, newName) {
        fetch(buildUrl(renameUrlTemplate, mediaId), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `filename=${encodeURIComponent(newName)}&_token=${document.querySelector('input[name="_token"]')?.value || ''}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification('Archivo renombrado correctamente');
                loadMedia(currentPage);
            } else {
                showError(data.message || 'Error al renombrar');
            }
        })
        .catch(err => showError('Error: ' + err.message));
    }

    // Función para pegar
    function pasteClipboard() {
        if (clipboardData.ids.length === 0) return;

        const targetFolderId = window.currentFolderId || '';
        const ids = clipboardData.ids;
        const action = clipboardData.action;

        fetch(moveUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `items[]=${ids.map(id => JSON.stringify({id, type: 'media'})).join('&items[]=')}&target_folder_id=${targetFolderId}&_token=${document.querySelector('input[name="_token"]')?.value || ''}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(action === 'cut' ? 'Archivo movido' : 'Archivo copiado');
                if (action === 'cut') {
                    clipboardData = { action: null, ids: [] };
                    document.querySelectorAll('.media-item, .attachment').forEach(item => item.style.opacity = '1');
                }
                loadMedia(currentPage);
            } else {
                showError(data.message || 'Error al pegar');
            }
        })
        .catch(err => showError('Error: ' + err.message));
    }

    // Función para mostrar notificaciones
    function showNotification(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-right',
                icon: 'success',
                title: message,
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            alert(message);
        }
    }

    // ========================================================
    // SECTION 13A: INITIALIZATION
    // ========================================================

    // --- Carga Inicial de la biblioteca principal ---
    if (gridContainer) {
        loadMedia(currentPage);
    }

}); // Fin DOMContentLoaded
