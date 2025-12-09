@extends('layouts.app')

@section('title', 'Editar Menú')

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Cabecera con botón de volver y datos del menú -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <h2 class="me-3 mb-0">Editar Menú</h2>
                <!-- Panel de edición del título del menú -->
                <div class="input-group me-4" style="max-width: 400px;">
                    <input type="text" id="menu-title" class="form-control" value="{{ $menu->title ?? 'Sin título' }}" readonly>
                    <button class="btn btn-outline-secondary toggle-edit" type="button">
                        <i class="bi bi-lock"></i>
                    </button>
                    <button class="btn btn-primary save-title d-none" type="button">
                        <i class="bi bi-check"></i> Guardar
                    </button>
                </div>
                
                <!-- Selector de idioma para el menú -->
                <div class="input-group" style="max-width: 180px;">
                    <select id="menu-locale" class="form-select" disabled>
                        @foreach ($languages as $language)
                            <option value="{{ $language->code }}" {{ $currentLocale == $language->code ? 'selected' : '' }}>
                                {{ $language->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-secondary toggle-locale" type="button">
                        <i class="bi bi-lock"></i>
                    </button>
                </div>
            </div>
            <a href="{{ route('menus.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al listado
            </a>
        </div>

        <div class="row">
            <!-- Panel izquierdo - Añadir enlaces -->
            <div class="col-md-4">
                <!-- Panel de selección de páginas -->
                <div class="card mb-3">
                    <div class="card-header">Añadir Páginas</div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="pageTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent" type="button">Más recientes</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">Ver todo</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="search-tab" data-bs-toggle="tab" data-bs-target="#search" type="button">Buscar</button>
                            </li>
                        </ul>

                        <div class="tab-content mt-3">
                            <div class="tab-pane fade show active" id="recent">
                                <div class="item-list-body">
                                    @foreach ($recentPages as $page)
                                        <div class="form-check">
                                            <input class="form-check-input page-select" type="checkbox" 
                                                value="{{ $page->id }}" 
                                                data-title="{{ $page->title }}" 
                                                data-page-id="{{ $page->id }}"
                                                data-link="/{{ $page->prefix }}/{{ $page->slug }}">
                                            <label class="form-check-label">{{ $page->title }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="tab-pane fade" id="all">
                                <div class="item-list-body">
                                    @foreach ($allPages as $page)
                                        <div class="form-check">
                                            <input class="form-check-input page-select" type="checkbox" 
                                                value="{{ $page->id }}" 
                                                data-title="{{ $page->title }}" 
                                                data-page-id="{{ $page->id }}"
                                                data-link="/{{ $page->prefix }}/{{ $page->slug }}">
                                            <label class="form-check-label">{{ $page->title }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="tab-pane fade" id="search">
                                <input type="text" id="searchInput" class="form-control mb-2" placeholder="Buscar página...">
                                <div id="searchResults" class="item-list-body"></div>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 mt-3" id="addSelectedPages">Añadir Páginas al Menú</button>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">Añadir Enlace Personalizado</div>
                    <div class="card-body">
                        <form id="addCustomLinkForm">
                            <div class="mb-3">
                                <label class="form-label">Texto del enlace</label>
                                <input type="text" id="customLinkTitle" class="form-control" placeholder="Ej: Inicio" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL personalizada</label>
                                <input type="text" id="customLinkUrl" class="form-control" placeholder="/ruta-o-url">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Añadir Enlace</button>
                        </form>
                    </div>
                </div>

                {{-- Panel de Blog (solo si el módulo está activo) --}}
                @if(isset($blogPosts) && count($blogPosts) > 0)
                <div class="card mb-3">
                    <div class="card-header">Añadir Posts del Blog</div>
                    <div class="card-body">
                        <div class="item-list-body" style="max-height: 200px; overflow-y: auto;">
                            @foreach ($blogPosts as $post)
                                <div class="form-check">
                                    <input class="form-check-input blog-post-select" type="checkbox"
                                        value="{{ $post->id }}"
                                        data-title="{{ $post->title }}"
                                        data-link="/blog/{{ $post->slug }}">
                                    <label class="form-check-label">{{ $post->title }}</label>
                                </div>
                            @endforeach
                        </div>
                        <button class="btn btn-primary w-100 mt-3" id="addSelectedPosts">Añadir Posts al Menú</button>
                    </div>
                </div>
                @endif

                {{-- Panel de Categorías del Blog --}}
                @if(isset($blogCategories) && count($blogCategories) > 0)
                <div class="card">
                    <div class="card-header">Añadir Categorías del Blog</div>
                    <div class="card-body">
                        <div class="item-list-body" style="max-height: 200px; overflow-y: auto;">
                            @foreach ($blogCategories as $category)
                                <div class="form-check">
                                    <input class="form-check-input blog-category-select" type="checkbox"
                                        value="{{ $category->id }}"
                                        data-title="{{ $category->name }}"
                                        data-link="/blog/categoria/{{ $category->slug }}">
                                    <label class="form-check-label">{{ $category->name }}</label>
                                </div>
                            @endforeach
                        </div>
                        <button class="btn btn-primary w-100 mt-3" id="addSelectedCategories">Añadir Categorías al Menú</button>
                    </div>
                </div>
                @endif
            </div>

            <!-- Panel derecho - Estructura del menú -->
            <div class="col-md-8">
                <div class="card">
					
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Estructura del Menú</span>
                            <div>
<select id="menu-location" class="form-select form-select-sm d-inline-block me-2" style="width: auto;">
  <option value="">Sin ubicación</option>
  @foreach ($menuAreas as $area)
    <option value="{{ $area['id'] }}" {{ $menu->location == $area['id'] ? 'selected' : '' }}>
      {{ $area['name'] }}
    </option>
  @endforeach
</select>
<div class="form-check form-check-inline ms-2">
  <input class="form-check-input" type="checkbox" id="show-title" {{ ($menu->show_title ?? 1) ? 'checked' : '' }}>
  <label class="form-check-label" for="show-title">Mostrar título</label>
</div>    
                            </div>
                        </div>
                    </div>
					
                    <div class="card-body">
                        <!-- Información -->
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Tip:</strong> Arrastra los elementos para ordenarlos. Mueve un elemento ligeramente a la derecha debajo de otro para crear un submenú.
                        </div>

                        <!-- Lista de menú -->
                        <ul id="menu-list" class="list-unstyled"></ul>

                        <!-- Mensaje si no hay elementos -->
                        <div id="empty-menu" class="text-center py-3">
                            <p class="text-muted">No hay elementos en el menú.</p>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="button" class="btn btn-outline-secondary" id="toggle-all">
                                <i class="bi bi-arrows-collapse"></i> Colapsar Todo
                            </button>
                            <button id="save-menu" class="btn btn-success">
                                <i class="bi bi-save me-1"></i> Guardar menú
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos básicos para el menú */
#menu-list {
    min-height: 20px;
}

#menu-list ul {
    list-style: none;
    padding-left: 30px;
    margin-top: 8px;
}

.menu-item {
    margin-bottom: 8px;
}

/* barra de cada elemento */
.menu-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 6px 12px;            /* más estrecha */
    cursor: move;
}

.menu-bar:hover {
    background-color: #e9ecef;
}

.menu-title {
    display: flex;
    align-items: center;
}

.menu-title .grip {
    color: #adb5bd;
    margin-right: 10px;
}

/* Flecha desplegable */
.toggle-children {
    border: none;
    background: transparent;
    padding: 0 6px 0 0;
    cursor: pointer;
}
.toggle-children i {
    transition: transform .2s;
    font-size: 14px;
}
.toggle-children.collapsed i {
    transform: rotate(0deg);
}
.toggle-children.expanded i {
    transform: rotate(90deg);
}

.menu-url {
    color: #6c757d;
    font-size: 0.875rem;
    margin-left: 8px;
}

.menu-actions button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0 5px;
}

