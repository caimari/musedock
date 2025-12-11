@extends('layouts.app')

@section('title', __('tenants.edit'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">{{ __('tenants.edit') }}</h3>
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

                </div>

                <div class="card-footer text-end">
                    <button type="button" class="btn btn-success" id="btnSaveTenant">
                        <i class="bi bi-check-lg me-1"></i>{{ __('common.update') }}
                    </button>
                    <a href="/musedock/tenants" class="btn btn-secondary">{{ __('common.cancel') }}</a>
                </div>
            </div>

        </div>
    </div>
</div>

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
                        status: status
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
