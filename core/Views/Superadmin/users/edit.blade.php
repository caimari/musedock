@extends('layouts.app')
@section('title', 'Editar Usuario')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Editar Usuario</h2>
        <a href="/musedock/users" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver a Usuarios
        </a>
    </div>
    @include('partials.alerts-sweetalert2')
    
    <form method="POST" action="/musedock/users/{{ is_object($user) ? $user->id : $user['id'] }}/update?type={{ $type }}">
        {!! csrf_field() !!}
        <div class="mb-3">
            <label for="name" class="form-label">Nombre</label>
            <input type="text" name="name" id="name" value="{{ is_object($user) ? $user->name : $user['name'] }}" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <div class="input-group">
                <input type="email" name="email_display" id="email_display" value="{{ is_object($user) ? $user->email : $user['email'] }}" class="form-control" required disabled>
                <input type="hidden" name="email" id="email" value="{{ is_object($user) ? $user->email : $user['email'] }}">
                <button type="button" class="btn btn-outline-warning" onclick="enableEmailChange(this)">
                    Activar cambio
                </button>
            </div>
            <small class="text-muted">Por seguridad, solo debes cambiar el correo cuando sea absolutamente necesario.</small>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Cambiar contraseña</label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" placeholder="Desactivado por seguridad" disabled>
                <button type="button" class="btn btn-outline-warning" onclick="enablePasswordChange(this)">
                    Activar cambio
                </button>
            </div>
            <small class="text-muted">La contraseña solo se actualizará si introduces una nueva.</small>
        </div>
        {{-- Para superadmin con is_root=1: mostrar aviso de acceso total --}}
        @if ($type === 'superadmin' && ($isRoot ?? false))
            <div class="alert alert-info">
                <i class="bi bi-shield-check me-2"></i>
                <strong>Super Admin Root:</strong> Este usuario tiene acceso completo a todas las funciones del sistema.
                No requiere asignación de roles ni permisos.
            </div>
        @endif

        {{-- Mostrar tenant solo para admins/users (no superadmins) --}}
        @if ($type !== 'superadmin')
            <div class="mb-3">
                <label for="tenant_id" class="form-label">Tenant</label>
                <select name="tenant_id" id="tenant_id" class="form-control">
                    <option value="">Global (CMS Principal)</option>
                    @foreach ($tenants as $tenant)
                        @php
                            $tenantId = is_object($tenant) ? $tenant->id : $tenant['id'];
                            $tenantName = is_object($tenant) ? $tenant->name : $tenant['name'];
                            $userTenantId = is_object($user) ? $user->tenant_id : $user['tenant_id'];
                            $selected = $userTenantId == $tenantId ? 'selected' : '';
                        @endphp
                        <option value="{{ $tenantId }}" {{ $selected }}>
                            {{ $tenantName }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Mostrar roles para: admins, users, y superadmins SIN is_root --}}
        @if ($type !== 'superadmin' || !($isRoot ?? false))
            <div class="mb-3">
                <label class="form-label">Roles asignados</label>
                @if ($type === 'superadmin')
                    <p class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Este super admin NO tiene acceso root. Los permisos del sidebar dependen de los roles asignados.
                    </p>
                @endif
                @forelse ($roles as $role)
                    @php
                        $roleId = is_object($role) ? $role->id : $role['id'];
                        $roleName = is_object($role) ? $role->name : $role['name'];
                        $roleDesc = is_object($role) ? ($role->description ?? '') : ($role['description'] ?? '');
                        $checked = in_array($roleId, $userRoles) ? 'checked' : '';
                    @endphp
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $roleId }}"
                            {{ $checked }} id="role_{{ $roleId }}">
                        <label class="form-check-label" for="role_{{ $roleId }}">
                            {{ $roleName }}
                            @if($roleDesc)
                                <small class="text-muted">- {{ $roleDesc }}</small>
                            @endif
                        </label>
                    </div>
                @empty
                    <div class="alert alert-warning">
                        No hay roles disponibles. <a href="/musedock/roles/create">Crear un rol</a>
                    </div>
                @endforelse
            </div>
        @endif

        {{-- PERMISOS DIRECTOS (solo para admins y users, no superadmins) --}}
        @if ($type !== 'superadmin')
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Permisos Directos</h5>
                    @if(!empty($groupedPermissions))
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-success" id="btnSelectAllPerms">
                                <i class="bi bi-check-all me-1"></i> Seleccionar todos
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btnDeselectAllPerms">
                                <i class="bi bi-x-lg me-1"></i> Deseleccionar todos
                            </button>
                        </div>
                    @endif
                </div>
                <p class="text-muted small">Selecciona los permisos específicos para este usuario. Los permisos directos se agregan a los heredados de roles.</p>

                @if(!empty($groupedPermissions))
                    @foreach($groupedPermissions as $category => $permissions)
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <strong>{{ $category }}</strong>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach($permissions as $perm)
                                        @php
                                            $isChecked = in_array($perm['slug'], $userPermissions);
                                        @endphp
                                        <div class="col-md-6 mb-2">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $perm['slug'] }}"
                                                    id="perm_{{ str_replace('.', '_', $perm['slug']) }}"
                                                    {{ $isChecked ? 'checked' : '' }}
                                                >
                                                <label class="form-check-label" for="perm_{{ str_replace('.', '_', $perm['slug']) }}">
                                                    <strong>{{ $perm['name'] }}</strong>
                                                    <br>
                                                    <small class="text-muted">{{ $perm['description'] }}</small>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="alert alert-warning">
                        No hay permisos disponibles. <a href="/musedock/permissions">Crear permisos</a>
                    </div>
                @endif
            </div>
        @endif
        <div class="d-flex justify-content-end mt-4">
            <a href="/musedock/users" class="btn btn-secondary me-2">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
        
        <!-- Campo oculto para redirección tras guardar (0 = quedarse en edit, 1 = volver a index) -->
        <input type="hidden" name="redirect_to_index" value="0">
    </form>
