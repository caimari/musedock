@extends('layouts.app')

@section('title', __('tenants.title'))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
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

        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('tenants.name') }}</th>
                            <th>{{ __('tenants.domain') }}</th>
                            <th>{{ __('common.created') }}</th>
                            <th>{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tenants as $tenant)
                            <tr>
							<td>{{ $tenant->name }}</td>
							<td>{{ $tenant->domain }}</td>
							<td>
                                @php
                                    $createdAt = $tenant->created_at ? new DateTime($tenant->created_at) : null;
                                @endphp
                                {{ $createdAt ? $createdAt->format($dateFormat . ' ' . $timeFormat) : '-' }}
                            </td>
							<td class="d-flex gap-1">
								<a href="/musedock/tenants/{{ $tenant->id }}/edit" class="btn btn-sm btn-warning">
									<i class="bi bi-pencil"></i> {{ __('common.edit') }}
								</a>

								<button type="button" class="btn btn-sm btn-danger btn-delete-tenant"
									data-tenant-id="{{ $tenant->id }}"
									data-tenant-name="{{ $tenant->name }}">
									<i class="bi bi-trash"></i> {{ __('common.delete') }}
								</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
