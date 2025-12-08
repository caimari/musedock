@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>{{ $title }}</h2>
        <a href="/musedock/roles" class="btn btn-secondary">Volver</a>
    </div>

    @include('partials.alerts')

    @if(!($multi_tenant_enabled ?? false))
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Modo CMS Simple:</strong> Multi-tenant desactivado. Los roles creados serán para el panel /musedock/.
    </div>
    @endif

    <form method="POST" action="/musedock/roles/store">
        {!! csrf_field() !!}

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Información del rol</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Nombre del rol</label>
                    <input type="text" name="name" class="form-control" required placeholder="Ej: Editor, Moderador, Gestor de contenido">
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="description" class="form-control" placeholder="Descripción breve del rol">
                </div>

                @if($multi_tenant_enabled ?? false)
                {{-- Multi-tenant habilitado: mostrar selector de tenant --}}
                <div class="mb-3">
                    <label for="tenant_id" class="form-label">Asignar a Tenant (opcional)</label>
                    <select name="tenant_id" id="tenant_id" class="form-select">
                        <option value="">(Ninguno / CMS Principal)</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant['id'] }}">{{ $tenant['name'] }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Deja vacío para roles del panel /musedock/. Selecciona un tenant para roles específicos de ese tenant.
                    </div>
                </div>
                @else
                {{-- Multi-tenant desactivado: rol para CMS principal --}}
                <input type="hidden" name="tenant_id" value="">
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Permisos del rol</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Selecciona los permisos que tendrá este rol. Los usuarios con este rol podrán realizar las acciones marcadas.</p>

                <div class="row">
                    @foreach ($groupedPermissions as $category => $permissions)
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <strong>{{ ucfirst($category) }}</strong>
                                    <button type="button" class="btn btn-sm btn-outline-secondary float-end" onclick="toggleCategory('{{ $category }}')">
                                        Todos
                                    </button>
                                </div>
                                <ul class="list-group list-group-flush" id="cat-{{ strtolower(str_replace(' ', '-', $category)) }}">
                                    @foreach ($permissions as $permission)
                                        <li class="list-group-item">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[]"
                                                       value="{{ $permission['id'] }}" id="perm_{{ $permission['id'] }}">
                                                <label class="form-check-label" for="perm_{{ $permission['id'] }}">
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
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="/musedock/roles" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Crear rol
            </button>
        </div>
    </form>
</div>

<script>
function toggleCategory(category) {
    const slug = category.toLowerCase().replace(/\s+/g, '-');
    const container = document.getElementById('cat-' + slug);
    if (!container) {
        console.log('Container not found: cat-' + slug);
        return;
    }

    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);

    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
    });
}
</script>
@endsection
