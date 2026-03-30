/**
 * Folder Manager for Media Manager
 * Maneja todas las funciones relacionadas con carpetas
 * Navegacion estilo Plesk: breadcrumbs + boton volver
 */

(function() {
    'use strict';

    // Estado global de carpetas
    window.FolderManager = {
        currentFolderId: null,
        rootFolderId: null,
        folders: [],

        // Inicializar el gestor de carpetas
        init: function() {
            this.loadFolderStructure();
            this.setupEventListeners();
        },

        // Configurar eventos
        setupEventListeners: function() {
            // Boton crear carpeta (panel izquierdo)
            const btnCreateFolder = document.getElementById('btn-create-folder');
            if (btnCreateFolder) {
                btnCreateFolder.addEventListener('click', () => this.showCreateFolderDialog());
            }

            // Boton crear carpeta (toolbar)
            const btnCreateFolderNew = document.getElementById('btn-create-folder-new');
            if (btnCreateFolderNew) {
                btnCreateFolderNew.addEventListener('click', () => this.showCreateFolderDialog());
            }

            // Boton volver atras
            const btnBack = document.getElementById('btn-folder-back');
            if (btnBack) {
                btnBack.addEventListener('click', () => this.goBack());
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
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Respuesta del servidor no es JSON valido');
                }
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && Array.isArray(data.folders)) {
                    this.folders = data.folders;

                    // Identificar root folder
                    const rootFolder = data.folders.find(f => f.path === '/' || f.parent_id === null);
                    this.rootFolderId = rootFolder ? rootFolder.id : null;

                    this.renderFolderTree(data.folders);
                    this.updateBreadcrumbs();
                } else {
                    console.error('Error al cargar carpetas:', data.message || 'Respuesta invalida');
                    this.showError(data.message || 'No se pudieron cargar las carpetas');
                }
            })
            .catch(error => {
                console.error('Error fetching folders:', error);
                this.showError('Error al cargar carpetas: ' + error.message);
            });
        },

        // Renderizar arbol de carpetas en el panel izquierdo
        renderFolderTree: function(folders) {
            const container = document.getElementById('folder-tree-container');
            if (!container) return;

            // Construir el arbol excluyendo el root folder
            const nonRootFolders = this.rootFolderId
                ? folders.filter(f => f.id != this.rootFolderId)
                : folders.filter(f => f.path !== '/');
            const tree = this.buildTree(nonRootFolders);

            // Enlace "Todos los archivos" al inicio
            const isRootActive = !this.currentFolderId ? ' active' : '';
            let rootHtml = `
                <div class="folder-item folder-root${isRootActive}" data-folder-id="" data-folder-name="Raiz">
                    <i class="bi bi-house-door-fill folder-icon"></i>
                    <span class="folder-name">Todos los archivos</span>
                </div>
            `;

            const treeHtml = this.renderTreeHTML(tree);
            container.innerHTML = rootHtml + (tree.length > 0 ? treeHtml : '');

            this.attachFolderEvents();
        },

        // Construir estructura de arbol jerarquico
        buildTree: function(folders, parentId = null) {
            return folders
                .filter(folder => {
                    if (parentId === null) {
                        // Top-level: folders with no parent, or whose parent is the root folder
                        return folder.parent_id === null || folder.parent_id == this.rootFolderId;
                    }
                    return folder.parent_id == parentId;
                })
                .map(folder => ({
                    ...folder,
                    children: this.buildTree(folders, folder.id)
                }));
        },

        // Renderizar HTML del arbol
        renderTreeHTML: function(nodes, level = 0) {
            if (!nodes || nodes.length === 0) {
                return '';
            }

            let html = '<ul class="folder-tree' + (level > 0 ? ' folder-children' : '') + '">';

            nodes.forEach(node => {
                const isActive = this.currentFolderId && node.id == this.currentFolderId ? ' active' : '';
                const hasChildren = node.children && node.children.length > 0;

                html += `
                    <li>
                        <div class="folder-item${isActive}" data-folder-id="${node.id}" data-folder-name="${this.escapeHtml(node.name)}">
                            <i class="bi bi-folder-fill folder-icon"></i>
                            <span class="folder-name">${this.escapeHtml(node.name)}</span>
                            <div class="folder-actions">
                                <button class="btn-empty-folder" title="Vaciar carpeta" data-folder-id="${node.id}">
                                    <i class="bi bi-trash3"></i>
                                </button>
                                <button class="btn-rename-folder" title="Renombrar" data-folder-id="${node.id}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-delete-folder" title="Eliminar carpeta" data-folder-id="${node.id}">
                                    <i class="bi bi-folder-x"></i>
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
                    if (e.target.closest('.folder-actions')) return;

                    const folderId = item.dataset.folderId;
                    const folderName = item.dataset.folderName;
                    this.navigateToFolder(folderId, folderName);
                });

                // Drag and drop
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
                    if (btn.disabled) return;
                    this.showRenameFolderDialog(btn.dataset.folderId);
                });
            });

            // Botones eliminar
            document.querySelectorAll('.btn-delete-folder').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (btn.disabled) return;
                    this.confirmDeleteFolder(btn.dataset.folderId);
                });
            });

            // Botones vaciar carpeta
            document.querySelectorAll('.btn-empty-folder').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (btn.disabled) return;
                    this.confirmEmptyFolder(btn.dataset.folderId);
                });
            });
        },

        // Navegar a una carpeta
        navigateToFolder: function(folderId, folderName) {
            this.currentFolderId = folderId || null;

            // Actualizar campo hidden del uploader
            const uploadFolderInput = document.getElementById('upload-folder-id');
            if (uploadFolderInput) {
                uploadFolderInput.value = folderId || '';
            }

            // Actualizar clase active en el arbol
            document.querySelectorAll('.folder-item').forEach(item => {
                item.classList.remove('active');
            });

            if (folderId) {
                document.querySelector(`.folder-item[data-folder-id="${folderId}"]`)?.classList.add('active');
            } else {
                document.querySelector('.folder-item.folder-root')?.classList.add('active');
            }

            // Actualizar breadcrumbs y boton volver
            this.updateBreadcrumbs();

            // Recargar medios de esta carpeta
            if (window.loadMedia) {
                window.currentFolderId = folderId || null;
                window.loadMedia(1);
            }
        },

        // Volver a la carpeta padre
        goBack: function() {
            if (!this.currentFolderId) return;

            const currentFolder = this.folders.find(f => f.id == this.currentFolderId);
            if (!currentFolder) {
                this.navigateToFolder(null, 'Raiz');
                return;
            }

            // Si el padre es el root, volver a raiz (null)
            if (!currentFolder.parent_id || currentFolder.parent_id == this.rootFolderId) {
                this.navigateToFolder(null, 'Raiz');
            } else {
                const parentFolder = this.folders.find(f => f.id == currentFolder.parent_id);
                this.navigateToFolder(currentFolder.parent_id, parentFolder ? parentFolder.name : 'Raiz');
            }
        },

        // Actualizar breadcrumbs y boton volver
        updateBreadcrumbs: function() {
            const breadcrumb = document.getElementById('folder-breadcrumb');
            const btnBack = document.getElementById('btn-folder-back');

            if (!breadcrumb) return;

            let html = '';

            if (!this.currentFolderId) {
                // En raiz - sin enlace, texto activo
                html = '<span class="breadcrumb-segment active"><i class="bi bi-folder-fill text-warning me-1"></i>Raíz</span>';
                if (btnBack) btnBack.style.display = 'none';
            } else {
                // Dentro de una carpeta: Raiz clickeable + ruta
                html = '<span class="breadcrumb-segment"><a href="#" data-folder-id=""><i class="bi bi-folder-fill text-warning me-1"></i>Raíz</a></span>';

                const path = this.getFolderPath(this.currentFolderId);

                path.forEach((folder, index) => {
                    // No mostrar el root folder del sistema
                    if (folder.path === '/' || folder.id == this.rootFolderId) return;

                    html += '<span class="breadcrumb-separator"><i class="bi bi-chevron-right"></i></span>';

                    if (index === path.length - 1) {
                        // Ultima carpeta: texto activo sin enlace
                        html += `<span class="breadcrumb-segment active">${this.escapeHtml(folder.name)}</span>`;
                    } else {
                        // Intermedia: clickeable
                        html += `<span class="breadcrumb-segment"><a href="#" data-folder-id="${folder.id}">${this.escapeHtml(folder.name)}</a></span>`;
                    }
                });

                if (btnBack) btnBack.style.display = 'inline-block';
            }

            breadcrumb.innerHTML = html;

            // Eventos click en los links del breadcrumb
            breadcrumb.querySelectorAll('a[data-folder-id]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const fid = link.dataset.folderId;
                    const fname = link.textContent.trim();
                    this.navigateToFolder(fid, fname);
                });
            });
        },

        // Obtener ruta completa de una carpeta (desde raiz hasta la carpeta)
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

        // Obtener nombre de la carpeta actual
        getCurrentFolderName: function() {
            if (!this.currentFolderId) return 'Raiz';
            const folder = this.folders.find(f => f.id == this.currentFolderId);
            return folder ? folder.name : 'Raiz';
        },

        // Mostrar dialogo para crear carpeta
        showCreateFolderDialog: function() {
            Swal.fire({
                title: 'Nueva Carpeta',
                html: `
                    <input id="swal-folder-name" class="swal2-input" placeholder="Nombre de la carpeta" value="">
                    <p class="text-muted small">Se creara en: ${this.getCurrentFolderName()}</p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Crear',
                cancelButtonText: 'Cancelar',
                focusConfirm: false,
                preConfirm: () => {
                    const name = document.getElementById('swal-folder-name').value;
                    if (!name || !name.trim()) {
                        Swal.showValidationMessage('El nombre no puede estar vacio');
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
                    this.loadFolderStructure();
                } else {
                    this.showError(data.message || 'Error al crear carpeta');
                }
            })
            .catch(error => {
                console.error('Error creating folder:', error);
                this.showError('Error de conexion al crear carpeta');
            });
        },

        // Mostrar dialogo para renombrar carpeta
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
                        return 'El nombre no puede estar vacio';
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
                    throw new Error('Respuesta del servidor no es JSON valido');
                }
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.showSuccess(data.message || 'Carpeta renombrada exitosamente');
                    this.loadFolderStructure();
                } else {
                    this.showError(data.message || 'Error al renombrar carpeta');
                }
            })
            .catch(error => {
                console.error('Error renaming folder:', error);
                this.showError('Error al renombrar carpeta: ' + error.message);
            });
        },

        // Confirmar eliminacion de carpeta
        confirmDeleteFolder: function(folderId) {
            const folder = this.folders.find(f => f.id == folderId);
            if (!folder) return;

            Swal.fire({
                title: 'Eliminar carpeta?',
                text: `Estas seguro de eliminar "${folder.name}"? Los archivos dentro se moveran a la carpeta raiz.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Si, eliminar',
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
                    throw new Error('Respuesta del servidor no es JSON valido');
                }
                return response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.message || `HTTP Error: ${response.status}`);
                    }
                    return data;
                });
            })
            .then(data => {
                if (data.success) {
                    this.showSuccess(data.message || 'Carpeta eliminada exitosamente');

                    // Si estabamos en esta carpeta, volver a raiz
                    if (this.currentFolderId == folderId) {
                        this.navigateToFolder(null, 'Raiz');
                    }

                    this.loadFolderStructure();
                } else {
                    this.showError(data.message || 'Error al eliminar carpeta');
                }
            })
            .catch(error => {
                console.error('Error deleting folder:', error);
                this.showError(error.message || 'Error al eliminar carpeta');
            });
        },

        // Confirmar vaciar carpeta (eliminar todos los archivos)
        confirmEmptyFolder: function(folderId) {
            const folder = this.folders.find(f => f.id == folderId);
            if (!folder) return;

            Swal.fire({
                title: 'Vaciar carpeta?',
                text: `Se eliminaran TODOS los archivos dentro de "${folder.name}". Esta accion no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Si, vaciar todo',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.emptyFolder(folderId);
                }
            });
        },

        // Vaciar carpeta en el servidor
        emptyFolder: function(folderId) {
            let url = window.MediaManagerConfig.emptyFolderUrl;
            if (!url || url.includes('#ruta-no-encontrada')) {
                // Fallback: usar deleteFolderUrl y cambiar /delete por /empty
                url = (window.MediaManagerConfig.deleteFolderUrl || '/musedock/media/folders/:id/delete').replace('/delete', '/empty');
            }
            url = url.replace(':id', folderId);
            const csrfToken = document.querySelector('input[name="_token"]')?.value;

            const formData = new URLSearchParams();
            formData.append('_token', csrfToken || '');

            // Mostrar loading
            Swal.fire({
                title: 'Eliminando archivos...',
                text: 'Por favor espera...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

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
                    throw new Error('Respuesta del servidor no es JSON valido');
                }
                return response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.message || `HTTP Error: ${response.status}`);
                    }
                    return data;
                });
            })
            .then(data => {
                Swal.close();
                if (data.success) {
                    this.showSuccess(data.message || 'Carpeta vaciada exitosamente');

                    // Recargar medios si estamos en esa carpeta
                    if (this.currentFolderId == folderId && window.loadMedia) {
                        window.loadMedia(1);
                    }
                } else {
                    this.showError(data.message || 'Error al vaciar carpeta');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error emptying folder:', error);
                this.showError(error.message || 'Error al vaciar carpeta');
            });
        },

        // Mover items a una carpeta (via drag and drop)
        moveItemsToFolder: function(ids, targetFolderId) {
            if (!ids || ids.length === 0) return;

            let url = '/musedock/media/move';

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
                        window.loadMedia(1);
                    }
                    this.loadFolderStructure();
                } else {
                    this.showError(data.message || 'Error al mover archivo(s)');
                }
            })
            .catch(error => {
                console.error('Error moving items:', error);
                this.showError('Error de conexion al mover archivo(s)');
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
                    title: 'Exito!',
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

    // Inicializar cuando el DOM este listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.FolderManager.init());
    } else {
        window.FolderManager.init();
    }

})();
