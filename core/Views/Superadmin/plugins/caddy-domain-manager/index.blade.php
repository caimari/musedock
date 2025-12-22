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
                <a href="/musedock/domain-manager/customers" class="btn btn-outline-primary">
                    <i class="bi bi-people"></i> Clientes
                </a>
                <button type="button" class="btn btn-success" onclick="showCreateFreeSubdomainModal()">
                    <i class="bi bi-gift"></i> Crear Subdominio FREE
                </button>
                <a href="/musedock/domain-manager/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nuevo Dominio Custom
                </a>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

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

                                        <!-- Gestionar Email Routing (solo si tiene Cloudflare) -->
                                        @if(!empty($tenant->cloudflare_zone_id))
                                            <a href="/musedock/domain-manager/{{ $tenant->id }}/email-routing"
                                               class="btn btn-outline-success"
                                               title="Gestionar Email Routing">
                                                <i class="bi bi-envelope-at"></i>
                                            </a>
                                        @endif

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

        <!-- Dominios registrados (Customers) -->
        <div class="card mt-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="bi bi-bag-check"></i> Dominios Registrados (Customers)</h5>
                    <small class="text-muted">Órdenes creadas desde el frontend (registro/transferencia)</small>
                </div>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" onchange="applyOrderFilter('hosting_type', this.value)" style="min-width: 190px;">
                        <option value="">Hosting: Todos</option>
                        <option value="musedock_hosting" {{ ($filters['hosting_type'] ?? '') === 'musedock_hosting' ? 'selected' : '' }}>DNS + Hosting MuseDock</option>
                        <option value="dns_only" {{ ($filters['hosting_type'] ?? '') === 'dns_only' ? 'selected' : '' }}>Solo DNS</option>
                    </select>
                    <select class="form-select form-select-sm" onchange="applyOrderFilter('order_status', this.value)" style="min-width: 170px;">
                        <option value="">Estado: Todos</option>
                        <option value="processing" {{ ($filters['order_status'] ?? '') === 'processing' ? 'selected' : '' }}>processing</option>
                        <option value="pending" {{ ($filters['order_status'] ?? '') === 'pending' ? 'selected' : '' }}>pending</option>
                        <option value="registered" {{ ($filters['order_status'] ?? '') === 'registered' ? 'selected' : '' }}>registered</option>
                        <option value="active" {{ ($filters['order_status'] ?? '') === 'active' ? 'selected' : '' }}>active</option>
                        <option value="failed" {{ ($filters['order_status'] ?? '') === 'failed' ? 'selected' : '' }}>failed</option>
                        <option value="cancelled" {{ ($filters['order_status'] ?? '') === 'cancelled' ? 'selected' : '' }}>cancelled</option>
                    </select>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Dominio</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Cloudflare</th>
                            <th>Tenant</th>
                            <th>Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $statusBadge = function ($status) {
                                $status = strtolower((string)$status);
                                return match ($status) {
                                    'active' => 'bg-success',
                                    'registered' => 'bg-success',
                                    'processing' => 'bg-warning text-dark',
                                    'pending' => 'bg-warning text-dark',
                                    'failed' => 'bg-danger',
                                    'cancelled', 'canceled' => 'bg-secondary',
                                    default => 'bg-secondary',
                                };
                            };
                        @endphp
                        @forelse($domainOrders as $order)
                            @php
                                $fullDomain = $order->full_domain ?? trim(($order->domain ?? '') . (!empty($order->extension) ? '.' . $order->extension : ''), '.');
                                $hostingType = $order->hosting_type ?? 'musedock_hosting';
                                $tenantDomain = $order->tenant_domain ?? null;
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $fullDomain }}</td>
                                <td>
                                    <div class="small text-muted">{{ $order->customer_name ?? '—' }}</div>
                                    <div>{{ $order->customer_email ?? '—' }}</div>
                                </td>
                                <td>
                                    @if($hostingType === 'dns_only')
                                        <span class="badge bg-secondary">Solo DNS</span>
                                    @else
                                        <span class="badge bg-info text-dark">DNS + Hosting</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadge($order->status ?? '') }}">{{ $order->status ?? '—' }}</span>
                                </td>
                                <td>
                                    @if(!empty($order->cloudflare_zone_id))
                                        <span class="badge bg-warning text-dark"><i class="bi bi-shield-fill-check"></i> Zona</span>
                                        <div class="small text-muted text-truncate" style="max-width: 220px;">{{ $order->cloudflare_zone_id }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($order->tenant_id))
                                        <a href="/musedock/tenants/{{ $order->tenant_id }}/edit" class="text-decoration-none">
                                            {{ $tenantDomain ?? ('#' . $order->tenant_id) }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @if(!empty($order->created_at))
                                        {{ date('d/m/Y H:i', strtotime($order->created_at)) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if(!empty($order->id))
                                            <a href="/customer/domain/{{ $order->id }}/dns" class="btn btn-outline-primary" title="DNS (Cloudflare)">
                                                <i class="bi bi-hdd-network"></i>
                                            </a>
                                            <a href="/customer/domain/{{ $order->id }}/contacts" class="btn btn-outline-secondary" title="Contactos (OpenProvider)">
                                                <i class="bi bi-person-lines-fill"></i>
                                            </a>
                                        @endif
                                        @if(!empty($tenantDomain))
                                            <a href="https://{{ $tenantDomain }}/admin" target="_blank" class="btn btn-outline-success" title="Admin Tenant">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                    No hay dominios registrados por customers.
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
function applyOrderFilter(key, value) {
    const url = new URL(window.location.href);
    if (!value) url.searchParams.delete(key);
    else url.searchParams.set(key, value);
    window.location.href = url.toString();
}

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
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="deleteFromCloudflare" checked>
                    <label class="form-check-label" for="deleteFromCloudflare">
                        <i class="bi bi-cloud me-1"></i> Eliminar también de Cloudflare
                        <br><small class="text-muted">Desmarcar si quieres mantener la zona/registro DNS en Cloudflare</small>
                    </label>
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
        width: '500px',
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
            return {
                password: password,
                deleteFromCloudflare: document.getElementById('deleteFromCloudflare').checked
            };
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
                    password: result.value.password,
                    deleteFromCloudflare: result.value.deleteFromCloudflare
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

@push('scripts')
<script>
function showCreateFreeSubdomainModal() {
    Swal.fire({
        title: '<i class="bi bi-gift"></i> Crear Subdominio FREE',
        html: `
            <div class="text-start">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Subdominio FREE:</strong> Se creará un nuevo customer y tenant con subdominio .musedock.com gratuito.
                </div>

                <div class="mb-3">
                    <label for="swal-subdomain" class="form-label fw-semibold">Subdominio <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="swal-subdomain" required
                               pattern="[a-z0-9\\-]+" placeholder="ejemplo"
                               title="Solo letras minúsculas, números y guiones">
                        <span class="input-group-text">.musedock.com</span>
                    </div>
                    <div class="form-text">Mínimo 3 caracteres. Solo letras minúsculas, números y guiones.</div>
                </div>

                <div class="mb-3">
                    <label for="swal-customer-name" class="form-label fw-semibold">Nombre del Customer <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="swal-customer-name" required
                           placeholder="Juan Pérez">
                </div>

                <div class="mb-3">
                    <label for="swal-customer-email" class="form-label fw-semibold">Email del Customer <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="swal-customer-email" required
                           placeholder="juan@example.com">
                </div>

                <div class="mb-3">
                    <label for="swal-customer-password" class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="swal-customer-password" required
                           minlength="8" placeholder="Mínimo 8 caracteres">
                    <div class="form-text">El customer usará esta contraseña para acceder a su panel.</div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="swal-send-welcome-email" checked>
                        <label class="form-check-label" for="swal-send-welcome-email">
                            <strong>Enviar email de bienvenida</strong>
                        </label>
                        <div class="form-text">El customer recibirá un email con sus credenciales de acceso al panel admin.</div>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg"></i> Crear Subdominio FREE',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        width: '600px',
        didOpen: () => {
            // Auto-convertir subdomain a minúsculas
            const subdomainInput = document.getElementById('swal-subdomain');
            subdomainInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
            });
        },
        preConfirm: () => {
            const subdomain = document.getElementById('swal-subdomain').value;
            const customerName = document.getElementById('swal-customer-name').value;
            const customerEmail = document.getElementById('swal-customer-email').value;
            const customerPassword = document.getElementById('swal-customer-password').value;
            const sendWelcomeEmail = document.getElementById('swal-send-welcome-email').checked;

            // Validaciones
            if (!subdomain || subdomain.length < 3) {
                Swal.showValidationMessage('El subdominio debe tener al menos 3 caracteres');
                return false;
            }
            if (!/^[a-z0-9\-]+$/.test(subdomain)) {
                Swal.showValidationMessage('El subdominio solo puede contener letras minúsculas, números y guiones');
                return false;
            }
            if (!customerName) {
                Swal.showValidationMessage('El nombre del customer es requerido');
                return false;
            }
            if (!customerEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(customerEmail)) {
                Swal.showValidationMessage('Email inválido');
                return false;
            }
            if (!customerPassword || customerPassword.length < 8) {
                Swal.showValidationMessage('La contraseña debe tener al menos 8 caracteres');
                return false;
            }

            // Crear FormData
            const formData = new FormData();
            formData.append('_csrf_token', '<?= csrf_token() ?>');
            formData.append('subdomain', subdomain);
            formData.append('customer_name', customerName);
            formData.append('customer_email', customerEmail);
            formData.append('customer_password', customerPassword);
            formData.append('send_welcome_email', sendWelcomeEmail ? '1' : '0');

            // Enviar petición
            return fetch('/musedock/domain-manager/create-free', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'No se pudo crear el subdominio');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`Error: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            Swal.fire({
                icon: 'success',
                title: '¡Subdominio FREE creado!',
                html: `
                    <p>El subdominio ha sido creado exitosamente:</p>
                    <p class="h5 text-success">${result.value.domain}</p>
                    <p class="mt-3">Tenant ID: ${result.value.tenant_id}</p>
                `,
                confirmButtonText: 'Entendido'
            }).then(() => {
                location.reload();
            });
        }
    });
}
</script>
@endpush

@endsection
