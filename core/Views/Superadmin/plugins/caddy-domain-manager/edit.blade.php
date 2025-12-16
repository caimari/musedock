@extends('layouts.app')

@section('title', 'Editar Dominio: ' . $tenant->domain)

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-pencil"></i> Editar Dominio</h2>
                <p class="text-muted mb-0">{{ $tenant->domain }}</p>
            </div>
            <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="row">
            <!-- Formulario de edición -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Información del Tenant</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/musedock/domain-manager/{{ $tenant->id }}">
                            {!! csrf_field() !!}
                            <input type="hidden" name="_method" value="PUT">

                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="{{ $tenant->name }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dominio</label>
                                <input type="text" class="form-control" value="{{ $tenant->domain }}" disabled>
                                <div class="form-text text-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    El dominio no se puede cambiar. Para usar otro dominio, crea un nuevo tenant.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">Estado del Tenant</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" {{ $tenant->status === 'active' ? 'selected' : '' }}>Activo</option>
                                    <option value="inactive" {{ $tenant->status === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                    <option value="suspended" {{ $tenant->status === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle text-info"></i>
                                    <strong>Solo afecta al CMS:</strong> Desactivar el tenant impide el acceso al panel /admin/, pero <strong>NO</strong> afecta a Caddy.
                                    El dominio seguira respondiendo si esta configurado en Caddy.
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="include_www" name="include_www"
                                           {{ ($tenant->include_www ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="include_www">
                                        Incluir www.{{ $tenant->domain }}
                                    </label>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-exclamation-triangle text-warning"></i>
                                    <strong>Requiere reconfigurar Caddy:</strong> Si cambias esta opcion, el sistema intentara reconfigurar automaticamente.
                                    Tambien puedes usar el boton "Reconfigurar en Caddy" del panel lateral para regenerar la ruta y el certificado SSL.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="caddy_status" class="form-label">Estado Caddy</label>
                                <select class="form-select" id="caddy_status" name="caddy_status">
                                    <option value="not_configured" {{ ($tenant->caddy_status ?? 'not_configured') === 'not_configured' ? 'selected' : '' }}>No Configurado</option>
                                    <option value="pending_dns" {{ ($tenant->caddy_status ?? '') === 'pending_dns' ? 'selected' : '' }}>Pendiente DNS</option>
                                    <option value="configuring" {{ ($tenant->caddy_status ?? '') === 'configuring' ? 'selected' : '' }}>Configurando</option>
                                    <option value="active" {{ ($tenant->caddy_status ?? '') === 'active' ? 'selected' : '' }}>Activo</option>
                                    <option value="error" {{ ($tenant->caddy_status ?? '') === 'error' ? 'selected' : '' }}>Error</option>
                                    <option value="suspended" {{ ($tenant->caddy_status ?? '') === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                                </select>
                                <div class="form-text">Cambiar manualmente el estado (solo afecta la BD, no Caddy)</div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary" id="btnSubmit">
                                    <span class="btn-text"><i class="bi bi-check-lg"></i> Guardar Cambios</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                        Guardando...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panel de estado -->
            <div class="col-md-4">
                <!-- Estado de Caddy -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-server"></i> Estado Caddy</h6>
                        @if($caddyApiAvailable)
                            <span class="badge bg-success">API Conectada</span>
                        @else
                            <span class="badge bg-danger">API No Disponible</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @php
                            $statusClass = match($tenant->caddy_status ?? 'not_configured') {
                                'active' => 'success',
                                'configuring' => 'info',
                                'pending_dns' => 'warning',
                                'error' => 'danger',
                                'suspended' => 'secondary',
                                default => 'light'
                            };
                        @endphp

                        <p class="mb-2">
                            <strong>Estado:</strong>
                            <span class="badge bg-{{ $statusClass }}">{{ $tenant->caddy_status ?? 'No Configurado' }}</span>
                        </p>

                        @if($tenant->caddy_route_id ?? false)
                            <p class="mb-2">
                                <strong>Route ID:</strong><br>
                                <code>{{ $tenant->caddy_route_id }}</code>
                            </p>
                        @endif

                        @if($tenant->caddy_configured_at ?? false)
                            <p class="mb-2">
                                <strong>Configurado:</strong><br>
                                {{ date('d/m/Y H:i', strtotime($tenant->caddy_configured_at)) }}
                            </p>
                        @endif

                        @if($tenant->caddy_error_log ?? false)
                            <div class="alert alert-danger small mb-2">
                                <strong>Último error:</strong><br>
                                {{ $tenant->caddy_error_log }}
                            </div>
                        @endif

                        @if($caddyApiAvailable)
                            <hr>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-info btn-sm" id="btnCheckStatus" onclick="checkStatus()">
                                    <span class="btn-text"><i class="bi bi-arrow-clockwise"></i> Verificar Estado</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                    </span>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnReconfigure" onclick="reconfigure()">
                                    <span class="btn-text"><i class="bi bi-gear"></i> Reconfigurar en Caddy</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Configurando...
                                    </span>
                                </button>
                                <div class="form-text mt-2 small text-muted">
                                    <i class="bi bi-shield-lock"></i> Regenera la ruta y solicita nuevo certificado SSL de Let's Encrypt.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Info de Caddy Route -->
                @if($caddyRouteInfo ?? false)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-code-slash"></i> Configuración Caddy</h6>
                        </div>
                        <div class="card-body">
                            <pre class="small mb-0" style="max-height: 300px; overflow: auto;">{{ json_encode($caddyRouteInfo, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                @endif

                <!-- Gestión del Tenant -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-shield-lock"></i> Gestión del Tenant</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-warning btn-sm" id="btnRegeneratePermissions" onclick="regeneratePermissions()">
                                <span class="btn-text"><i class="bi bi-key"></i> Regenerar Permisos</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Regenerando...
                                </span>
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" id="btnRegenerateMenus" onclick="regenerateMenus()">
                                <span class="btn-text"><i class="bi bi-list-nested"></i> Regenerar Menús</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Regenerando...
                                </span>
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" id="btnRegenerateModules" onclick="regenerateModules()">
                                <span class="btn-text"><i class="bi bi-puzzle"></i> Regenerar Módulos</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Regenerando...
                                </span>
                            </button>
                        </div>
                        <div class="form-text mt-2 small">
                            Aplica la configuración de <a href="/musedock/settings/tenant-defaults">tenant-defaults</a> a este tenant.
                            <br><strong>No afecta contraseñas</strong> de usuarios existentes.
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-lightning"></i> Acciones Rápidas</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="https://{{ $tenant->domain }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-box-arrow-up-right"></i> Visitar Sitio
                            </a>
                            <a href="/musedock/tenants/{{ $tenant->id }}/edit" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-building"></i> Editar Tenant Completo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
// Token CSRF para peticiones AJAX
const csrfToken = '<?= csrf_token() ?>';

// Helper para toggle de spinner en botones
function toggleBtnSpinner(btn, loading) {
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    if (loading) {
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        btn.disabled = true;
    } else {
        btnText.classList.remove('d-none');
        btnLoading.classList.add('d-none');
        btn.disabled = false;
    }
}

// Spinner en el formulario de guardar
document.querySelector('form').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnSubmit');
    toggleBtnSpinner(btn, true);
});

async function checkStatus() {
    const btn = document.getElementById('btnCheckStatus');
    toggleBtnSpinner(btn, true);

    // Mostrar modal de SweetAlert2 con loading
    Swal.fire({
        title: '<i class="bi bi-info-circle text-primary"></i> Estado del Dominio',
        html: `
            <div class="text-center py-3">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 mb-0">Verificando...</p>
            </div>
        `,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: '500px'
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/status', {
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
                title: '<i class="bi bi-info-circle text-primary"></i> Estado del Dominio',
                html: `
                    <table class="table table-sm mb-0 text-start">
                        <tr><th>Dominio</th><td>${data.domain}</td></tr>
                        <tr><th>Estado Caddy</th><td><span class="badge bg-info">${data.caddy_status}</span></td></tr>
                        <tr><th>Ruta en Caddy</th><td>${routeStatus}</td></tr>
                        <tr><th>Respuesta HTTPS</th><td>${domainStatus}</td></tr>
                        <tr><th>Certificado SSL</th><td>${sslStatus}</td></tr>
                        ${data.http_code ? `<tr><th>Código HTTP</th><td>${data.http_code}</td></tr>` : ''}
                    </table>
                `,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#0d6efd',
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
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

async function reconfigure() {
    const result = await Swal.fire({
        title: '<i class="bi bi-gear text-primary"></i> Reconfigurar Dominio',
        html: `
            <div class="text-start">
                <p>¿Deseas reconfigurar este dominio en Caddy?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hara lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar la ruta actual de Caddy</li>
                        <li>Crear una nueva ruta con la configuracion actual (incluir www o no)</li>
                        <li>Solicitar un nuevo certificado SSL de Let's Encrypt</li>
                    </ul>
                </div>
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>El proceso puede tardar hasta 60 segundos mientras se genera el certificado.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-gear me-1"></i> Reconfigurar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnReconfigure');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Reconfigurando...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/reconfigure', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Dominio Reconfigurado',
                text: 'El dominio se ha reconfigurado correctamente en Caddy.',
                confirmButtonColor: '#0d6efd'
            }).then(() => window.location.reload());
        } else {
            toggleBtnSpinner(btn, false);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'No se pudo reconfigurar el dominio.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        toggleBtnSpinner(btn, false);
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    }
}

async function regeneratePermissions() {
    const result = await Swal.fire({
        title: '<i class="bi bi-key text-warning"></i> Regenerar Permisos',
        html: `
            <div class="text-start">
                <p>¿Deseas regenerar los permisos de este tenant?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hará lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar los permisos actuales del tenant</li>
                        <li>Crear nuevos permisos según <a href="/musedock/settings/tenant-defaults" target="_blank">tenant-defaults</a></li>
                        <li>Asignar todos los permisos al rol Admin</li>
                    </ul>
                </div>
                <div class="alert alert-success py-2 mb-0">
                    <i class="bi bi-check-circle me-2"></i>
                    <small><strong>NO</strong> afecta las contraseñas de los usuarios existentes.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-key me-1"></i> Regenerar Permisos',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnRegeneratePermissions');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Regenerando permisos...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/regenerate-permissions', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Permisos Regenerados',
                html: `<p>${data.message}</p>`,
                confirmButtonColor: '#0d6efd'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudieron regenerar los permisos.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

async function regenerateMenus() {
    const result = await Swal.fire({
        title: '<i class="bi bi-list-nested text-info"></i> Regenerar Menús',
        html: `
            <div class="text-start">
                <p>¿Deseas regenerar los menús del sidebar de este tenant?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hará lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar todos los menús actuales del tenant</li>
                        <li>Copiar los menús desde <code>admin_menus</code> (panel principal)</li>
                        <li>Mantener la jerarquía de submenús</li>
                    </ul>
                </div>
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>Las personalizaciones de menús del tenant se perderán.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-list-nested me-1"></i> Regenerar Menús',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0dcaf0',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnRegenerateMenus');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Regenerando menús...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/regenerate-menus', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Menús Regenerados',
                html: `<p>${data.message}</p>`,
                confirmButtonColor: '#0d6efd'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudieron regenerar los menús.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

async function regenerateModules() {
    const result = await Swal.fire({
        title: '<i class="bi bi-puzzle text-success"></i> Regenerar Módulos',
        html: `
            <div class="text-start">
                <p>¿Deseas regenerar la configuración de módulos de este tenant?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hará lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar la configuración de módulos actual del tenant</li>
                        <li>Aplicar los módulos activos según <a href="/musedock/settings/tenant-defaults" target="_blank">tenant-defaults</a></li>
                        <li>Solo los módulos marcados como activos aparecerán en el sidebar</li>
                    </ul>
                </div>
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>Los módulos que el tenant haya activado/desactivado manualmente se perderán.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-puzzle me-1"></i> Regenerar Módulos',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnRegenerateModules');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Regenerando módulos...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/regenerate-modules', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Módulos Regenerados',
                html: `<p>${data.message}</p>`,
                confirmButtonColor: '#0d6efd'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudieron regenerar los módulos.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        toggleBtnSpinner(btn, false);
    }
}
</script>
@endpush

@endsection
