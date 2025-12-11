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
                                            default => 'dark'
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

@push('scripts')
<script>
const csrfToken = '<?= csrf_token() ?>';

// ========== ELIMINAR TENANT con SweetAlert2 y verificación de contraseña ==========
function confirmDelete(id, domain) {
    Swal.fire({
        title: '<i class="bi bi-exclamation-triangle text-danger"></i> Confirmar Eliminación',
        html: `
            <div class="text-start">
                <p class="mb-3">¿Estás seguro de eliminar el tenant con dominio <strong>${domain}</strong>?</p>
                <div class="alert alert-danger py-2 mb-3">
                    <i class="bi bi-trash me-2"></i>
                    <small><strong>Esta acción no se puede deshacer.</strong> Se eliminará la configuración de Caddy y todos los datos asociados.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Introduce tu contraseña para confirmar:</label>
                    <input type="password" id="deletePassword" class="form-control" placeholder="Contraseña del superadmin" autocomplete="current-password">
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

            fetch(`/musedock/domain-manager/${id}/delete-secure`, {
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
}

// ========== VERIFICAR ESTADO con SweetAlert2 ==========
async function checkStatus(id) {
    Swal.fire({
        title: '<i class="bi bi-info-circle text-info"></i> Estado del Dominio',
        html: `
            <div class="text-center py-4">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 mb-0">Verificando...</p>
            </div>
        `,
        showConfirmButton: false,
        allowOutsideClick: false,
        width: '500px'
    });

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

            Swal.fire({
                title: '<i class="bi bi-info-circle text-info"></i> Estado del Dominio',
                html: `
                    <div class="text-start">
                        <table class="table table-sm mb-0">
                            <tr><th style="width:40%">Dominio</th><td>${data.domain}</td></tr>
                            <tr><th>Estado Caddy</th><td><span class="badge bg-info">${data.caddy_status}</span></td></tr>
                            <tr><th>Ruta en Caddy</th><td>${routeStatus}</td></tr>
                            <tr><th>Respuesta HTTPS</th><td>${domainStatus}</td></tr>
                            <tr><th>Certificado SSL</th><td>${sslStatus}</td></tr>
                            ${data.http_code ? `<tr><th>Código HTTP</th><td>${data.http_code}</td></tr>` : ''}
                        </table>
                    </div>
                `,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#6c757d',
                width: '500px'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo verificar el estado del dominio.',
            confirmButtonColor: '#0d6efd'
        });
    }
}

// ========== RECONFIGURAR con SweetAlert2 ==========
async function reconfigure(id) {
    const result = await Swal.fire({
        title: '<i class="bi bi-gear text-primary"></i> Reconfigurar en Caddy',
        html: '<p>¿Deseas reconfigurar este dominio en Caddy?</p><p class="text-muted small">Esto actualizará la configuración SSL y las rutas.</p>',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-gear me-1"></i> Reconfigurar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d'
    });

    if (!result.isConfirmed) return;

    Swal.fire({
        title: 'Reconfigurando...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch(`/musedock/domain-manager/${id}/reconfigure`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Reconfigurado',
                text: 'El dominio ha sido reconfigurado correctamente en Caddy.',
                confirmButtonColor: '#0d6efd'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'No se pudo reconfigurar el dominio.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo completar la operación.',
            confirmButtonColor: '#0d6efd'
        });
    }
}
</script>
@endpush

@endsection
