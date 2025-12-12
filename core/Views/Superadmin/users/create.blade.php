@extends('layouts.app')
@section('title', 'Crear nuevo usuario')
@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Crear nuevo usuario</h2>
    @include('partials.alerts-sweetalert2')

    @if(!($multi_tenant_enabled ?? false))
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Modo CMS Simple:</strong> Multi-tenant está desactivado. Los usuarios creados serán usuarios globales del CMS.
    </div>
    @endif

    <form method="POST" action="/musedock/users/store" onsubmit="return validateForm()">
        {!! csrf_field() !!}
        <div class="mb-3">
            <label for="name" class="form-label">Nombre completo</label>
            <input type="text" name="name" id="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('password')">
                    👁️
                </button>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" class="form-control" required>
        </div>

        @if($multi_tenant_enabled ?? false)
        {{-- Multi-tenant habilitado: mostrar tipo de usuario y selector de tenant --}}
        <div class="mb-3">
            <label for="type" class="form-label">Tipo de usuario</label>
            <select name="type" id="type" class="form-control" required onchange="toggleTenantField()">
                <option value="user">Usuario CMS (Global)</option>
                <option value="admin">Admin de Tenant</option>
            </select>
            <div class="form-text">
                <strong>Usuario CMS:</strong> Acceso al panel principal /musedock/<br>
                <strong>Admin de Tenant:</strong> Acceso solo al panel de su tenant asignado
            </div>
        </div>

        <div class="mb-3" id="tenant-field" style="display: none;">
            <label for="tenant_id" class="form-label">Tenant asignado</label>
            <select name="tenant_id" id="tenant_id" class="form-control">
                <option value="">-- Seleccionar Tenant --</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant['id'] }}">{{ $tenant['name'] }}</option>
                @endforeach
            </select>
            <div class="form-text">Requerido para Admins de Tenant</div>
        </div>
        @else
        {{-- Multi-tenant desactivado: solo usuarios CMS --}}
        <input type="hidden" name="type" value="user">
        <input type="hidden" name="tenant_id" value="">
        @endif

        <div class="mb-3">
            <label class="form-label">Asignar roles</label>
            @forelse ($roles as $role)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role['id'] }}" id="role_{{ $role['id'] }}">
                    <label class="form-check-label" for="role_{{ $role['id'] }}">{{ $role['name'] }}</label>
                </div>
            @empty
                <p class="text-muted">No hay roles disponibles.</p>
            @endforelse
        </div>

        <div class="d-flex justify-content-end mt-4">
            <a href="/musedock/users" class="btn btn-secondary me-2">Cancelar</a>
            <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
    </form>

    <div class="alert alert-warning mt-4">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Nota sobre permisos:</strong><br>
        Los permisos y roles seleccionados aquí son asignados manualmente por el superadmin.<br>
        Los <a href="/musedock/settings/tenant-defaults">valores por defecto de tenant</a> <strong>NO</strong> se aplican a usuarios creados desde esta pantalla; esos defaults solo se usan al crear nuevos tenants.
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId) {
    const input = document.getElementById(fieldId);
    input.type = input.type === "password" ? "text" : "password";
}

function toggleTenantField() {
    const typeSelect = document.getElementById('type');
    const tenantField = document.getElementById('tenant-field');
    const tenantSelect = document.getElementById('tenant_id');

    if (typeSelect && tenantField) {
        if (typeSelect.value === 'admin') {
            tenantField.style.display = 'block';
            tenantSelect.required = true;
        } else {
            tenantField.style.display = 'none';
            tenantSelect.required = false;
            tenantSelect.value = '';
        }
    }
}

function validateForm() {
    const email = document.getElementById('email').value;
    const pass1 = document.getElementById('password').value;
    const pass2 = document.getElementById('password_confirmation').value;
    const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

    if (!emailValid) {
        alert("Introduce un correo electrónico válido.");
        return false;
    }
    if (pass1 !== pass2) {
        alert("Las contraseñas no coinciden.");
        return false;
    }

    // Validar tenant para admins
    const typeSelect = document.getElementById('type');
    const tenantSelect = document.getElementById('tenant_id');
    if (typeSelect && typeSelect.value === 'admin' && (!tenantSelect || !tenantSelect.value)) {
        alert("Debes seleccionar un Tenant para los Admins de Tenant.");
        return false;
    }

    return true;
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    toggleTenantField();
});
</script>
@endsection