.edit-btn {
    color: #0d6efd;
}

.delete-btn {
    color: #dc3545;
}

.menu-edit-panel {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 4px 4px;
    padding: 15px;
    margin-top: -1px;
    margin-bottom: 8px;
    display: none;
}

.child-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background-color: #0d6efd;
    border-radius: 50%;
    margin-right: 8px;
}

.placeholder {
    border: 2px dashed #0d6efd;
    background-color: #e6f2ff;
    height: 40px;
    margin: 8px 0;
    border-radius: 4px;
}

.actions-row {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
    gap: 8px;
}
	
/* Mejoras visuales para los botones */
#toggle-all {
    transition: all 0.3s ease;
}
#toggle-all.collapsed {
    background-color: #f8f9fa;
}
#toggle-all.expanded {
    background-color: #e9ecef;
}

/* Estilos para el panel de edición del título */
.toggle-edit, .toggle-locale {
    cursor: pointer;
}
.toggle-edit i, .toggle-locale i {
    transition: all 0.3s;
}
</style>

@endsection

@push('scripts')
<!-- jQuery UI y nestedSortable - Se cargan después de jQuery del layout -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="/vendor/nestedsortable/jquery.mjs.nestedSortable.js"></script>

<script>
// Verificar que nestedSortable se haya cargado correctamente
console.log('Verificando plugins...');
console.log('jQuery version:', $.fn.jquery);
console.log('jQuery UI:', typeof $.ui !== 'undefined' ? 'Cargado' : 'NO cargado');
console.log('nestedSortable:', typeof $.fn.nestedSortable !== 'undefined' ? 'Cargado' : 'NO cargado');

