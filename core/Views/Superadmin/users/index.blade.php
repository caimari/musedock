@extends('layouts.app')

@section('title', 'Usuarios del sistema')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Usuarios del sistema</h2>

        @php
            use Screenart\Musedock\Security\PermissionManager;

            $userId = null;
            $tenantId = null;

            if (isset($_SESSION['super_admin'])) {
                $userId = $_SESSION['super_admin']['id'];
                $tenantId = null;
            } elseif (isset($_SESSION['admin'])) {
                $userId = $_SESSION['admin']['id'] ?? null;
                $tenantId = $_SESSION['admin']['tenant_id'] ?? null;
            } elseif (isset($_SESSION['user'])) {
                $userId = $_SESSION['user']['id'] ?? null;
                $tenantId = $_SESSION['user']['tenant_id'] ?? null;
            }

            $isSuperadmin = isset($_SESSION['super_admin']) && ($_SESSION['super_admin']['role'] ?? '') === 'superadmin';

            // Verificar si multitenencia está activada
            $multiTenantEnabled = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
            if ($multiTenantEnabled === null) {
                $multiTenantEnabled = setting('multi_tenant_enabled', false);
            }
        @endphp

        @if ($isSuperadmin || ($userId && PermissionManager::userHasPermission($userId, 'users.create', $tenantId)))
            <a href="/musedock/users/create" class="btn btn-success">
                <i class="bi bi-plus-lg me-1"></i> Nuevo Usuario
            </a>
        @endif
    </div>

    @include('partials.alerts-sweetalert2')

    {{-- Super Admins --}}
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <strong>Super Admins</strong>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        @if($multiTenantEnabled)
                            <th>Tenant</th>
                        @endif
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($superAdmins as $user)
                        <tr>
                            <td>{{ $user['name'] ?? '-' }}</td>
                            <td>{{ $user['email'] }}</td>
                            <td>
                                @if(!empty($user['is_root']))
                                    <span class="badge bg-danger" title="Acceso completo a todas las funciones">
                                        <i class="bi bi-shield-check me-1"></i> Root
                                    </span>
                                @else
                                    <span class="badge bg-secondary" title="Permisos basados en roles asignados">
                                        <i class="bi bi-person-badge me-1"></i> Con roles
                                    </span>
                                @endif
                            </td>
                            @if($multiTenantEnabled)
                                <td>Global</td>
                            @endif
                            <td>
                                @php
                                    $createdAt = !empty($user['created_at']) ? new DateTime($user['created_at']) : null;
                                @endphp
                                {{ $createdAt ? $createdAt->format($dateFormat . ' ' . $timeFormat) : '-' }}
                            </td>
                            <td>
                                <a href="/musedock/users/{{ $user['id'] }}/edit?type=superadmin" class="btn btn-sm btn-outline-secondary">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $multiTenantEnabled ? 6 : 5 }}">No hay super admins</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Admins (Solo visible con multitenencia) --}}
    @if($multiTenantEnabled)
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <strong>Admins (Tenant)</strong>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Tenant</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($admins as $user)
                        <tr>
                            <td>{{ $user['name'] }}</td>
                            <td>{{ $user['email'] }}</td>
                            <td>
                                @if(isset($user['tenant_id']) && !empty($user['tenant_id']))
                                    @if(isset($user['tenant_name']))
                                        {{ $user['tenant_name'] }}
                                    @elseif(isset($user['tenant_domain']))
                                        {{ $user['tenant_domain'] }}
                                    @else
                                        {{ $user['tenant_id'] }}
                                    @endif
                                @else
                                    Global
                                @endif
                            </td>
                            <td>
                                @php
                                    $createdAt = !empty($user['created_at']) ? new DateTime($user['created_at']) : null;
                                @endphp
                                {{ $createdAt ? $createdAt->format($dateFormat . ' ' . $timeFormat) : '-' }}
                            </td>
                            <td class="d-flex gap-2">
                                <a href="/musedock/users/{{ $user['id'] }}/edit?type=admin" class="btn btn-sm btn-outline-secondary">Editar</a>

                                @if ($isSuperadmin)
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-user"
                                        data-user-id="{{ $user['id'] }}"
                                        data-user-name="{{ $user['name'] }}"
                                        data-user-type="admin">
                                        Eliminar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No hay admins</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- CMS Users (Solo visible con multitenencia) --}}
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <strong>Usuarios (CMS / Tenant)</strong>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Tenant</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user['name'] }}</td>
                            <td>{{ $user['email'] }}</td>
                            <td>
                                @if(isset($user['tenant_id']) && !empty($user['tenant_id']))
                                    @if(isset($user['tenant_name']))
                                        {{ $user['tenant_name'] }}
                                    @elseif(isset($user['tenant_domain']))
                                        {{ $user['tenant_domain'] }}
                                    @else
                                        {{ $user['tenant_id'] }}
                                    @endif
                                @else
                                    Global
                                @endif
                            </td>
                            <td>
                                @php
                                    $createdAt = !empty($user['created_at']) ? new DateTime($user['created_at']) : null;
                                @endphp
                                {{ $createdAt ? $createdAt->format($dateFormat . ' ' . $timeFormat) : '-' }}
                            </td>
                            <td class="d-flex gap-2">
                                <a href="/musedock/users/{{ $user['id'] }}/edit?type=user" class="btn btn-sm btn-outline-secondary">Editar</a>

                                @if ($isSuperadmin)
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-user"
                                        data-user-id="{{ $user['id'] }}"
                                        data-user-name="{{ $user['name'] }}"
                                        data-user-type="user">
                                        Eliminar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No hay usuarios</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= csrf_token() ?>';

    // ========== ELIMINAR USUARIO con SweetAlert2 y verificación de contraseña ==========
    document.querySelectorAll('.btn-delete-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            const userType = this.dataset.userType;

            Swal.fire({
                title: '<i class="bi bi-exclamation-triangle text-danger"></i> Confirmar Eliminación',
                html: `
                    <div class="text-start">
                        <p class="mb-3">¿Estás seguro de eliminar el usuario <strong>${userName}</strong>?</p>
                        <div class="alert alert-danger py-2 mb-3">
                            <i class="bi bi-trash me-2"></i>
                            <small><strong>Esta acción no se puede deshacer.</strong> Se eliminarán todos los roles y permisos asociados.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Introduce tu contraseña para confirmar:</label>
                            <input type="password" id="deletePassword" class="form-control" placeholder="Contraseña del superadmin" autocomplete="current-password">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar Usuario',
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
                        title: 'Eliminando usuario...',
                        html: '<p class="mb-0">Por favor espera...</p>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch(`/musedock/users/${userId}/delete-secure`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            _csrf: csrfToken,
                            password: result.value,
                            type: userType
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Usuario Eliminado',
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