document.addEventListener('DOMContentLoaded', function() {

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

    let currentPage = 1;
	const uploadUrl = window.MediaManagerConfig.uploadUrl;
	const dataUrl = window.MediaManagerConfig.dataUrl;
	const deleteUrlBase = window.MediaManagerConfig.deleteUrlTemplate;

    // --- Función para Cargar Medios ---
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
                    renderPagination(data.pagination);
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
        div.dataset.mediaId = item.id; // Guardar ID

        const thumbnailUrl = item.thumbnail_url || item.url; // Usar thumb si existe
        const isImage = item.mime_type && item.mime_type.startsWith('image/');

        div.innerHTML = `
            <div class="media-item-thumbnail">
                ${isImage ?
                    `<img src="${escapeHtml(thumbnailUrl)}" alt="${escapeHtml(item.alt_text || item.filename)}" loading="lazy">` :
                    `<i class="bi bi-file-earmark file-icon"></i>` /* Icono genérico */
                }
            </div>
            <div class="media-item-filename" title="${escapeHtml(item.filename)}">${escapeHtml(item.filename)}</div>
            <div class="media-item-actions">
                <button class="delete-media-btn" data-id="${item.id}" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
                 {{-- Aquí podrían ir botones de Editar Meta, etc. --}}
            </div>
        `;
        // Añadir listener para borrar
        div.querySelector('.delete-media-btn').addEventListener('click', handleDeleteMedia);
        // Añadir listener para seleccionar (para el modal) - Pendiente
        // div.addEventListener('click', handleSelectMedia);
        return div;
    }

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

    // --- Función para Renderizar Paginación ---
    function renderPagination(pagination) {
        if (!pagination || pagination.last_page <= 1) {
            paginationContainer.innerHTML = '';
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

        paginationContainer.innerHTML = html;

        // Añadir listeners a los nuevos botones de paginación
        paginationContainer.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const pageNum = e.target.dataset.page;
                const parentLi = e.target.closest('.page-item');
                if (pageNum && !parentLi.classList.contains('disabled') && !parentLi.classList.contains('active')) {
                    loadMedia(parseInt(pageNum));
                }
            });
        });
    }

    // --- Manejo de Subida de Archivos ---
    if(browseButton && fileInput) {
        browseButton.addEventListener('click', () => fileInput.click()); // Abrir selector al hacer clic en botón
        fileInput.addEventListener('change', handleFilesUpload); // Manejar archivos seleccionados
    }

    // Drag and Drop (Básico)
    if(uploaderDiv) {
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
        if (!fileInput.files.length) return;

        const files = Array.from(fileInput.files);
        uploadStatus.textContent = `Subiendo ${files.length} archivo(s)...`;
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.setAttribute('aria-valuenow', 0);

        // Usar FormData para enviar archivos
        const formData = new FormData();
        files.forEach((file, index) => {
            formData.append('file[]', file); // Enviar como array si permites múltiple (ajustar backend)
                                           // O enviar uno por uno
        });
        // Añadir CSRF token si es necesario
        const csrfToken = document.querySelector('input[name="_csrf"]')?.value;
        if(csrfToken) formData.append('_csrf', csrfToken);


        // --- Subida con XMLHttpRequest para barra de progreso ---
        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl, true);

        // Progreso
        xhr.upload.onprogress = function(event) {
            if (event.lengthComputable) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressBar.setAttribute('aria-valuenow', percentComplete);
                uploadStatus.textContent = `Subiendo... ${percentComplete}%`;
            }
        };

        // Finalización
        xhr.onload = function() {
            progressContainer.style.display = 'none'; // Ocultar barra
            uploadStatus.textContent = ''; // Limpiar estado
            fileInput.value = ''; // Resetear input

            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success && response.media) {
                        // Añadir nuevo item al PRINCIPIO de la rejilla
                        const newItemElement = createMediaItemElement(response.media);
                        if (loadingIndicator) loadingIndicator.remove(); // Quitar mensaje 'cargando' si estaba
                        const noMediaMsg = gridContainer.querySelector('p');
                        if (noMediaMsg) noMediaMsg.remove(); // Quitar mensaje 'no hay medios'
                        gridContainer.prepend(newItemElement);
                        showSuccess('Archivo subido: ' + response.media.filename);
                    } else {
                        showError('Error al subir: ' + (response.message || 'Respuesta inválida'));
                    }
                } catch (e) {
                    showError('Error procesando respuesta del servidor.');
                    console.error("Error parsing upload response:", e, xhr.responseText);
                }
            } else {
                showError(`Error de subida: ${xhr.status} ${xhr.statusText}`);
            }
        };

        // Error de red
        xhr.onerror = function() {
             progressContainer.style.display = 'none';
             uploadStatus.textContent = '';
             fileInput.value = '';
             showError('Error de red durante la subida.');
        };

        xhr.send(formData);
    }

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
                 const csrfToken = document.querySelector('input[name="_csrf"]')?.value;

                 fetch(deleteUrl, {
                     method: 'POST', // O 'DELETE' si tu router y servidor lo soportan
                     headers: {
                         'X-Requested-With': 'XMLHttpRequest',
                          // Incluir CSRF en headers es más estándar para AJAX DELETE/POST
                         'X-CSRF-TOKEN': csrfToken,
                         // Si usas POST con _method:
                         'Content-Type': 'application/x-www-form-urlencoded',
                     },
                      // Si usas POST con _method:
                     body: new URLSearchParams({'_method': 'DELETE', '_csrf': csrfToken}) // Enviar _method=DELETE
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


     // --- Funciones Helper para Notificaciones (usando SweetAlert2) ---
     function showSuccess(message) {
         Swal.fire({ icon: 'success', title: 'Éxito', text: message, timer: 2000, showConfirmButton: false });
     }
     function showError(message) {
         Swal.fire({ icon: 'error', title: 'Error', text: message });
     }

    // --- Carga Inicial ---
    loadMedia(currentPage);

}); // Fin DOMContentLoaded