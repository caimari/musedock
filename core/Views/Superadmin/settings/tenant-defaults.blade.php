@extends('layouts.app')

@section('title', $title)

@push('styles')
<style>
.permission-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.5rem;
}
.permission-item {
    padding: 0.5rem;
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
    transition: all 0.2s;
}
.permission-item:hover {
    background-color: #f8f9fa;
}
.permission-item input:checked + label {
    color: #0d6efd;
    font-weight: 500;
}
.role-card {
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
}
.role-card .btn-remove-role {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}
.select-actions {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.menu-tree {
    list-style: none;
    padding-left: 0;
}
.menu-tree .menu-tree {
    padding-left: 2rem;
    margin-top: 0.5rem;
}
.menu-item {
    padding: 0.5rem;
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
    margin-bottom: 0.5rem;
    transition: all 0.2s;
}
.menu-item:hover {
    background-color: #f8f9fa;
}
.menu-item.has-children {
    border-left: 3px solid #0d6efd;
}
.menu-item input:checked + label {
    color: #0d6efd;
    font-weight: 500;
}
.menu-icon {
    width: 24px;
    display: inline-block;
    text-align: center;
}
.module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}
.module-card {
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    padding: 1rem;
    transition: all 0.2s;
}
.module-card:hover {
    border-color: #0d6efd;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.module-card.active {
    border-color: #198754;
    background-color: rgba(25, 135, 84, 0.05);
}
.module-card .module-icon {
    width: 40px;
    height: 40px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 1.25rem;
}
.module-card .form-switch {
    margin-left: auto;
}
</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            <i class="bi bi-gear-wide-connected me-2"></i>
            Configuración de Nuevos Tenants
        </h3>
    </div>

    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle me-2"></i>
            Esta configuración se aplicará automáticamente cada vez que se cree un nuevo tenant, ya sea desde el panel de Tenants o desde el Domain Manager.
        </div>

        <form id="tenantDefaultsForm" method="POST" action="{{ route('tenant-defaults.update') }}">
            {!! csrf_field() !!}

            <!-- Opciones Generales -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Opciones Generales</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tema por defecto</label>
                                <select name="default_theme" class="form-select">
                                    <option value="default" {{ ($settings['default_theme'] ?? 'default') === 'default' ? 'selected' : '' }}>Default</option>
                                    <option value="starter" {{ ($settings['default_theme'] ?? '') === 'starter' ? 'selected' : '' }}>Starter</option>
                                    <option value="starter-developer" {{ ($settings['default_theme'] ?? '') === 'starter-developer' ? 'selected' : '' }}>Starter Developer</option>
                                </select>
                                <small class="text-muted">Tema que se asignará a los nuevos tenants</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="copy_menus_from_admin" id="copyMenus"
                                           {{ ($settings['copy_menus_from_admin'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="copyMenus">
                                        Copiar menús desde admin_menus
                                    </label>
                                </div>
                                <small class="text-muted">Copia la estructura del menú del superadmin al nuevo tenant</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="assign_admin_role_to_creator" id="assignRole"
                                       {{ ($settings['assign_admin_role_to_creator'] ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="assignRole">
                                    Asignar rol Admin al creador del tenant
                                </label>
                            </div>
                            <small class="text-muted">El usuario administrador creado tendrá el rol Admin automáticamente</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Módulos por Defecto -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-puzzle me-2"></i>Módulos Activos por Defecto</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Selecciona los módulos que estarán <strong>activos por defecto</strong> para los nuevos tenants.
                        Los módulos desactivados no aparecerán en el sidebar ni estarán disponibles.
                    </p>

                    <div class="select-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllModules">
                            <i class="bi bi-check-all me-1"></i>Activar todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllModules">
                            <i class="bi bi-x-lg me-1"></i>Desactivar todos
                        </button>
                    </div>

                    <div class="module-grid">
                        @foreach($allModules as $module)
                        <div class="module-card {{ in_array($module['id'], $selectedModules) ? 'active' : '' }}" data-module-id="{{ $module['id'] }}">
                            <div class="d-flex align-items-start">
                                <div class="module-icon me-3">
                                    <i class="bi bi-puzzle"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">{{ $module['name'] }}</h6>
                                    <small class="text-muted">{{ $module['slug'] }}</small>
                                    @if($module['description'])
                                    <p class="mb-0 mt-1 small text-muted">{{ str_limit($module['description'], 60) }}</p>
                                    @endif
                                </div>
                                <div class="form-check form-switch ms-2">
                                    <input class="form-check-input module-checkbox" type="checkbox"
                                           name="modules[]" value="{{ $module['id'] }}" id="module_{{ $module['id'] }}"
                                           {{ in_array($module['id'], $selectedModules) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <span class="badge bg-success" id="moduleCount">{{ count($selectedModules) }}</span>
                        <span class="text-muted">módulos activos por defecto</span>
                    </div>
                </div>
            </div>

            <!-- Permisos por Defecto -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Permisos por Defecto para Rol Admin</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Selecciona los permisos que se asignarán al rol Admin de cada nuevo tenant.
                    </p>

                    <div class="select-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">
                            <i class="bi bi-check-all me-1"></i>Seleccionar todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">
                            <i class="bi bi-x-lg me-1"></i>Deseleccionar todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" id="selectViewOnly">
                            <i class="bi bi-eye me-1"></i>Solo lectura
                        </button>
                    </div>

                    <div class="permission-grid">
                        @foreach($allPermissions as $slug => $name)
                        <div class="permission-item">
                            <div class="form-check">
                                <input class="form-check-input permission-checkbox" type="checkbox"
                                       name="permissions[]" value="{{ $slug }}" id="perm_{{ $slug }}"
                                       data-is-view="{{ str_starts_with($slug, 'view_') ? '1' : '0' }}"
                                       {{ in_array($slug, $selectedPermissions) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_{{ $slug }}">
                                    {{ $name }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <span class="badge bg-primary" id="permissionCount">{{ count($selectedPermissions) }}</span>
                        <span class="text-muted">permisos seleccionados</span>
                    </div>
                </div>
            </div>

            <!-- Menús por Defecto -->
            <div class="card mb-4" id="menusSection" style="display: {{ ($settings['copy_menus_from_admin'] ?? true) ? 'block' : 'none' }};">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-list-nested me-2"></i>Menús a Copiar</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Selecciona los menús de admin_menus que se copiarán a los nuevos tenants.
                        Los menús no seleccionados no estarán disponibles para los tenants.
                    </p>

                    <div class="select-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllMenus">
                            <i class="bi bi-check-all me-1"></i>Seleccionar todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllMenus">
                            <i class="bi bi-x-lg me-1"></i>Deseleccionar todos
                        </button>
                    </div>

                    <ul class="menu-tree">
                        @foreach($allMenus as $menu)
                        <li>
                            <div class="menu-item {{ !empty($menu['children']) ? 'has-children' : '' }}">
                                <div class="form-check">
                                    <input class="form-check-input menu-checkbox" type="checkbox"
                                           name="menus[]" value="{{ $menu['slug'] }}" id="menu_{{ $menu['slug'] }}"
                                           data-has-children="{{ !empty($menu['children']) ? '1' : '0' }}"
                                           {{ in_array($menu['slug'], $selectedMenus) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="menu_{{ $menu['slug'] }}">
                                        <span class="menu-icon">
                                            @php
                                                $iconType = $menu['icon_type'] ?? 'bi';
                                                $icon = $menu['icon'] ?? 'circle';
                                                $iconClass = $iconType === 'bi' ? "bi bi-{$icon}" : "{$iconType} fa-{$icon}";
                                            @endphp
                                            <i class="{{ $iconClass }}"></i>
                                        </span>
                                        {{ $menu['title'] }}
                                        <small class="text-muted">({{ $menu['slug'] }})</small>
                                    </label>
                                </div>
                            </div>
                            @if(!empty($menu['children']))
                            <ul class="menu-tree">
                                @foreach($menu['children'] as $child)
                                <li>
                                    <div class="menu-item">
                                        <div class="form-check">
                                            <input class="form-check-input menu-checkbox" type="checkbox"
                                                   name="menus[]" value="{{ $child['slug'] }}" id="menu_{{ $child['slug'] }}"
                                                   data-parent="{{ $menu['slug'] }}"
                                                   {{ in_array($child['slug'], $selectedMenus) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="menu_{{ $child['slug'] }}">
                                                <span class="menu-icon">
                                                    @php
                                                        $cIconType = $child['icon_type'] ?? 'bi';
                                                        $cIcon = $child['icon'] ?? 'circle';
                                                        $cIconClass = $cIconType === 'bi' ? "bi bi-{$cIcon}" : "{$cIconType} fa-{$cIcon}";
                                                    @endphp
                                                    <i class="{{ $cIconClass }}"></i>
                                                </span>
                                                {{ $child['title'] }}
                                                <small class="text-muted">({{ $child['slug'] }})</small>
                                            </label>
                                        </div>
                                    </div>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                        </li>
                        @endforeach
                    </ul>

                    <div class="mt-3">
                        <span class="badge bg-info" id="menuCount">{{ count($selectedMenus) }}</span>
                        <span class="text-muted">menús seleccionados</span>
                    </div>
                </div>
            </div>

            <!-- Roles por Defecto -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Roles por Defecto</h5>
                    <button type="button" class="btn btn-sm btn-success" id="addRole">
                        <i class="bi bi-plus-lg me-1"></i>Añadir Rol
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Define los roles que se crearán automáticamente para cada nuevo tenant.
                        <strong>Importante:</strong> El rol con slug "admin" recibirá todos los permisos seleccionados arriba.
                    </p>

                    <div id="rolesContainer">
                        @foreach($defaultRoles as $index => $role)
                        <div class="role-card" data-index="{{ $index }}">
                            @if($role['slug'] !== 'admin')
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-role">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endif
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre</label>
                                        <input type="text" class="form-control" name="role_name[]"
                                               value="{{ $role['name'] }}" required
                                               {{ $role['slug'] === 'admin' ? 'readonly' : '' }}>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Slug</label>
                                        <input type="text" class="form-control" name="role_slug[]"
                                               value="{{ $role['slug'] }}" required
                                               {{ $role['slug'] === 'admin' ? 'readonly' : '' }}>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="mb-3">
                                        <label class="form-label">Descripción</label>
                                        <input type="text" class="form-control" name="role_description[]"
                                               value="{{ $role['description'] ?? '' }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Botón Guardar -->
            <div class="d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary" id="btnSave">
                    <span class="btn-text">
                        <i class="bi bi-check-lg me-1"></i>Guardar Configuración
                    </span>
                    <span class="btn-loading d-none">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Guardando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('tenantDefaultsForm');
    const btnSave = document.getElementById('btnSave');
    const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
    const permissionCount = document.getElementById('permissionCount');
    const rolesContainer = document.getElementById('rolesContainer');

    // Actualizar contador de permisos
    function updatePermissionCount() {
        const checked = document.querySelectorAll('.permission-checkbox:checked').length;
        permissionCount.textContent = checked;
    }

    permissionCheckboxes.forEach(cb => {
        cb.addEventListener('change', updatePermissionCount);
    });

    // Seleccionar todos los permisos
    document.getElementById('selectAll').addEventListener('click', function() {
        permissionCheckboxes.forEach(cb => cb.checked = true);
        updatePermissionCount();
    });

    // Deseleccionar todos los permisos
    document.getElementById('deselectAll').addEventListener('click', function() {
        permissionCheckboxes.forEach(cb => cb.checked = false);
        updatePermissionCount();
    });

    // Seleccionar solo permisos de lectura
    document.getElementById('selectViewOnly').addEventListener('click', function() {
        permissionCheckboxes.forEach(cb => {
            cb.checked = cb.dataset.isView === '1';
        });
        updatePermissionCount();
    });

    // Módulos - Activar/desactivar
    const moduleCheckboxes = document.querySelectorAll('.module-checkbox');
    const moduleCount = document.getElementById('moduleCount');

    function updateModuleCount() {
        const checked = document.querySelectorAll('.module-checkbox:checked').length;
        moduleCount.textContent = checked;
    }

    moduleCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const card = this.closest('.module-card');
            if (this.checked) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
            updateModuleCount();
        });
    });

    document.getElementById('selectAllModules').addEventListener('click', function() {
        moduleCheckboxes.forEach(cb => {
            cb.checked = true;
            cb.closest('.module-card').classList.add('active');
        });
        updateModuleCount();
    });

    document.getElementById('deselectAllModules').addEventListener('click', function() {
        moduleCheckboxes.forEach(cb => {
            cb.checked = false;
            cb.closest('.module-card').classList.remove('active');
        });
        updateModuleCount();
    });

    // Menús - Mostrar/ocultar sección según el checkbox de copiar menús
    const copyMenusCheckbox = document.getElementById('copyMenus');
    const menusSection = document.getElementById('menusSection');
    const menuCheckboxes = document.querySelectorAll('.menu-checkbox');
    const menuCount = document.getElementById('menuCount');

    copyMenusCheckbox.addEventListener('change', function() {
        menusSection.style.display = this.checked ? 'block' : 'none';
    });

    // Actualizar contador de menús
    function updateMenuCount() {
        const checked = document.querySelectorAll('.menu-checkbox:checked').length;
        menuCount.textContent = checked;
    }

    menuCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateMenuCount);
    });

    // Seleccionar todos los menús
    document.getElementById('selectAllMenus').addEventListener('click', function() {
        menuCheckboxes.forEach(cb => cb.checked = true);
        updateMenuCount();
    });

    // Deseleccionar todos los menús
    document.getElementById('deselectAllMenus').addEventListener('click', function() {
        menuCheckboxes.forEach(cb => cb.checked = false);
        updateMenuCount();
    });

    // Añadir nuevo rol
    let roleIndex = {{ count($defaultRoles) }};
    document.getElementById('addRole').addEventListener('click', function() {
        const roleCard = document.createElement('div');
        roleCard.className = 'role-card';
        roleCard.dataset.index = roleIndex;
        roleCard.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-role">
                <i class="bi bi-trash"></i>
            </button>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="role_name[]" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-control" name="role_slug[]" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="role_description[]">
                    </div>
                </div>
            </div>
        `;
        rolesContainer.appendChild(roleCard);
        roleIndex++;
    });

    // Eliminar rol
    rolesContainer.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-role')) {
            const roleCard = e.target.closest('.role-card');
            Swal.fire({
                title: '¿Eliminar rol?',
                text: 'Este rol no se creará en los nuevos tenants',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    roleCard.remove();
                }
            });
        }
    });

    // Enviar formulario
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Mostrar spinner
        btnSave.querySelector('.btn-text').classList.add('d-none');
        btnSave.querySelector('.btn-loading').classList.remove('d-none');
        btnSave.disabled = true;

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión: ' + error.message
            });
        })
        .finally(() => {
            btnSave.querySelector('.btn-text').classList.remove('d-none');
            btnSave.querySelector('.btn-loading').classList.add('d-none');
            btnSave.disabled = false;
        });
    });
});
</script>
@endpush
