@extends('layouts.app')

@section('title', __('tenants.edit'))

@section('content')
<style>
.tenant-page .btn { border-radius:8px;text-decoration:none!important;display:inline-flex;align-items:center;justify-content:center;gap:6px; }
.tenant-page .card { border:1px solid #e5eaf1;border-radius:12px;box-shadow:0 3px 14px rgba(30,50,80,.035);overflow:hidden; }
.tenant-page .card-body { padding:24px; }
.tenant-page .table td,.tenant-page .table th { padding:16px; }
.tenant-page .tenant-actions { display:flex;gap:8px;flex-wrap:wrap; }
.tenant-page .form-control,.tenant-page .form-select { border-radius:7px; }
</style>
<div class="app-content tenant-page"><div class="container-fluid">
    <div class="row">
        <div class="col-12 col-xl-10">

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><h3 class="card-title mb-1">{{ __('tenants.edit') }}</h3><a class="small" href="https://{{ $tenant->domain }}" target="_blank" rel="noopener noreferrer">{{ $tenant->domain }} <i class="bi bi-box-arrow-up-right"></i></a></div>
                    <a href="/musedock/tenants" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Volver
                    </a>
                </div>

                <div class="card-body">
                    @include('partials.alerts')

                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('tenants.name') }}</label>
                        <input type="text" class="form-control" id="name" name="name"
                            value="{{ old('name') ?: $tenant->name }}">
                        {!! form_error('name') !!}
                    </div>

                    <div class="mb-3">
                        <label for="domain" class="form-label">{{ __('tenants.domain') }}</label>
                        <input type="text" class="form-control" id="domain" name="domain"
                            value="{{ old('domain') ?: $tenant->domain }}">
                        {!! form_error('domain') !!}
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">{{ __('tenants.status') }}</label>
                        <select class="form-select" name="status" id="status">
                            <option value="active" {{ (old('status') ?: $tenant->status) === 'active' ? 'selected' : '' }}>
                                {{ __('tenants.active') }}
                            </option>
                            <option value="inactive" {{ (old('status') ?: $tenant->status) === 'inactive' ? 'selected' : '' }}>
                                {{ __('tenants.inactive') }}
                            </option>
                        </select>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3"><i class="bi bi-hdd-stack me-2"></i>Almacenamiento</h5>

                    <div class="mb-3">
                        <label for="storage_quota_mb" class="form-label">Cuota de almacenamiento</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="storage_quota_mb" name="storage_quota_mb"
                                value="{{ old('storage_quota_mb') ?: ($tenant->storage_quota_mb ?? 1024) }}"
                                min="100" max="102400">
                            <span class="input-group-text">MB</span>
                        </div>
                        <small class="text-muted">Espacio máximo de almacenamiento para este tenant (1024 MB = 1 GB)</small>
                    </div>

                    @php
                        $usedBytes = $tenant->storage_used_bytes ?? 0;
                        $quotaMb = $tenant->storage_quota_mb ?? 1024;
                        $quotaBytes = $quotaMb * 1024 * 1024;
                        $percentage = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 1) : 0;
                        $usedFormatted = $usedBytes < 1024 * 1024
                            ? round($usedBytes / 1024, 1) . ' KB'
                            : round($usedBytes / (1024 * 1024), 2) . ' MB';
                    @endphp

                    <div class="mb-3">
                        <label class="form-label">Uso actual</label>
                        <p class="fw-semibold mb-2">{{ $usedFormatted }} / {{ $quotaMb >= 1024 ? round($quotaMb / 1024, 1) . ' GB' : $quotaMb . ' MB' }}</p>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar {{ $percentage > 90 ? 'bg-danger' : ($percentage > 70 ? 'bg-warning' : 'bg-success') }}"
                                role="progressbar"
                                style="width: {{ min($percentage, 100) }}%"
                                aria-valuenow="{{ $percentage }}"
                                aria-valuemin="0"
                                aria-valuemax="100">
                            </div>
                        </div>
                        <small class="text-muted">{{ $percentage }}% del espacio utilizado</small>
                    </div>

                </div>

                <div class="card-footer text-end">
                    <button type="button" class="btn btn-success" id="btnSaveTenant">
                        <i class="bi bi-check-lg me-1"></i>{{ __('common.update') }}
                    </button>
                    <a href="/musedock/tenants" class="btn btn-secondary">{{ __('common.cancel') }}</a>
                </div>
            </div>

            <div class="card mt-4" id="tenant-classification">
                <div class="card-header"><h3 class="h5 mb-0">Clasificación del dominio</h3></div>
                <form class="card-body" method="post" action="/musedock/tenants/{{ $tenant->id }}/classification">
                    {!! csrf_field() !!}<input type="hidden" name="edit_client" value="1">
                    <label class="d-flex gap-2 align-items-center mb-2"><input type="checkbox" name="is_client_domain" value="1" {{ !empty($tenant->is_client_domain) ? 'checked' : '' }}> Dominio de cliente</label>
                    <p class="small text-muted">Marca los sitios de clientes para distinguirlos en el listado. Esta etiqueta no cambia los permisos ni los grupos editoriales.</p>
                    <label for="label-color" class="form-label">Color del dominio</label>
                    <select id="label-color" name="label_color" class="form-select mb-3">@foreach ($labelColors as $value => $label)<option value="{{ $value }}" {{ ($tenant->label_color ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select>
                    <button class="btn btn-primary">Guardar clasificación</button>
                </form>
            </div>

            <div class="card mt-4" id="tenant-plugins">
                <div class="card-header"><h3 class="h5 mb-0"><i class="bi bi-puzzle me-2"></i>Plugins compartidos de este dominio</h3></div>
                <div class="card-body">
                    <p class="text-muted">Instala y activa los plugins compartidos disponibles para este tenant. Los cambios se aplican únicamente a este dominio. Desactivar conserva sus datos y configuración.</p>
                    @php
                        $installedPlugins=[];
                        foreach (($tenantPlugins ?? []) as $plugin) { $plugin=(array)$plugin; $installedPlugins[$plugin['slug']]=$plugin; }
                    @endphp
                    @forelse(($sharedPlugins ?? []) as $plugin)
                        @php $installed=$installedPlugins[$plugin['slug']]??null; $active=!empty($installed['active']); @endphp
                        <div class="border rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div><strong>{{ $plugin['name'] }}</strong> <span class="badge {{ $active ? 'bg-success' : 'bg-secondary' }}">{{ $active ? 'Activo' : ($installed ? 'Inactivo' : 'No instalado') }}</span><p class="small text-muted mb-0 mt-1">{{ $plugin['description'] }}</p></div>
                            <form method="post" action="/musedock/tenants/{{ $tenant->id }}/shared-plugin">
                                {!! csrf_field() !!}<input type="hidden" name="slug" value="{{ $plugin['slug'] }}"><input type="hidden" name="plugin_action" value="{{ $active ? 'deactivate' : 'activate' }}">
                                <button class="btn btn-sm {{ $active ? 'btn-outline-secondary' : 'btn-outline-primary' }}">{{ $active ? 'Desactivar' : ($installed ? 'Activar' : 'Instalar y activar') }}</button>
                            </form>
                        </div>
                    @empty <p class="text-muted mb-0">No hay plugins compartidos disponibles.</p>@endforelse
                </div>
            </div>

        </div>
    </div>
</div></div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= csrf_token() ?>';
    const tenantId = <?= $tenant->id ?>;
    const tenantName = '<?= htmlspecialchars($tenant->name, ENT_QUOTES) ?>';

    // ========== GUARDAR TENANT con SweetAlert2 y verificación de contraseña ==========
    document.getElementById('btnSaveTenant').addEventListener('click', function() {
        // Obtener valores del formulario
        const name = document.getElementById('name').value.trim();
        const domain = document.getElementById('domain').value.trim();
        const status = document.getElementById('status').value;
        const storageQuotaMb = parseInt(document.getElementById('storage_quota_mb').value) || 1024;

        // Validaciones básicas
        if (!name || !domain) {
            Swal.fire({
                icon: 'warning',
                title: 'Campos requeridos',
                text: 'El nombre y dominio son obligatorios.',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        Swal.fire({
            title: '<i class="bi bi-shield-lock text-primary"></i> Confirmar Actualización',
            html: `
                <div class="text-start">
                    <p class="mb-3">Estás a punto de actualizar el tenant <strong>${tenantName}</strong>.</p>
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <small>Por seguridad, se requiere verificación de contraseña para realizar cambios.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Introduce tu contraseña para confirmar:</label>
                        <input type="password" id="updatePassword" class="form-control" placeholder="Contraseña del superadmin" autocomplete="current-password">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Guardar Cambios',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            width: '450px',
            focusConfirm: false,
            didOpen: () => {
                document.getElementById('updatePassword').focus();
            },
            preConfirm: () => {
                const password = document.getElementById('updatePassword').value;
                if (!password) {
                    Swal.showValidationMessage('La contraseña es requerida');
                    return false;
                }
                return password;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Guardando cambios...',
                    html: '<p class="mb-0">Por favor espera...</p>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch(`/musedock/tenants/${tenantId}/update-secure`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        _csrf: csrfToken,
                        password: result.value,
                        name: name,
                        domain: domain,
                        status: status,
                        storage_quota_mb: storageQuotaMb
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Tenant Actualizado',
                            text: data.message,
                            confirmButtonColor: '#0d6efd'
                        }).then(() => window.location.href = '/musedock/tenants');
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
</script>
@endpush

@endsection
