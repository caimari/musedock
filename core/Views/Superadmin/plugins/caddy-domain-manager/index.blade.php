@extends('layouts.app')

@section('title', 'Domain Manager')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-globe2"></i> Domain Manager</h2>
                <p class="text-muted mb-0">Gestiona los dominios custom de tus tenants e integra con Caddy Server</p>
            </div>
            <div class="d-flex gap-2">
                @if($caddyApiAvailable)
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Caddy API Conectado</span>
                @else
                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Caddy API No Disponible</span>
                @endif
                <a href="/musedock/domain-manager/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nuevo Dominio
                </a>
            </div>
        </div>

        @include('partials.alerts')

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="/musedock/domain-manager" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="search" class="form-control" placeholder="Dominio o nombre..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado Caddy</label>
                        <select name="caddy_status" class="form-select">
                            <option value="">Todos</option>
                            <option value="not_configured" {{ ($filters['caddy_status'] ?? '') === 'not_configured' ? 'selected' : '' }}>No Configurado</option>
                            <option value="pending_dns" {{ ($filters['caddy_status'] ?? '') === 'pending_dns' ? 'selected' : '' }}>Pendiente DNS</option>
                            <option value="configuring" {{ ($filters['caddy_status'] ?? '') === 'configuring' ? 'selected' : '' }}>Configurando</option>
                            <option value="active" {{ ($filters['caddy_status'] ?? '') === 'active' ? 'selected' : '' }}>Activo</option>
                            <option value="error" {{ ($filters['caddy_status'] ?? '') === 'error' ? 'selected' : '' }}>Error</option>
                            <option value="suspended" {{ ($filters['caddy_status'] ?? '') === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado Tenant</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Activo</option>
                            <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                            <option value="suspended" {{ ($filters['status'] ?? '') === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-filter"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="/musedock/domain-manager" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de dominios -->
        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tenant</th>
                            <th>Dominio</th>
                            <th>Estado Caddy</th>
                            <th>SSL</th>
                            <th>Route ID</th>
                            <th>Configurado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            <tr>
                                <td>
                                    <strong>{{ $tenant->name }}</strong>
                                    <br><small class="text-muted">{{ $tenant->slug }}</small>
                                </td>
                                <td>
                                    <a href="https://{{ $tenant->domain }}" target="_blank" class="text-decoration-none">
                                        {{ $tenant->domain }}
                                        <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                    @if($tenant->include_www ?? false)
                                        <br><small class="text-muted">+ www</small>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusClass = match($tenant->caddy_status ?? 'not_configured') {
                                            'active' => 'success',
                                            'configuring' => 'info',
                                            'pending_dns' => 'warning',
                                            'error' => 'danger',
                                            'suspended' => 'secondary',
                                            default => 'light'
                                        };
                                        $statusText = match($tenant->caddy_status ?? 'not_configured') {
                                            'active' => 'Activo',
                                            'configuring' => 'Configurando',
                                            'pending_dns' => 'Pendiente DNS',
                                            'error' => 'Error',
                                            'suspended' => 'Suspendido',
                                            default => 'No Configurado'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusClass }}">{{ $statusText }}</span>
                                    @if($tenant->caddy_error_log ?? false)
                                        <br><small class="text-danger" title="{{ $tenant->caddy_error_log }}">
                                            <i class="bi bi-exclamation-triangle"></i> Ver error
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    @if(($tenant->caddy_status ?? '') === 'active')
                                        <span class="text-success"><i class="bi bi-shield-check"></i> SSL</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($tenant->caddy_route_id ?? false)
                                        <code class="small">{{ $tenant->caddy_route_id }}</code>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($tenant->caddy_configured_at ?? false)
                                        {{ date('d/m/Y H:i', strtotime($tenant->caddy_configured_at)) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Verificar estado -->
                                        <button type="button" class="btn btn-outline-info" onclick="checkStatus({{ $tenant->id }})" title="Verificar estado">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>

                                        <!-- Editar -->
                                        <a href="/musedock/domain-manager/{{ $tenant->id }}/edit" class="btn btn-outline-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Reconfigurar en Caddy -->
                                        @if($caddyApiAvailable)
                                            <button type="button" class="btn btn-outline-primary" onclick="reconfigure({{ $tenant->id }})" title="Reconfigurar en Caddy">
                                                <i class="bi bi-gear"></i>
                                            </button>
                                        @endif

                                        <!-- Eliminar -->
                                        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete({{ $tenant->id }}, '{{ $tenant->domain }}')" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                    No hay dominios configurados.
                                    <br><a href="/musedock/domain-manager/create">Crear el primero</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de eliminar el tenant con dominio <strong id="deleteDomainName"></strong>?</p>
                <p class="text-danger small mb-0">
                    <i class="bi bi-exclamation-circle"></i>
                    Esta acción eliminará también la configuración de Caddy y todos los datos asociados.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="bi bi-trash"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de estado -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-info-circle"></i> Estado del Dominio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="statusModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 mb-0">Verificando...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let deleteId = null;
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));

function confirmDelete(id, domain) {
    deleteId = id;
    document.getElementById('deleteDomainName').textContent = domain;
    deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
    if (!deleteId) return;

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Eliminando...';

    try {
        const response = await fetch(`/musedock/domain-manager/${deleteId}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        });

        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
        }
    } catch (error) {
        alert('Error de conexión');
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
    }
});

async function checkStatus(id) {
    document.getElementById('statusModalBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2 mb-0">Verificando...</p>
        </div>
    `;
    statusModal.show();

    try {
        const response = await fetch(`/musedock/domain-manager/${id}/status`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success) {
            const sslStatus = data.ssl_valid
                ? '<span class="text-success"><i class="bi bi-shield-check"></i> Válido</span>'
                : '<span class="text-warning"><i class="bi bi-shield-exclamation"></i> No válido</span>';

            const domainStatus = data.domain_responds
                ? '<span class="text-success"><i class="bi bi-check-circle"></i> Responde</span>'
                : '<span class="text-danger"><i class="bi bi-x-circle"></i> No responde</span>';

            const routeStatus = data.route_exists
                ? '<span class="text-success"><i class="bi bi-check-circle"></i> Existe</span>'
                : '<span class="text-warning"><i class="bi bi-x-circle"></i> No existe</span>';

            document.getElementById('statusModalBody').innerHTML = `
                <table class="table table-sm mb-0">
                    <tr><th>Dominio</th><td>${data.domain}</td></tr>
                    <tr><th>Estado Caddy</th><td><span class="badge bg-info">${data.caddy_status}</span></td></tr>
                    <tr><th>Ruta en Caddy</th><td>${routeStatus}</td></tr>
                    <tr><th>Respuesta HTTPS</th><td>${domainStatus}</td></tr>
                    <tr><th>Certificado SSL</th><td>${sslStatus}</td></tr>
                    ${data.http_code ? `<tr><th>Código HTTP</th><td>${data.http_code}</td></tr>` : ''}
                </table>
            `;
        } else {
            document.getElementById('statusModalBody').innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-exclamation-circle"></i> ${data.message}
                </div>
            `;
        }
    } catch (error) {
        document.getElementById('statusModalBody').innerHTML = `
            <div class="alert alert-danger mb-0">
                <i class="bi bi-exclamation-circle"></i> Error de conexión
            </div>
        `;
    }
}

async function reconfigure(id) {
    if (!confirm('¿Reconfigurar este dominio en Caddy?')) return;

    try {
        const response = await fetch(`/musedock/domain-manager/${id}/reconfigure`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        });
        const result = await response.json();

        if (result.success) {
            alert('Dominio reconfigurado correctamente');
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        alert('Error de conexión');
    }
}
</script>
@endpush

@endsection
