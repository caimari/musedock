@extends('layouts.app')

@section('title', __('tenants.title'))

@section('content')
<style>
.tenant-page .btn { border-radius:8px;text-decoration:none!important;display:inline-flex;align-items:center;justify-content:center;gap:6px; }
.tenant-page .card { border:1px solid #e5eaf1;border-radius:12px;box-shadow:0 3px 14px rgba(30,50,80,.035);overflow:hidden; }
.tenant-page .card-body { padding:24px; }
.tenant-page .table td,.tenant-page .table th { padding:16px; }
.tenant-page .tenant-actions { display:flex;gap:8px;flex-wrap:wrap; }
.tenant-page .form-control,.tenant-page .form-select { border-radius:7px; }
.tenant-color { width:18px;height:18px;border-radius:50%;padding:0;border:2px solid white;outline:1px solid #ccd3dd; }
.tenant-color.selected { outline:2px solid #263c55; }
.tenant-color-blue { background:#397de0; }.tenant-color-green { background:#279c65; }.tenant-color-orange { background:#e49a29; }.tenant-color-purple { background:#8a56c5; }.tenant-color-red { background:#d84c53; }.tenant-color-none { background:#d5dae1; }
</style>
<div class="app-content tenant-page">
    <div class="container-fluid">

        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
            <h2>{{ __('tenants.title') }}</h2>
            <div class="d-flex gap-2">
                <a href="/musedock/settings/tenant-defaults" class="btn btn-outline-secondary">
                    <i class="bi bi-gear-wide-connected"></i> Configurar Defaults
                </a>
                <a href="/musedock/tenants/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> {{ __('tenants.create') }}
                </a>
            </div>
        </div>

        <form method="get" action="/musedock/tenants" class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-end">
                <div class="flex-grow-1"><label for="tenant-search" class="form-label">Buscar nombre o dominio</label><input id="tenant-search" name="q" class="form-control" value="{{ $search }}" placeholder="Nombre o dominio"></div>
                <div><label for="tenant-type" class="form-label">Tipo de dominio</label><select id="tenant-type" name="type" class="form-select">
                    <option value="">Todos</option><option value="domain" {{ $type === 'domain' ? 'selected' : '' }}>Dominios propios</option><option value="subdomain" {{ $type === 'subdomain' ? 'selected' : '' }}>Subdominios de {{ $mainDomain }}</option>
                </select></div>
                <div><label for="tenant-per-page" class="form-label">Por página</label><select id="tenant-per-page" name="per_page" class="form-select">@foreach ([25, 50, 100, 200] as $size)<option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select></div>
                <div><label for="tenant-ownership" class="form-label">Clasificación</label><select id="tenant-ownership" name="ownership" class="form-select"><option value="">Todos</option><option value="client" {{ $ownership === 'client' ? 'selected' : '' }}>Dominios de clientes</option><option value="unmarked" {{ $ownership === 'unmarked' ? 'selected' : '' }}>Sin marca de cliente</option></select></div>
                <div>
                    <label for="tenant-color-filter" class="form-label">Color</label>
                    <select id="tenant-color-filter" name="color" class="form-select">
                        <option value="">Todos los colores</option>
                        <option value="none" {{ $color === 'none' ? 'selected' : '' }}>Sin color</option>
                        @foreach ($labelColors as $value => $label)
                            @if ($value !== '')
                                <option value="{{ $value }}" {{ $color === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="direction" value="{{ $direction }}">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn btn-outline-secondary" href="/musedock/tenants">Restablecer</a>
            </div>
        </form>
        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a class="text-decoration-none" href="{{ $listUrl(['sort' => 'name', 'direction' => $sort === 'name' && $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">{{ __('tenants.name') }} <i class="bi {{ $sort === 'name' ? ($direction === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up' }}"></i></a></th>
                            <th aria-sort="{{ $sort === 'domain' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a class="text-decoration-none" href="{{ $listUrl(['sort' => 'domain', 'direction' => $sort === 'domain' && $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">{{ __('tenants.domain') }} <i class="bi {{ $sort === 'domain' ? ($direction === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up' }}"></i></a></th>
                            <th aria-sort="{{ $sort === 'created_at' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a class="text-decoration-none" href="{{ $listUrl(['sort' => 'created_at', 'direction' => $sort === 'created_at' && $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">{{ __('common.created') }} <i class="bi {{ $sort === 'created_at' ? ($direction === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up' }}"></i></a></th>
                            <th>{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($total === 0)<tr><td colspan="4" class="text-center text-muted py-4">No hay tenants que coincidan con los filtros.</td></tr>@endif
                        @foreach ($tenants as $tenant)
                            <tr>
							<td>{{ $tenant->name }}</td>
							<td>{{ $tenant->domain }} @if (!empty($tenant->is_client_domain))<span class="badge bg-secondary ms-2">Cliente</span>@endif
                                <form method="post" action="/musedock/tenants/{{ $tenant->id }}/classification" class="d-flex gap-2 mt-2" aria-label="Color de {{ $tenant->domain }}">
                                    {!! csrf_field() !!}
                                    @foreach ($labelColors as $value => $label)<button type="submit" name="label_color" value="{{ $value }}" class="tenant-color tenant-color-{{ $value ?: 'none' }} {{ ($tenant->label_color ?? '') === $value ? 'selected' : '' }}" title="{{ $label }}" aria-label="{{ $label }}" aria-pressed="{{ ($tenant->label_color ?? '') === $value ? 'true' : 'false' }}"></button>@endforeach
                                </form>
                            </td>
							<td>
                                @php
                                    $createdAt = $tenant->created_at ? new DateTime($tenant->created_at) : null;
                                @endphp
                                {{ $createdAt ? $createdAt->format($dateFormat . ' ' . $timeFormat) : '-' }}
                            </td>
							<td><div class="tenant-actions">
<a href="https://{{ $tenant->domain }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary" title="Abrir web de {{ $tenant->domain }}" aria-label="Abrir web de {{ $tenant->domain }}"><i class="bi bi-box-arrow-up-right"></i></a>
								<a href="/musedock/tenants/{{ $tenant->id }}/edit" class="btn btn-sm btn-outline-primary">
									<i class="bi bi-pencil"></i> {{ __('common.edit') }}
								</a>

								<button type="button" class="btn btn-sm btn-outline-danger btn-delete-tenant"
									data-tenant-id="{{ $tenant->id }}"
									data-tenant-name="{{ $tenant->name }}">
									<i class="bi bi-trash"></i> {{ __('common.delete') }}
								</button>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 border-top">
                    <span class="text-muted">{{ $total === 0 ? 0 : ($page - 1) * $perPage + 1 }}–{{ min($page * $perPage, $total) }} de {{ $total }} tenants</span>
                    <nav aria-label="Paginación de tenants" class="d-flex flex-wrap gap-2 align-items-center">
                        @if ($page > 1)<a class="btn btn-sm btn-outline-secondary" href="{{ $listUrl(['page' => 1]) }}">Primera</a><a class="btn btn-sm btn-outline-secondary" href="{{ $listUrl(['page' => $page - 1]) }}">Anterior</a>@endif
                        <span>Página {{ $page }} de {{ $pages }}</span>
                        @if ($page < $pages)<a class="btn btn-sm btn-outline-secondary" href="{{ $listUrl(['page' => $page + 1]) }}">Siguiente</a><a class="btn btn-sm btn-outline-secondary" href="{{ $listUrl(['page' => $pages]) }}">Última</a>@endif
                    </nav>
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= csrf_token() ?>';

    // ========== ELIMINAR TENANT con SweetAlert2 y verificación de contraseña ==========
    document.querySelectorAll('.btn-delete-tenant').forEach(btn => {
        btn.addEventListener('click', function() {
            const tenantId = this.dataset.tenantId;
            const tenantName = this.dataset.tenantName;

            Swal.fire({
                title: '<i class="bi bi-exclamation-triangle text-danger"></i> Confirmar Eliminación',
                html: `
                    <div class="text-start">
                        <p class="mb-3">¿Estás seguro de eliminar el tenant <strong>${tenantName}</strong>?</p>
                        <div class="alert alert-danger py-2 mb-3">
                            <i class="bi bi-trash me-2"></i>
                            <small><strong>Esta acción no se puede deshacer.</strong> Se eliminarán todos los usuarios, roles, permisos y datos asociados.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Introduce tu contraseña para confirmar:</label>
                            <input type="password" id="deletePassword" class="form-control" placeholder="Contraseña de tu usuario" autocomplete="current-password">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar Tenant',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                width: '450px',
                focusConfirm: false,
                didOpen: () => {
                    document.getElementById('deletePassword').focus();
                },
                preConfirm: () => {
                    const password = document.getElementById('deletePassword').value;
                    if (!password) {
                        Swal.showValidationMessage('La contraseña es requerida');
                        return false;
                    }
                    return password;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Eliminando tenant...',
                        html: '<p class="mb-0">Por favor espera...</p>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch(`/musedock/tenants/${tenantId}/delete-secure`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            _csrf: csrfToken,
                            password: result.value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Tenant Eliminado',
                                text: data.message,
                                confirmButtonColor: '#0d6efd'
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#0d6efd'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error de conexión. Intenta de nuevo.',
                            confirmButtonColor: '#0d6efd'
                        });
                    });
                }
            });
        });
    });
});
</script>
@endpush

@endsection