</div>
@push('scripts')
<script>
function enableEmailChange(button) {
    Swal.fire({
        title: '<i class="bi bi-envelope-exclamation text-warning"></i> Cambiar Email',
        html: `
            <div class="text-start">
                <div class="alert alert-warning py-2 mb-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>ATENCIÓN:</strong> Cambiar el correo electrónico puede afectar el acceso del usuario.
                </div>
                <p>¿Estás seguro de que deseas habilitar el cambio de email?</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Sí, activar cambio',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        width: '450px'
    }).then((result) => {
        if (result.isConfirmed) {
            var emailDisplay = document.getElementById('email_display');
            var hiddenEmail = document.getElementById('email');
            var inputGroup = button.parentNode;

            // Creamos el nuevo input editable
            var newInput = document.createElement('input');
            newInput.type = 'email';
            newInput.name = 'email';
            newInput.id = 'email';
            newInput.value = hiddenEmail.value;
            newInput.className = 'form-control';
            newInput.required = true;

            // Eliminamos el campo de visualización y el hidden antiguo
            if (emailDisplay) {
                inputGroup.removeChild(emailDisplay);
            }
            if (hiddenEmail) {
                inputGroup.removeChild(hiddenEmail);
            }

            // Insertamos el nuevo input editable antes del botón
            inputGroup.insertBefore(newInput, button);

            // Modificamos el botón
            button.disabled = true;
            button.classList.remove('btn-outline-warning');
            button.classList.add('btn-outline-danger');
            button.textContent = 'Cambio activado';

            newInput.focus();

            Swal.fire({
                icon: 'info',
                title: 'Cambio de email activado',
                text: 'Ahora puedes modificar el correo electrónico.',
                confirmButtonColor: '#0d6efd',
                timer: 2000,
                timerProgressBar: true
            });
        }
    });
}

function enablePasswordChange(button) {
    Swal.fire({
        title: '<i class="bi bi-key text-warning"></i> Cambiar Contraseña',
        html: `
            <div class="text-start">
                <div class="alert alert-warning py-2 mb-3">
                    <i class="bi bi-shield-lock me-2"></i>
                    <strong>Seguridad:</strong> La nueva contraseña reemplazará la actual del usuario.
                </div>
                <p>¿Deseas habilitar el cambio de contraseña?</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Sí, activar cambio',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        width: '450px'
    }).then((result) => {
        if (result.isConfirmed) {
            var passwordField = document.getElementById('password');
            passwordField.disabled = false;
            passwordField.placeholder = 'Introduce la nueva contraseña';
            button.disabled = true;
            button.classList.remove('btn-outline-warning');
            button.classList.add('btn-outline-danger');
            button.textContent = 'Cambio activado';
            passwordField.focus();

            Swal.fire({
                icon: 'info',
                title: 'Cambio de contraseña activado',
                text: 'Ahora puedes introducir la nueva contraseña.',
                confirmButtonColor: '#0d6efd',
                timer: 2000,
                timerProgressBar: true
            });
        }
    });
}

// ========== SELECCIONAR / DESELECCIONAR TODOS LOS PERMISOS ==========
document.addEventListener('DOMContentLoaded', function() {
    const btnSelectAll = document.getElementById('btnSelectAllPerms');
    const btnDeselectAll = document.getElementById('btnDeselectAllPerms');

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
            checkboxes.forEach(cb => cb.checked = true);

            Swal.fire({
                icon: 'success',
                title: 'Permisos seleccionados',
                text: `Se han seleccionado ${checkboxes.length} permisos.`,
                confirmButtonColor: '#0d6efd',
                timer: 1500,
                timerProgressBar: true
            });
        });
    }

    if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', function() {
            Swal.fire({
                title: '<i class="bi bi-question-circle text-warning"></i> Confirmar',
                text: '¿Deseas deseleccionar todos los permisos?',
                showCancelButton: true,
                confirmButtonText: 'Sí, deseleccionar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#6c757d',
                cancelButtonColor: '#0d6efd'
            }).then((result) => {
                if (result.isConfirmed) {
                    const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
                    checkboxes.forEach(cb => cb.checked = false);

                    Swal.fire({
                        icon: 'info',
                        title: 'Permisos deseleccionados',
                        text: 'Se han deseleccionado todos los permisos.',
                        confirmButtonColor: '#0d6efd',
                        timer: 1500,
                        timerProgressBar: true
                    });
                }
            });
        });
    }
});
</script>
@endpush


@endsection