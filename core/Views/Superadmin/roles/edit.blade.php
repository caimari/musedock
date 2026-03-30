@extends('layouts.app')
@section('title', 'Editar rol')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Editar rol: {{ $role['name'] }}</h2>
        <a href="/musedock/roles" class="btn btn-secondary">Volver</a>
    </div>
    @include('partials.alerts')

    @if(!($multi_tenant_enabled ?? false))
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Modo CMS Simple:</strong> Multi-tenant desactivado. Este rol aplica al panel /musedock/.
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-header bg-info text-white">
            <strong>Información del Rol</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="/musedock/roles/{{ $role['id'] }}/update-info">
                {!! csrf_field() !!}
                <div class="row">
                    <div class="{{ ($multi_tenant_enabled ?? false) ? 'col-md-4' : 'col-md-6' }} mb-3">
                        <label class="form-label">Nombre del rol</label>
                        <input type="text" name="name" class="form-control" value="{{ $role['name'] }}" required>
                    </div>
                    <div class="{{ ($multi_tenant_enabled ?? false) ? 'col-md-4' : 'col-md-6' }} mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="description" class="form-control" value="{{ $role['description'] }}">
                    </div>
                    @if($multi_tenant_enabled ?? false)
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tenant</label>
                        <select name="tenant_id" class="form-control">
                            <option value="global" {{ $role['tenant_id'] === null ? 'selected' : '' }}>Global (CMS Principal)</option>
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant['id'] }}" {{ $role['tenant_id'] == $tenant['id'] ? 'selected' : '' }}>
                                    {{ $tenant['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @else
                    <input type="hidden" name="tenant_id" value="global">
                    @endif
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Actualizar información</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="/musedock/roles/{{ $role['id'] }}/update">
        {!! csrf_field() !!}
        <input type="hidden" name="role_id" value="{{ $role['id'] }}">
        <input type="hidden" name="redirect_to_index" value="1">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <strong>Permisos del Rol</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach ($groupedPermissions as $category => $permissions)
                        <div class="col-md-4 mb-4">
                            <h5 class="text-primary">{{ ucfirst($category) }}</h5>
                            <div class="border rounded p-2 mb-3">
                                <div class="mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="toggleCategoryCheckboxes('{{ str_replace(' ', '_', strtolower($category)) }}', true)">
                                        Seleccionar todos
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            onclick="toggleCategoryCheckboxes('{{ str_replace(' ', '_', strtolower($category)) }}', false)">
                                        Deseleccionar todos
                                    </button>
                                </div>
                                <ul class="list-group category-{{ str_replace(' ', '_', strtolower($category)) }}">
                                    @foreach ($permissions as $permission)
                                        <li class="list-group-item">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="permission_{{ $permission['id'] }}"
                                                       name="permissions[]" value="{{ $permission['id'] }}"
                                                       {{ in_array($permission['id'], $assignedPermissionIds) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="permission_{{ $permission['id'] }}">
                                                    {{ $permission['description'] ?? $permission['name'] ?? $permission['slug'] }}
                                                </label>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-primary" onclick="selectAllPermissions()">Seleccionar todos</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="deselectAllPermissions()">Deseleccionar todos</button>
                </div>
                <div>
                    <a href="/musedock/roles" class="btn btn-light me-2">Cancelar</a>
                    <button type="submit" class="btn btn-success">Guardar permisos</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleCategoryCheckboxes(category, checked) {
    const checkboxes = document.querySelectorAll('.category-' + category + ' input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
    });
}

function selectAllPermissions() {
    const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllPermissions() {
    const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}
</script>
@endsection
