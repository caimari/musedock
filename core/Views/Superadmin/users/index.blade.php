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
                            <td>{{ $user['created_at'] ?? '-' }}</td>
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
                            <td>{{ $user['created_at'] ?? '-' }}</td>
                            <td class="d-flex gap-2">
                                <a href="/musedock/users/{{ $user['id'] }}/edit?type=admin" class="btn btn-sm btn-outline-secondary">Editar</a>

                                @if ($isSuperadmin)
                                    <form method="POST" action="/musedock/users/{{ $user['id'] }}/delete" onsubmit="return confirm('¿Eliminar este usuario?')">
                                        {!! csrf_field() !!}
                                        <input type="hidden" name="type" value="admin">
                                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
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
                            <td>{{ $user['created_at'] ?? '-' }}</td>
                            <td class="d-flex gap-2">
                                <a href="/musedock/users/{{ $user['id'] }}/edit?type=user" class="btn btn-sm btn-outline-secondary">Editar</a>

                                @if ($isSuperadmin)
                                    <form method="POST" action="/musedock/users/{{ $user['id'] }}/delete" onsubmit="return confirm('¿Eliminar este usuario?')">
                                        {!! csrf_field() !!}
                                        <input type="hidden" name="type" value="user">
                                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
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
@endsection