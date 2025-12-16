/**
 * Folder Manager for Media Manager
 * Maneja todas las funciones relacionadas con carpetas
 */

(function() {
    'use strict';

    // Estado global de carpetas
    window.FolderManager = {
        currentFolderId: null,
        folders: [],

        // Inicializar el gestor de carpetas
        init: function() {
            this.loadFolderStructure();
            this.setupEventListeners();
        },

        // Configurar eventos
        setupEventListeners: function() {
            // Botón crear carpeta (panel izquierdo)
            const btnCreateFolder = document.getElementById('btn-create-folder');
            if (btnCreateFolder) {
                btnCreateFolder.addEventListener('click', () => this.showCreateFolderDialog());
            }

            // Botón crear carpeta (toolbar)
            const btnCreateFolderNew = document.getElementById('btn-create-folder-new');
            if (btnCreateFolderNew) {
                btnCreateFolderNew.addEventListener('click', () => this.showCreateFolderDialog());
            }
        },

        // Cargar estructura de carpetas desde el servidor
        loadFolderStructure: function() {
            let url = window.MediaManagerConfig.foldersStructureUrl;
            if (!url || url.includes('#ruta-no-encontrada')) {
                url = '/musedock/media/folders/structure';
            }

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                // Verificar que la respuesta sea JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Respuesta del servidor no es JSON válido');
                }
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && Array.isArray(data.folders)) {
                    this.folders = data.folders;
                    this.renderFolderTree(data.folders);
                } else {
                    console.error('Error al cargar carpetas:', data.message || 'Respuesta inválida');
                    this.showError(data.message || 'No se pudieron cargar las carpetas');
                }
            })
            .catch(error => {
                console.error('Error fetching folders:', error);
                this.showError('Error al cargar carpetas: ' + error.message);
            });
        },

        // Renderizar árbol de carpetas
        renderFolderTree: function(folders) {
            const container = document.getElementById('folder-tree-container');
            if (!container) return;

            // Crear estructura jerárquica
            const tree = this.buildTree(folders);

            // Renderizar HTML
            container.innerHTML = this.renderTreeHTML(tree);

            // Agregar eventos a carpetas
            this.attachFolderEvents();

            // Actualizar selector de carpetas en toolbar
            this.updateFolderSelector(folders);
        },

        // Actualizar selector de carpetas
        updateFolderSelector: function(folders) {
            const selector = document.getElementById('folder-selector');
            if (!selector) return;

            // Mantener la opción Raíz
            selector.innerHTML = '<option value="">📁 Raíz</option>';

            // Agregar todas las carpetas
            folders.forEach(folder => {
                const isRoot = (folder.path === '/' || folder.parent_id === null);
                if (!isRoot) { // No incluir raíz
                    const option = document.createElement('option');
                    option.value = folder.id;
                    const indent = folder.parent_id ? '&nbsp;&nbsp;' : '';
                    option.textContent = folder.name;
                    selector.appendChild(option);
                }
            });

            // Evento al cambiar selector
            selector.addEventListener('change', (e) => {
                const folderId = e.target.value || '';
                const folderName = e.target.options[e.target.selectedIndex].text || 'Raíz';
                if (folderId) {
                    this.navigateToFolder(folderId, folderName);
                } else {
                    this.navigateToFolder('', 'Raíz');
                }
            });
        },

        // Construir estructura de árbol jerárquico
        buildTree: function(folders, parentId = null) {
            return folders
                .filter(folder => folder.parent_id === parentId)
                .map(folder => ({
                    ...folder,
                    children: this.buildTree(folders, folder.id)
                }));
        },

        // Renderizar HTML del árbol
        renderTreeHTML: function(nodes, level = 0) {
            if (!nodes || nodes.length === 0) {
                return level === 0 ? '<p class="text-muted small">No hay carpetas</p>' : '';
            }

            let html = '<ul class="folder-tree' + (level > 0 ? ' folder-children' : '') + '">';

            nodes.forEach(node => {
                const isActive = node.id === this.currentFolderId ? ' active' : '';
                const hasChildren = node.children && node.children.length > 0;
                const isRoot = (node.path === '/' || node.parent_id === null); // Detectar carpeta raíz real

                html += `
                    <li>
                        <div class="folder-item${isActive}" data-folder-id="${node.id}" data-folder-name="${this.escapeHtml(node.name)}">
                            <i class="bi bi-folder-fill folder-icon"></i>
                            <span class="folder-name">${this.escapeHtml(node.name)}</span>
                            <div class="folder-actions">
                                <button class="btn-rename-folder" title="Renombrar" data-folder-id="${node.id}" ${isRoot ? 'disabled' : ''} style="${isRoot ? 'opacity: 0.5; cursor: not-allowed;' : ''}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-delete-folder" title="Eliminar" data-folder-id="${node.id}" ${isRoot ? 'disabled' : ''} style="${isRoot ? 'opacity: 0.5; cursor: not-allowed;' : ''}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        ${hasChildren ? this.renderTreeHTML(node.children, level + 1) : ''}
                    </li>
                `;
            });

            html += '</ul>';
            return html;
        },

        // Agregar eventos a carpetas
        attachFolderEvents: function() {
            // Click en carpeta para navegar
            document.querySelectorAll('.folder-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    if (e.target.closest('.folder-actions')) return; // Ignorar si click en acciones

                    const folderId = item.dataset.folderId;
                    const folderName = item.dataset.folderName;
                    this.navigateToFolder(folderId, folderName);
                });

                // Drag and drop - permitir soltar archivos en carpetas
                item.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    item.classList.add('drag-over');
                });

                item.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    item.classList.remove('drag-over');
                });

                item.addEventListener('drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    item.classList.remove('drag-over');

                    const folderId = item.dataset.folderId;
                    const draggedIds = e.dataTransfer.getData('media-ids');

                    if (draggedIds) {
                        const ids = JSON.parse(draggedIds);
                        this.moveItemsToFolder(ids, folderId);
                    }
                });
            });

            // Botones renombrar
            document.querySelectorAll('.btn-rename-folder').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (btn.disabled) return; // Ignorar si está deshabilitado
                    const folderId = btn.dataset.folderId;
                    this.showRenameFolderDialog(folderId);
                });
            });

            // Botones eliminar
            document.querySelectorAll('.btn-delete-folder').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (btn.disabled) return; // Ignorar si está deshabilitado
                    const folderId = btn.dataset.folderId;
                    this.confirmDeleteFolder(folderId);
                });
            });
        },

        // Navegar a una carpeta
        navigateToFolder: function(folderId, folderName) {
            this.currentFolderId = folderId;

            // Actualizar campo hidden del uploader
            const uploadFolderInput = document.getElementById('upload-folder-id');
            if (uploadFolderInput) {
                uploadFolderInput.value = folderId || '';
            }

            // Actualizar clase active en el árbol
            document.querySelectorAll('.folder-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`.folder-item[data-folder-id="${folderId}"]`)?.classList.add('active');

            // Actualizar breadcrumbs (DESHABILITADO - ya no se muestra el árbol en el header)
            // this.updateBreadcrumbs(folderId);

            // Recargar medios de esta carpeta
            if (window.loadMedia) {
                window.currentFolderId = folderId; // Exponer para el otro script
                window.loadMedia(1);
            }
        },

        // Actualizar breadcrumbs de navegación
        updateBreadcrumbs: function(folderId) {
            const breadcrumb = document.getElementById('folder-breadcrumb');
            if (!breadcrumb) return;

            let html = '<li class="breadcrumb-item"><a href="#" data-folder-id="">Raíz</a></li>';

            if (folderId) {
                const path = this.getFolderPath(folderId);
                path.forEach(folder => {
                    html += `<li class="breadcrumb-item"><a href="#" data-folder-id="${folder.id}">${this.escapeHtml(folder.name)}</a></li>`;
                });
            }

            breadcrumb.innerHTML = html;

            // Agregar eventos a los links del breadcrumb
            breadcrumb.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const fid = link.dataset.folderId;
                    const fname = link.textContent;
                    this.navigateToFolder(fid, fname);
                });
            });
        },

        // Obtener ruta completa de una carpeta
        getFolderPath: function(folderId) {
            const path = [];
            let currentId = folderId;

            while (currentId) {
                const folder = this.folders.find(f => f.id == currentId);
                if (!folder) break;

                path.unshift(folder);
                currentId = folder.parent_id;
            }

            return path;
        },

        // Mostrar diálogo para crear carpeta
        showCreateFolderDialog: function() {
            Swal.fire({
                title: 'Nueva Carpeta',
                html: `
                    <input id="swal-folder-name" class="swal2-input" placeholder="Nombre de la carpeta" value="">
                    <p class="text-muted small">Se creará en: ${this.getCurrentFolderName()}</p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Crear',
                cancelButtonText: 'Cancelar',
                focusConfirm: false,
                preConfirm: () => {
                    const name = document.getElementById('swal-folder-name').value;
                    if (!name || !name.trim()) {
                        Swal.showValidationMessage('El nombre no puede estar vacío');
                        return false;
                    }
                    return { name: name.trim() };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    this.createFolder(result.value.name);
                }
            });
        },

        // Crear carpeta en el servidor
        createFolder: function(name) {
            let url = window.MediaManagerConfig.createFolderUrl;
            if (!url || url.includes('#ruta-no-encontrada')) {
                url = '/musedock/media/folders/create';
            }
            const formData = new URLSearchParams();
            formData.append('name', name);
            formData.append('parent_id', this.currentFolderId || '');

            const csrfToken = document.querySelector('input[name="_token"]')?.value;
            if (csrfToken) formData.append('_token', csrfToken);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess(data.message || 'Carpeta creada exitosamente');
                    this.loadFolderStructure(); // Recargar árbol
                } else {
                    this.showError(data.message || 'Error al crear carpeta');
                }
            })
            .catch(error => {
                console.error('Error creating folder:', error);
                this.showError('Error de conexión al crear carpeta');
            });
        },

        // Mostrar diálogo para renombrar carpeta
        showRenameFolderDialog: function(folderId) {
            const folder = this.folders.find(f => f.id == folderId);
            if (!folder) return;

            Swal.fire({
                title: 'Renombrar Carpeta',
                input: 'text',
                inputValue: folder.name,
                inputPlaceholder: 'Nuevo nombre',
                showCancelButton: true,
                confirmButtonText: 'Renombrar',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'El nombre no puede estar vacío';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    this.renameFolder(folderId, result.value.trim());
                }
            });
        },

        // Renombrar carpeta en el servidor
        renameFolder: function(folderId, newName) {
            let url = window.MediaManagerConfig.renameFolderUrl;
            if (!url || url.includes('#ruta-no-encontrada')) {
                url = '/musedock/media/folders/:id/rename';
            }
            url = url.replace(':id', folderId);
            const formData = new URLSearchParams();
            formData.append('name', newName);

            const csrfToken = document.querySelector('input[name="_token"]')?.value;
            if (csrfToken) formData.append('_token', csrfToken);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Respuesta del servidor no es JSON válido');
                }
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.showSuccess(data.message || 'Carpeta renombrada exitosamente');
                    this.loadFolderStructure(); // Recargar árbol
                } else {
                    this.showError(data.message || 'Error al renombrar carpeta');
                }
            })
            .catch(error => {
                console.error('Error renaming folder:', error);
                this.showError('Error al renombrar carpeta: ' + error.message);
            });
        },

        // Confirmar eliminación de carpeta
        confirmDeleteFolder: function(folderId) {
            const folder = this.folders.find(f => f.id == folderId);
            if (!folder) return;

            Swal.fire({
                title: '¿Eliminar carpeta?',
                text: `¿Estás seguro de eliminar "${folder.name}"? Los archivos dentro se moverán a la carpeta raíz.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.deleteFolder(folderId);
                }
            });
        },

        // Eliminar carpeta en el servidor
        deleteFolder: function(folderId) {
            let url = window.MediaManagerConfig.deleteFolderUrl;
            if (!url || url.includes('#ruta-no-encontrada')) {
                url = '/musedock/media/folders/:id/delete';
            }
            url = url.replace(':id', folderId);
            const csrfToken = document.querySelector('input[name="_token"]')?.value;

            const formData = new URLSearchParams();
            formData.append('_token', csrfToken || '');

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Respuesta del servidor no es JSON válido');
                }
                // Parsear JSON incluso para errores 4xx para obtener el mensaje del servidor
                return response.json().then(data => {
                    if (!response.ok) {
                        // Usar el mensaje del servidor si está disponible
                        throw new Error(data.message || `HTTP Error: ${response.status}`);
                    }
                    return data;
                });
            })
            .then(data => {
                if (data.success) {
                    this.showSuccess(data.message || 'Carpeta eliminada exitosamente');

                    // Si estábamos en esta carpeta, volver a raíz
                    if (this.currentFolderId == folderId) {
                        this.navigateToFolder(null, 'Raíz');
                    }

                    this.loadFolderStructure(); // Recargar árbol
                } else {
                    this.showError(data.message || 'Error al eliminar carpeta');
                }
            })
            .catch(error => {
                console.error('Error deleting folder:', error);
                this.showError(error.message || 'Error al eliminar carpeta');
            });
        },

        // Obtener nombre de la carpeta actual
        getCurrentFolderName: function() {
            if (!this.currentFolderId) return 'Raíz';
            const folder = this.folders.find(f => f.id == this.currentFolderId);
            return folder ? folder.name : 'Raíz';
        },

        // Mover items a una carpeta (via drag and drop)
        moveItemsToFolder: function(ids, targetFolderId) {
            if (!ids || ids.length === 0) return;

            let url = '/musedock/media/move'; // Fallback URL

            const formData = new URLSearchParams();
            ids.forEach(id => {
                formData.append('items[]', JSON.stringify({ id: id, type: 'media' }));
            });
            formData.append('target_folder_id', targetFolderId);

            const csrfToken = document.querySelector('input[name="_token"]')?.value;
            if (csrfToken) formData.append('_token', csrfToken);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess(data.message || 'Archivo(s) movido(s) correctamente');
                    if (window.loadMedia) {
                        window.loadMedia(1); // Recargar medios
                    }
                    this.loadFolderStructure(); // Recargar árbol de carpetas
                } else {
                    this.showError(data.message || 'Error al mover archivo(s)');
                }
            })
            .catch(error => {
                console.error('Error moving items:', error);
                this.showError('Error de conexión al mover archivo(s)');
            });
        },

        // Utilidades
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        },

        showSuccess: function(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: message,
                    timer: 3000,
                    showConfirmButton: false
                });
            } else {
                alert(message);
            }
        },

        showError: function(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message
                });
            } else {
                alert('Error: ' + message);
            }
        }
    };

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.FolderManager.init());
    } else {
        window.FolderManager.init();
    }

})();