if (typeof $.fn.nestedSortable !== 'undefined') {
    console.log('✓ Plugin nestedSortable cargado exitosamente');
} else {
    console.error('✗ ERROR: Plugin nestedSortable NO está disponible');
}
</script>

<script>
function generateTempId() {
    return 'temp_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
}

const menuId = {{ $menu->id }};

$(document).ready(function() {
    // Contador para IDs temporales
    let tempIdCounter = 1;

    // ----------------------
    // Funcionalidad para editar el título
    // ----------------------
    
    $('.toggle-edit').click(function() {
        const $input = $('#menu-title');
        const $button = $(this);
        const $saveButton = $('.save-title');
        
        if ($input.prop('readonly')) {
            // Desbloquear
            $input.prop('readonly', false).focus();
            $button.html('<i class="bi bi-unlock"></i>');
            $saveButton.removeClass('d-none');
        } else {
            // Bloquear
            $input.prop('readonly', true);
            $button.html('<i class="bi bi-lock"></i>');
            $saveButton.addClass('d-none');
        }
    });
    
    $('.save-title').click(function() {
        const newTitle = $('#menu-title').val();
        const locale = $('#menu-locale').val();
        
        if (newTitle.trim() === '') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El título no puede estar vacío'
            });
            return;
        }
        
        // Guardar cambios
        $.ajax({
            url: `/musedock/menus/${menuId}/update`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            data: {
                title: newTitle,
                locale: locale,
                location: $('#menu-location').val()
            },
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cambios guardados',
                    text: 'Se ha actualizado el título del menú',
                    timer: 1500,
                    showConfirmButton: false
                });

                // Bloquear de nuevo
                $('#menu-title').prop('readonly', true);
                $('.toggle-edit').html('<i class="bi bi-lock"></i>');
                $('.save-title').addClass('d-none');
            },
            error: function(xhr) {
                console.error('Error al guardar título:', xhr);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.status === 419 ? 'Sesión expirada. Por favor, recargue la página.' : 'No se pudo guardar el título del menú'
                });
            }
        });
    });
    
    // ----------------------
    // Funcionalidad para cambiar el idioma
    // ----------------------

    $('.toggle-locale').click(function() {
        const $select = $('#menu-locale');
        const $button = $(this);

        if ($select.prop('disabled')) {
            // Desbloquear para permitir cambio
            $select.prop('disabled', false);
            $button.html('<i class="bi bi-unlock"></i>');
        } else {
            // Bloquear y guardar
            const newLocale = $select.val();
            const currentTitle = $('#menu-title').val();

            $select.prop('disabled', true);
            $button.html('<i class="bi bi-lock"></i>');

            // Guardar el cambio de idioma automáticamente
            $.ajax({
                url: `/musedock/menus/${menuId}/update`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                data: {
                    title: currentTitle,
                    locale: newLocale,
                    location: $('#menu-location').val()
                },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Idioma actualizado',
                        text: 'Recargando para mostrar el menú en el nuevo idioma...',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Recargar la página para mostrar el contenido en el nuevo idioma
                        window.location.reload();
                    });
                },
                error: function(xhr) {
                    console.error('Error al cambiar idioma:', xhr);
                    $select.prop('disabled', false);
                    $button.html('<i class="bi bi-unlock"></i>');

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.status === 419 ? 'Sesión expirada. Por favor, recargue la página.' : 'No se pudo cambiar el idioma del menú'
                    });
                }
            });
        }
    });
    
    // ----------------------
    // Actualización de ubicación
    // ----------------------

    $('#menu-location').change(function() {
        const newLocation = $(this).val();

        // Guardar el cambio de ubicación automáticamente
        $.ajax({
            url: `/musedock/menus/${menuId}/update`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            data: {
                title: $('#menu-title').val(),
                locale: $('#menu-locale').val(),
                location: newLocation,
                show_title: $('#show-title').is(':checked') ? 1 : 0
            },
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Ubicación actualizada',
                    text: 'Se ha cambiado la ubicación del menú',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            error: function(xhr) {
                console.error('Error al cambiar ubicación:', xhr);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.status === 419 ? 'Sesión expirada. Por favor, recargue la página.' : 'No se pudo cambiar la ubicación del menú'
                });
            }
        });
    });

    // ----------------------
    // Checkbox mostrar título
    // ----------------------

    $('#show-title').change(function() {
        const showTitle = $(this).is(':checked') ? 1 : 0;

        $.ajax({
            url: `/musedock/menus/${menuId}/update`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            data: {
                title: $('#menu-title').val(),
                locale: $('#menu-locale').val(),
                location: $('#menu-location').val(),
                show_title: showTitle
            },
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: showTitle ? 'Título visible' : 'Título oculto',
                    text: showTitle ? 'El título del menú se mostrará en el frontend' : 'El título del menú no se mostrará en el frontend',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            error: function(xhr) {
                console.error('Error al cambiar visibilidad del título:', xhr);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cambiar la visibilidad del título'
                });
            }
        });
    });

    // ----------------------
    // Añadir posts del blog
    // ----------------------

    $('#addSelectedPosts').on('click', function() {
        const checkboxes = $('.blog-post-select:checked');

        if (checkboxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Selección vacía',
                text: 'Por favor, selecciona al menos un post para añadir al menú.'
            });
            return;
        }

        checkboxes.each(function() {
            const title = $(this).data('title');
            const link = $(this).data('link');
            const newId = generateTempId();
            const newItem = addMenuItem(newId, title, link, false, false, 'blog_post', $(this).val());
            $('#menu-list').append(newItem);
            $(this).prop('checked', false);
        });

        checkEmptyMenu();
        initNestedSortable();

        Swal.fire({
            icon: 'success',
            title: 'Posts añadidos',
            text: 'Los posts seleccionados se han añadido al menú.',
            timer: 1500,
            showConfirmButton: false
        });
    });

    // ----------------------
    // Añadir categorías del blog
    // ----------------------

    $('#addSelectedCategories').on('click', function() {
        const checkboxes = $('.blog-category-select:checked');

        if (checkboxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Selección vacía',
                text: 'Por favor, selecciona al menos una categoría para añadir al menú.'
            });
            return;
        }

        checkboxes.each(function() {
            const title = $(this).data('title');
            const link = $(this).data('link');
            const newId = generateTempId();
            const newItem = addMenuItem(newId, title, link, false, false, 'blog_category', $(this).val());
            $('#menu-list').append(newItem);
            $(this).prop('checked', false);
        });

        checkEmptyMenu();
        initNestedSortable();

        Swal.fire({
            icon: 'success',
            title: 'Categorías añadidas',
            text: 'Las categorías seleccionadas se han añadido al menú.',
            timer: 1500,
            showConfirmButton: false
        });
    });

    // ----------------------
    // Funciones auxiliares
    // ----------------------

    function checkEmptyMenu() {
        if ($('#menu-list li').length > 0) {
            $('#empty-menu').hide();
        } else {
            $('#empty-menu').show();
        }
    }

    // Función para expandir todos los elementos
    function expandAllItems() {
        $('#menu-list li').each(function () {
            const $li = $(this);
            const $ulHijos = $li.children('ul');
            if ($ulHijos.length) {
                $ulHijos.show(); // Mostrar submenús
                $li.find('> .menu-bar .toggle-children')
                    .removeClass('collapsed')
                    .addClass('expanded');
            }
        });
    }

    // Función para colapsar todos los elementos
    function collapseAllItems() {
        $('#menu-list li').each(function () {
            const $li = $(this);
            const $ulHijos = $li.children('ul');
            if ($ulHijos.length) {
                $ulHijos.hide(); // Ocultar submenús
                $li.find('> .menu-bar .toggle-children')
                    .removeClass('expanded')
                    .addClass('collapsed');
            }
        });
    }

    // ----------------------
    // nestedSortable
    // ----------------------

    function initNestedSortable() {
        // Verificar que el plugin nestedSortable esté disponible
        if (typeof $.fn.nestedSortable === 'undefined') {
            console.error('ERROR: Plugin nestedSortable no está cargado');
            return;
        }

        // Destruir instancia anterior si existe
        try {
            $('#menu-list').nestedSortable('destroy');
        } catch(e) {
            // No hay instancia previa, continuar
        }

        // Inicializar nestedSortable
        $('#menu-list').nestedSortable({
            handle: '.menu-bar',
            items: 'li',
            toleranceElement: '> div.menu-bar',
            maxLevels: 3,
            placeholder: 'placeholder',
            forcePlaceholderSize: true,
            helper: 'clone',
            opacity: 0.6,
            tolerance: 'pointer',
            listType: 'ul',
            isTree: true,
            expandOnHover: 700,
            startCollapsed: false,   // Configurado para iniciar expandido
            relocate: function () {
                // Opcional: cualquier acción después de reorganizar
            }
        });

        console.log('nestedSortable inicializado correctamente');
    }

    // ----------------------
    // Generar HTML item menú
    // ----------------------

    function addMenuItem(id, title, url, isChild = false, hasChildren = false, type = 'custom', pageId = null) {
        const arrow = hasChildren ? `
        <button type="button" class="toggle-children expanded" title="Mostrar / ocultar subelementos">
            <i class="bi bi-chevron-right"></i>
        </button>` : '';

        return `
            <li class="menu-item" data-id="${id}" data-type="${type}" data-page-id="${pageId || ''}" data-title="${title}" data-link="${url}">
                <div class="menu-bar">
                    <div class="menu-title">
                        <i class="bi bi-grip-vertical grip"></i>
                        ${arrow}
                        ${isChild ? '<span class="child-indicator"></span>' : ''}
                        <span class="menu-text">${title}</span>
                        <span class="menu-url">${url}</span>
                    </div>
                    <div class="menu-actions">
                        <button type="button" class="edit-btn" title="Editar"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="delete-btn" title="Eliminar"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <div class="menu-edit-panel">
                    <div class="mb-3">
                        <label class="form-label">Texto del enlace</label>
                        <input type="text" class="form-control edit-title" value="${title}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL</label>
                        <input type="text" class="form-control edit-url" value="${url}">
                    </div>
                    <div class="actions-row">
                        <button type="button" class="btn btn-secondary btn-sm cancel-edit">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm save-edit">Guardar</button>
                    </div>
                </div>
            </li>`;
    }

    // ----------------------
    // Agregar páginas seleccionadas al menú
    // ----------------------
    $('#addSelectedPages').on('click', function() {
        const checkboxes = $('.page-select:checked');
        
        if (checkboxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Selección vacía',
                text: 'Por favor, selecciona al menos una página para añadir al menú.'
            });
            return;
        }
        
        // Recopilar todos los IDs
        const selectedIds = [];
        checkboxes.each(function() {
            selectedIds.push($(this).val());
        });
        
        // Llamar a la API para añadir páginas
        $.ajax({
            url: `/musedock/menus/add-pages`,
            method: 'GET',
            data: {
                menuid: menuId,
                ids: selectedIds.join(',') // Enviar como cadena separada por comas
            },
            success: function(response) {
                try {
                    console.log("Respuesta recibida:", response);
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result && result.success) {
                        // Agregar los elementos localmente
                        checkboxes.each(function() {
                            const pageId = $(this).data('page-id');
                            const title = $(this).data('title');
                            const link = $(this).data('link');
                            
                            // Crear nuevo elemento
                            const newId = generateTempId();
                            const newItem = addMenuItem(newId, title, link, false, false, 'page', pageId);
                            $('#menu-list').append(newItem);
                            
                            // Desmarcar checkbox
                            $(this).prop('checked', false);
                        });
                        
                        // Actualizar estado y re-inicializar sortable
                        checkEmptyMenu();
                        initNestedSortable();
                        expandAllItems();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Páginas añadidas',
                            text: 'Las páginas seleccionadas se han añadido al menú.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        throw new Error(result.error || 'Error al añadir las páginas');
                    }
                } catch (e) {
                    console.error('Error procesando respuesta:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: e.message || 'Ocurrió un error al procesar la respuesta del servidor'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error del servidor:', xhr.responseText);
                console.error('Estado:', status);
                console.error('Error:', error);
                
                let errorMessage = 'Hubo un problema al añadir las páginas al menú.';
                
                if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.error) {
                            errorMessage = response.error;
                        }
                    } catch (e) {
                        // Si no se puede analizar la respuesta como JSON, usar el texto tal cual
                        if (xhr.responseText.length < 100) {
                            errorMessage = xhr.responseText;
                        }
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage
                });
            }
        });
    });

    // ----------------------
    // Buscar páginas en tiempo real
    // ----------------------

    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        
        if (searchTerm.length < 2) {
            $('#searchResults').html('<p class="text-muted">Escribe al menos 2 caracteres para buscar...</p>');
            return;
        }
        
        // Buscar entre todas las páginas
        let results = [];
        
        $('#all .form-check').each(function() {
            const title = $(this).find('.form-check-label').text().toLowerCase();
            if (title.includes(searchTerm)) {
                results.push($(this).clone());
            }
        });
        
        // Mostrar resultados
        $('#searchResults').empty();
        
        if (results.length > 0) {
            results.forEach(function(result) {
                $('#searchResults').append(result);
            });
        } else {
            $('#searchResults').html('<p class="text-muted">No se encontraron resultados.</p>');
        }
    });
        
    // ----------------------
    // Alta de nuevo enlace
    // ----------------------

    $('#addCustomLinkForm').on('submit', function (e) {
        e.preventDefault();

        const linkText = $('#customLinkTitle').val().trim();
        const linkUrl = $('#customLinkUrl').val().trim();

        if (linkText && linkUrl) {
            // Usar AJAX para añadir el enlace personalizado a través del controlador
            $.ajax({
                url: `/musedock/menus/add-custom?menuid=${menuId}&link=${encodeURIComponent(linkText)}&url=${encodeURIComponent(linkUrl)}`,
                method: 'GET',
                success: function(response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (result && result.success) {
                            // Añadir item al árbol de menú
                            const newId = generateTempId();
                            const newItem = addMenuItem(newId, linkText, linkUrl, false, false, 'custom', null);
                            $('#menu-list').append(newItem);
                            
                            // Limpiar formulario
                            $('#customLinkTitle').val('');
                            $('#customLinkUrl').val('');
                            
                            // Actualizar estado
                            checkEmptyMenu();
                            initNestedSortable();
                            
                            // Mostrar éxito
                            Swal.fire({
                                icon: 'success',
                                title: 'Enlace añadido',
                                text: 'El enlace se ha añadido correctamente al menú.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            throw new Error(result.error || 'Error al añadir el enlace');
                        }
                    } catch (e) {
                        console.error('Error procesando respuesta:', e);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: e.message
                        });
                    }
                },
                error: function (xhr) {
                    console.error('Error del servidor:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al añadir el enlace.'
                    });
                }
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Datos incompletos',
                text: 'Por favor, ingresa tanto el texto como la URL del enlace.'
            });
        }
    });

    // ----------------------
    // Botón editar / guardar / cancelar
    // ----------------------

    $(document).on('click', '.edit-btn', function (e) {
        e.preventDefault();
        $('.menu-edit-panel').not($(this).closest('li').find('.menu-edit-panel')).slideUp(200);
        $(this).closest('li').find('.menu-edit-panel').slideToggle(200);
    });

    $(document).on('click', '.cancel-edit', function () {
        $(this).closest('.menu-edit-panel').slideUp(200);
    });

    $(document).on('click', '.save-edit', function () {
        const $item = $(this).closest('.menu-item');
        const $panel = $(this).closest('.menu-edit-panel');
        const newTitle = $panel.find('.edit-title').val().trim();
        const newUrl = $panel.find('.edit-url').val().trim();
        
        // Solo actualizar el elemento actual, no sus hijos
        $item.find('> .menu-bar .menu-text').text(newTitle);
        $item.find('> .menu-bar .menu-url').text(newUrl);
        
        // Actualizar los atributos data
        $item.attr('data-title', newTitle);
        $item.attr('data-link', newUrl);
        
        $panel.slideUp(200);
    });

    // ----------------------
    // Eliminar elemento
    // ----------------------

    $(document).on('click', '.delete-btn', function () {
        const $item = $(this).closest('.menu-item');
        const itemTitle = $item.find('> .menu-bar .menu-text').text();
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: `¿Quieres eliminar "${itemTitle}" del menú?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $item.fadeOut(300, function () {
                    $(this).remove();
                    checkEmptyMenu();
                });
            }
        });
    });

    // ----------------------
    // Flecha desplegable
    // ----------------------

    $(document).on('click', '.toggle-children', function (e) {
        e.stopPropagation();
        const $btn = $(this);
        const $item = $btn.closest('li');
        const $childList = $item.children('ul');

        if ($btn.hasClass('collapsed')) {
            $childList.slideDown(150);
            $btn.removeClass('collapsed').addClass('expanded');
        } else {
            $childList.slideUp(150);
            $btn.removeClass('expanded').addClass('collapsed');
        }
    });

    // ----------------------
    // Botón expandir/colapsar todo
    // ----------------------
    
    $('#toggle-all').on('click', function() {
        const allExpanded = $('#menu-list ul').is(':visible');
        
        if (allExpanded) {
            // Si están expandidos, colapsar todos
            collapseAllItems();
            $(this).html('<i class="bi bi-arrows-expand"></i> Expandir Todo');
            $(this).removeClass('expanded').addClass('collapsed');
        } else {
            // Si están colapsados, expandir todos
            expandAllItems();
            $(this).html('<i class="bi bi-arrows-collapse"></i> Colapsar Todo');
            $(this).removeClass('collapsed').addClass('expanded');
        }
    });

    // ----------------------
    // Guardar menú en base de datos (real AJAX)
    // ----------------------

    $('#save-menu').on('click', function () {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass"></i> Guardando...');

        // Función recursiva para construir la estructura
        function buildStructure(container) {
            const items = [];
            
            $(container).children('li.menu-item').each(function (index) {
                const $item = $(this);
                const id = $item.data('id');
                const title = $item.find('> .menu-bar .menu-text').first().text();
                const url = $item.find('> .menu-bar .menu-url').first().text();
                const type = $item.data('type') || 'custom';
                const pageId = $item.data('page-id') || null;
                
                const itemData = {
                    id: id,
                    title: title,
                    url: url,
                    link: url, // Para compatibilidad con el backend
                    sort: index, // Explícitamente enviamos el índice como sort
                    type: type,
                    page_id: pageId,
                    children: []
                };
                
                // Procesar elementos hijos si existen
                const $childList = $item.children('ul');
                if ($childList.length > 0) {
                    itemData.children = buildStructure($childList);
                }
                
                items.push(itemData);
            });
            
            return items;
        }
        
        // Construir la estructura del menú
        const menuData = buildStructure('#menu-list');
        
        console.log('Enviando al servidor:', menuData);

        // Debug: Mostrar la estructura exacta que se enviará
        console.log('JSON a enviar:', JSON.stringify(menuData));

        $.ajax({
            url: '/musedock/menus/' + menuId + '/update-items',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            data: {
                menu: JSON.stringify(menuData) // Enviamos como string JSON para evitar problemas de formato
            },
            success: function (response) {
                console.log('Respuesta del servidor:', response);
                
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result && result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Menú guardado!',
                            text: 'La estructura del menú se ha actualizado correctamente.',
                            confirmButtonColor: '#3085d6',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Recargar la página después de guardar exitosamente
                            // Esto asegura que todos los IDs temporales se actualicen
                            location.reload();
                        });
                    } else {
                        throw new Error(result.error || 'La respuesta no indica éxito');
                    }
                } catch (e) {
                    console.error('Error procesando respuesta:', e);
                    Swal.fire({
                        icon: 'warning',
                        title: 'Respuesta inesperada',
                        text: e.message || 'El servidor ha respondido, pero no reconocemos el formato.',
                        confirmButtonColor: '#f8bb86'
                    });
                    $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Guardar menú');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error del servidor:', xhr.responseText);
                console.error('Estado HTTP:', status);
                console.error('Error:', error);
                
                let errorMessage = 'Hubo un problema al guardar el menú.';
                
                if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.error) {
                            errorMessage = response.error;
                        }
                    } catch (e) {
                        // Si no podemos analizar la respuesta JSON, usamos el texto tal cual
                        errorMessage = xhr.responseText;
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
                    confirmButtonColor: '#d33'
                });
                
                $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Guardar menú');
            }
        });
    });

    // ----------------------
    // Cargar datos Blade (si existen)
    // ----------------------

    @if(isset($items) && count($items) > 0)
        @php
            $parentItems = [];
            $childItems = [];
            $grandchildItems = [];
            
            foreach ($items as $dbItem) {
                if ($dbItem['parent'] === null) {
                    $parentItems[] = $dbItem;
                } else {
                    if (!isset($childItems[$dbItem['parent']])) {
                        $childItems[$dbItem['parent']] = [];
                    }
                    $childItems[$dbItem['parent']][] = $dbItem;
                    
                    // También registramos este elemento como posible padre de nietos
                    if (!isset($grandchildItems[$dbItem['id']])) {
                        $grandchildItems[$dbItem['id']] = [];
                    }
                }
            }
            
            // Segunda pasada para identificar nietos
            foreach ($items as $dbItem) {
                if ($dbItem['parent'] !== null && isset($childItems[$dbItem['parent']])) {
                    // Verificar si este elemento tiene un padre que es hijo de otro
                    foreach ($childItems as $parentId => $children) {
                        foreach ($children as $child) {
                            if ($dbItem['parent'] == $child['id']) {
                                if (!isset($grandchildItems[$child['id']])) {
                                    $grandchildItems[$child['id']] = [];
                                }
                                $grandchildItems[$child['id']][] = $dbItem;
                            }
                        }
                    }
                }
            }
        @endphp
        
        @foreach($parentItems as $parent)
            $('#menu-list').append(addMenuItem(
                '{{ $parent["id"] }}', 
                '{{ $parent["title"] }}', 
                '{{ $parent["link"] }}', 
                false, 
                {{ isset($childItems[$parent["id"]]) && !empty($childItems[$parent["id"]]) ? 'true' : 'false' }},
                '{{ $parent["type"] ?? "custom" }}',
                {{ $parent["page_id"] ?? 'null' }}
            ));
            
            @if(isset($childItems[$parent["id"]]) && !empty($childItems[$parent["id"]]))
                var $parent = $('#menu-list').find('li[data-id="{{ $parent["id"] }}"]');
                if ($parent.length) {
                    $parent.append('<ul></ul>');
                    
                    @foreach($childItems[$parent["id"]] as $child)
                        $parent.find('> ul').append(addMenuItem(
                            '{{ $child["id"] }}', 
                            '{{ $child["title"] }}', 
                            '{{ $child["link"] }}', 
                            true, 
                            {{ isset($grandchildItems[$child["id"]]) && !empty($grandchildItems[$child["id"]]) ? 'true' : 'false' }},
                            '{{ $child["type"] ?? "custom" }}',
                            {{ $child["page_id"] ?? 'null' }}
                        ));
                        
                        @if(isset($grandchildItems[$child["id"]]) && !empty($grandchildItems[$child["id"]]))
                            var $child = $parent.find('> ul > li[data-id="{{ $child["id"] }}"]');
                            if ($child.length) {
                                $child.append('<ul></ul>');
                                
                                @foreach($grandchildItems[$child["id"]] as $grandchild)
                                    $child.find('> ul').append(addMenuItem(
                                        '{{ $grandchild["id"] }}', 
                                        '{{ $grandchild["title"] }}', 
                                        '{{ $grandchild["link"] }}', 
                                        true, 
                                        false,
                                        '{{ $grandchild["type"] ?? "custom" }}',
                                        {{ $grandchild["page_id"] ?? 'null' }}
                                    ));
                                @endforeach
                            }
                        @endif
                    @endforeach
                }
            @endif
        @endforeach
        
        checkEmptyMenu();
    @else
        // No hay elementos para mostrar
    @endif
    
    // ----------------------
    // Init
    // ----------------------

    initNestedSortable();
    expandAllItems(); // Iniciar con todo expandido
});
</script>
@endpush