@extends('layouts.app')
@section('title', $title)
@section('content')
@php
    // Calcular permisos del código y BD en un solo lugar para consistencia
    $codePermissions = \Screenart\Musedock\Helpers\PermissionScanner::scanCodePermissions();
    $scanStats = \Screenart\Musedock\Helpers\PermissionScanner::getScanStats();
    $dbSlugs = array_column(array_map(function($p) { return (array)$p; }, $permissions), 'slug');

    // Calcular permisos faltantes en BD (para botón sincronizar)
    $codeSlugs = array_keys($codePermissions);
    $permsMissingInDb = array_values(array_diff($codeSlugs, $dbSlugs));

    // Detectar permisos DUPLICADOS en BD
    $slugCounts = array_count_values($dbSlugs);
    $duplicateSlugs = array_filter($slugCounts, fn($count) => $count > 1);

    // Detectar permisos HUÉRFANOS (en BD pero NO en código)
    $orphanSlugs = array_values(array_diff(array_unique($dbSlugs), $codeSlugs));
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>{{ $title }}</h2>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-info" data-bs-toggle="collapse" data-bs-target="#codePermissions" title="Ver permisos detectados en el código">
                <i class="bi bi-code-slash"></i> Escanear Código
            </button>
            @if(!empty($permsMissingInDb))
            <form method="POST" action="/musedock/permissions/sync" class="d-inline">
                {!! csrf_field() !!}
                <button type="submit" class="btn btn-warning" title="Crear permisos faltantes automáticamente">
                    <i class="bi bi-arrow-repeat"></i> Sincronizar ({{ count($permsMissingInDb) }})
                </button>
            </form>
            @else
            <span class="btn btn-outline-success disabled" title="Todos los permisos del código están en la BD">
                <i class="bi bi-check-circle"></i> Sincronizado ({{ count($codeSlugs) }})
            </span>
            @endif
            <a href="/musedock/permissions/create" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Nuevo Permiso
            </a>
        </div>
    </div>

    {{-- ESTADO DE SINCRONIZACIÓN --}}
    <div class="alert alert-light border mb-3 py-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small>
                <i class="bi bi-info-circle me-1"></i>
                <strong>Estado:</strong>
                Código: {{ count($codeSlugs) }} |
                BD: {{ count($dbSlugs) }} ({{ count(array_unique($dbSlugs)) }} únicos) |
                @if(count($permsMissingInDb) > 0)
                    <span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Faltan {{ count($permsMissingInDb) }}</span> |
                @endif
                @if(count($duplicateSlugs) > 0)
                    <span class="text-danger"><i class="bi bi-files"></i> {{ count($duplicateSlugs) }} duplicados</span> |
                @endif
                @if(count($orphanSlugs) > 0)
                    <span class="text-secondary"><i class="bi bi-trash"></i> {{ count($orphanSlugs) }} huérfanos</span>
                @else
                    <span class="text-success"><i class="bi bi-check-circle"></i> Limpio</span>
                @endif
            </small>
            <div class="d-flex gap-2">
                @if(count($duplicateSlugs) > 0)
                <form method="POST" action="/musedock/permissions/cleanup-duplicates" class="d-inline" onsubmit="return confirm('¿Eliminar {{ array_sum($duplicateSlugs) - count($duplicateSlugs) }} permisos duplicados? Se mantendrá solo el más reciente de cada slug.')">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar duplicados, mantener el más reciente">
                        <i class="bi bi-files"></i> Limpiar duplicados
                    </button>
                </form>
                @endif
                @if(count($orphanSlugs) > 0)
                <form method="POST" action="/musedock/permissions/cleanup-orphans" class="d-inline" onsubmit="return confirm('¿Eliminar {{ count($orphanSlugs) }} permisos huérfanos? Estos permisos no se usan en ningún controlador.')">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Eliminar permisos que no están en el código">
                        <i class="bi bi-trash"></i> Limpiar huérfanos
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ALERTA DE DUPLICADOS --}}
    @if(count($duplicateSlugs) > 0)
    <div class="alert alert-danger mb-3">
        <h6 class="alert-heading"><i class="bi bi-files me-2"></i>Permisos duplicados detectados</h6>
        <p class="mb-2 small">Los siguientes slugs tienen múltiples registros en la BD:</p>
        <div class="d-flex flex-wrap gap-2">
            @foreach($duplicateSlugs as $slug => $count)
                <code class="bg-light text-danger px-2 py-1 rounded">{{ $slug }} (×{{ $count }})</code>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ALERTA DE HUÉRFANOS --}}
    @if(count($orphanSlugs) > 0)
    <div class="alert alert-secondary mb-3">
        <h6 class="alert-heading"><i class="bi bi-trash me-2"></i>Permisos huérfanos (no usados en código)</h6>
        <p class="mb-2 small">Estos permisos existen en la BD pero no están definidos en ningún controlador:</p>
        <div class="d-flex flex-wrap gap-2" style="max-height: 150px; overflow-y: auto;">
            @foreach($orphanSlugs as $slug)
                <code class="bg-light text-muted px-2 py-1 rounded">{{ $slug }}</code>
            @endforeach
        </div>
    </div>
    @endif

    {{-- PANEL COLAPSABLE: Permisos del código --}}
    <div class="collapse mb-4" id="codePermissions">
        <div class="card card-body bg-light">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-code-slash me-2"></i>Permisos detectados en controladores ({{ count($codePermissions) }} total)</h6>
                <div class="d-flex gap-2">
                    @if($scanStats['multi_tenant_enabled'])
                        <span class="badge bg-info" title="Multi-tenant activo: escaneando Admin, Tenant y Módulos">
                            <i class="bi bi-building me-1"></i>Multi-tenant ON
                        </span>
                    @else
                        <span class="badge bg-secondary" title="Multi-tenant inactivo: solo escaneando Superadmin">
                            <i class="bi bi-building me-1"></i>Multi-tenant OFF
                        </span>
                    @endif
                    <span class="badge bg-dark">{{ $scanStats['directories_scanned'] }} directorios</span>
                </div>
            </div>

            {{-- Estadísticas por tipo --}}
            <div class="row mb-3 g-2">
                <div class="col-auto">
                    <span class="badge bg-primary">Superadmin: {{ $scanStats['by_type']['superadmin'] }}</span>
                </div>
                @if($scanStats['multi_tenant_enabled'])
                <div class="col-auto">
                    <span class="badge bg-info">Admin (Tenant): {{ $scanStats['by_type']['admin'] }}</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-secondary">Tenant: {{ $scanStats['by_type']['tenant'] }}</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-dark">Módulos: {{ $scanStats['by_type']['module'] }}</span>
                </div>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2">
                @foreach($codePermissions as $slug => $info)
                    @php
                        $typeIcons = [
                            'superadmin' => 'bi-shield-lock',
                            'admin' => 'bi-person-gear',
                            'tenant' => 'bi-building',
                            'module' => 'bi-puzzle',
                        ];
                        $typeClass = in_array('superadmin', $info['types'] ?? []) ? 'primary' :
                                    (in_array('admin', $info['types'] ?? []) ? 'info' :
                                    (in_array('module', $info['types'] ?? []) ? 'dark' : 'secondary'));
                        $typeIcon = $typeIcons[$info['types'][0] ?? 'superadmin'] ?? 'bi-code';
                        $inDb = in_array($slug, $dbSlugs);
                    @endphp
                    @if($inDb)
                        <span class="badge bg-success" title="Ya existe en BD | Tipo: {{ implode(', ', $info['types'] ?? []) }}">
                            <i class="bi bi-check-circle me-1"></i>{{ $slug }}
                        </span>
                    @else
                        <span class="badge bg-warning text-dark" title="Falta en BD | Tipo: {{ implode(', ', $info['types'] ?? []) }}">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $slug }}
                        </span>
                    @endif
                @endforeach
            </div>
            @if(empty($codePermissions))
                <p class="text-muted mb-0">No se detectaron permisos en los controladores.</p>
            @else
                <hr>
                <small class="text-muted">
                    <span class="badge bg-success">Verde</span> = Ya existe en BD |
                    <span class="badge bg-warning text-dark">Amarillo</span> = Falta en BD (usar botón Sincronizar)
                    @if(!$scanStats['multi_tenant_enabled'])
                    <br><i class="bi bi-info-circle me-1"></i>Activa <code>MULTI_TENANT_ENABLED=true</code> en .env para escanear controladores de Admin/Tenant/Módulos
                    @endif
                </small>
            @endif
        </div>
    </div>

    @php
        // Verificar si multitenencia está activada
        $multiTenantEnabled = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($multiTenantEnabled === null) {
            $multiTenantEnabled = setting('multi_tenant_enabled', false);
        }

        // Separar permisos globales y de tenant específico
        $globalPermissions = array_filter($permissions, function($perm) {
            return empty($perm['tenant_id']);
        });
        $tenantSpecificPermissions = array_filter($permissions, function($perm) {
            return !empty($perm['tenant_id']);
        });

        // Obtener permisos agrupados por tipo de controlador
        $permissionsByType = \Screenart\Musedock\Helpers\PermissionScanner::getPermissionsByType();

        // Permisos usados en controladores de Tenant (aunque sean globales)
        $tenantControllerSlugs = array_keys($permissionsByType['tenant'] ?? []);

        // Filtrar permisos globales que se usan en controllers de tenant
        $tenantUsedPermissions = array_filter($globalPermissions, function($perm) use ($tenantControllerSlugs) {
            return in_array($perm['slug'] ?? '', $tenantControllerSlugs);
        });

        // Permisos globales que NO se usan en tenant (solo superadmin/modules)
        $nonTenantGlobalPermissions = array_filter($globalPermissions, function($perm) use ($tenantControllerSlugs) {
            return !in_array($perm['slug'] ?? '', $tenantControllerSlugs);
        });
    @endphp

    @include('partials.alerts-sweetalert2')

    {{-- ALERTA CRÍTICA: MÉTODOS SIN PROTECCIÓN --}}
    @if(!empty($unprotectedMethods))
    <div class="alert alert-danger mb-4">
        <h6 class="alert-heading"><i class="bi bi-shield-exclamation me-2"></i>SEGURIDAD: Métodos sin checkPermission()</h6>
        <p class="mb-2">Los siguientes métodos de controladores <strong>no tienen verificación de permisos</strong> y están siendo <strong>bloqueados automáticamente</strong>:</p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($unprotectedMethods as $methodId)
                <code class="bg-light text-danger px-2 py-1 rounded border border-danger">{{ $methodId }}</code>
            @endforeach
        </div>
        <hr>
        <p class="mb-0 small">
            <strong>Acción requerida:</strong> Un desarrollador debe agregar <code>$this->checkPermission('slug')</code> a cada método,
            o agregarlos a la whitelist si no requieren permisos (ej: login, dashboard).
        </p>
    </div>
    @endif

    {{-- ALERTA DE PERMISOS FALTANTES --}}
    @if(!empty($permsMissingInDb))
    <div class="alert alert-warning mb-4">
        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Permisos detectados en el código pero no en la BD</h6>
        <p class="mb-2">Los siguientes permisos están siendo usados en los controladores pero no existen en la base de datos:</p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($permsMissingInDb as $slug)
                <code class="bg-light px-2 py-1 rounded">{{ $slug }}</code>
            @endforeach
        </div>
        <hr>
        <p class="mb-0 small">
            <strong>Solución:</strong> Haz clic en <strong>"Sincronizar"</strong> para crear estos permisos automáticamente,
            o créalos manualmente con el botón "Nuevo Permiso".
        </p>
    </div>
    @endif

    {{-- EXPLICACIÓN DEL SISTEMA DE PERMISOS --}}
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
            <i class="bi bi-info-circle me-2"></i><strong>¿Cómo funciona el sistema de permisos?</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="bi bi-code-slash me-2 text-primary"></i>¿Dónde se definen los permisos?</h6>
                    <p class="small text-muted mb-3">
                        Los permisos están definidos <strong>en el código de los controladores</strong> por el desarrollador.
                        Cada función protegida tiene una llamada <code>$this->checkPermission('slug')</code> que verifica
                        si el usuario tiene ese permiso antes de ejecutar la acción.
                    </p>
                    <p class="small text-muted mb-0">
                        <strong>Ejemplo:</strong> Si una función tiene <code>checkPermission('pages.create')</code>,
                        solo los usuarios con el permiso <code>pages.create</code> podrán acceder a ella.
                    </p>
                </div>
                <div class="col-md-6">
                    <h6><i class="bi bi-people me-2 text-success"></i>¿Qué son los roles?</h6>
                    <p class="small text-muted mb-3">
                        Los <strong>Roles</strong> son <strong>agrupaciones de permisos</strong>. En lugar de asignar
                        permisos uno por uno a cada usuario, se crean roles (ej: "Editor", "Moderador", "Admin")
                        y se asignan los permisos necesarios a cada rol.
                    </p>
                    <p class="small text-muted mb-0">
                        <strong>Flujo:</strong> <span class="badge bg-primary">Permiso</span>
                        <i class="bi bi-arrow-right mx-1"></i> asignado a <span class="badge bg-success">Rol</span>
                        <i class="bi bi-arrow-right mx-1"></i> Rol asignado a <span class="badge bg-secondary">Usuario</span>
                    </p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-12">
                    <h6><i class="bi bi-shield-check me-2 text-warning"></i>Seguridad "Deny by Default"</h6>
                    <p class="small text-muted mb-0">
                        El sistema implementa <strong>denegación por defecto</strong>: cualquier función de controlador
                        que <em>no tenga</em> <code>checkPermission()</code> será <strong>bloqueada automáticamente</strong>.
                        Esto garantiza que ninguna función quede accidentalmente sin protección.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- PERMISOS USADOS EN TENANT CONTROLLERS --}}
    @if($multiTenantEnabled && count($tenantUsedPermissions) > 0)
    <div class="card mb-4">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-building me-2"></i>Permisos para Paneles de Tenant</strong>
            <span class="badge bg-light text-dark">{{ count($tenantUsedPermissions) }}</span>
        </div>
        <div class="card-body p-3 bg-light">
            <p class="small text-muted mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Estos permisos se usan en <code>Controllers/Tenant/</code> y son los que debes asignar a los roles admin de cada tenant.
                Son permisos globales (sin tenant_id específico) pero aplicables a todos los tenants.
            </p>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Slug</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Usado en</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenantUsedPermissions as $perm)
                        @php
                            $slugInfo = $permissionsByType['tenant'][$perm['slug']] ?? null;
                            $files = $slugInfo ? array_filter($slugInfo['files'], fn($f) => str_contains($f, 'Tenant')) : [];
                        @endphp
                        <tr>
                            <td>{{ $perm['id'] }}</td>
                            <td><code class="text-info">{{ $perm['slug'] ?? '-' }}</code></td>
                            <td>{{ $perm['name'] }}</td>
                            <td class="text-muted small">{{ $perm['description'] }}</td>
                            <td>
                                @if(!empty($perm['category']))
                                    <span class="badge bg-secondary">{{ $perm['category'] }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="small">
                                @if(!empty($files))
                                    <span class="text-muted" title="{{ implode(', ', array_slice($files, 0, 5)) }}">
                                        {{ count($files) }} archivo(s)
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="d-flex gap-2">
                                <a href="/musedock/permissions/{{ $perm['id'] }}/edit" class="btn btn-sm btn-outline-secondary">
                                    Editar
                                </a>
                                <form method="POST" action="/musedock/permissions/{{ $perm['id'] }}/delete" onsubmit="return confirm('¿Eliminar este permiso? Se eliminará de todos los roles.')">
                                    {!! csrf_field() !!}
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- PERMISOS GLOBALES (Solo Superadmin/Módulos) --}}
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-globe me-2"></i>Permisos Globales (Superadmin/Módulos)</strong>
            <span class="badge bg-light text-dark">{{ count($nonTenantGlobalPermissions) }}</span>
        </div>
        @if($multiTenantEnabled)
        <div class="card-body p-3 bg-light">
            <p class="small text-muted mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Estos permisos se usan solo en el panel Superadmin (<code>/musedock</code>) y módulos globales.
                No aplican a los paneles de administración de tenants.
            </p>
        </div>
        @endif
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Slug</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($nonTenantGlobalPermissions as $perm)
                        <tr>
                            <td>{{ $perm['id'] }}</td>
                            <td><code class="text-primary">{{ $perm['slug'] ?? '-' }}</code></td>
                            <td>{{ $perm['name'] }}</td>
                            <td class="text-muted small">{{ $perm['description'] }}</td>
                            <td>
                                @if(!empty($perm['category']))
                                    <span class="badge bg-secondary">{{ $perm['category'] }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="small text-nowrap">{{ format_datetime($perm['created_at']) }}</td>
                            <td class="d-flex gap-2">
                                <a href="/musedock/permissions/{{ $perm['id'] }}/edit" class="btn btn-sm btn-outline-secondary">
                                    Editar
                                </a>
                                <form method="POST" action="/musedock/permissions/{{ $perm['id'] }}/delete" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este permiso? También se eliminará de los roles que lo tengan asignado.')">
                                    {!! csrf_field() !!}
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="bi bi-inbox text-muted d-block" style="font-size: 2rem;"></i>
                                <p class="text-muted mb-0">No hay permisos globales de superadmin/módulos.</p>
                                @if(!empty($permsMissingInDb))
                                    <p class="text-muted small">Usa "Sincronizar" para crear los permisos del código automáticamente.</p>
                                @else
                                    <a href="/musedock/permissions/create" class="btn btn-sm btn-primary mt-2">Crear primer permiso</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- PERMISOS ESPECÍFICOS DE TENANT (permisos personalizados para un tenant concreto) --}}
    @if($multiTenantEnabled)
    <div class="card">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-building-gear me-2"></i>Permisos Específicos por Tenant</strong>
            <span class="badge bg-light text-dark">{{ count($tenantSpecificPermissions) }}</span>
        </div>
        <div class="card-body p-3 bg-light">
            <p class="small text-muted mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Estos son permisos <strong>personalizados</strong> creados específicamente para un tenant individual.
                Solo aplican a usuarios de ese tenant concreto. Normalmente no necesitas crear permisos aquí;
                los permisos de la sección "Paneles de Tenant" son suficientes para la mayoría de casos.
            </p>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Slug</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Tenant</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenantSpecificPermissions as $perm)
                        <tr>
                            <td>{{ $perm['id'] }}</td>
                            <td><code class="text-secondary">{{ $perm['slug'] ?? '-' }}</code></td>
                            <td>{{ $perm['name'] }}</td>
                            <td class="text-muted small">{{ $perm['description'] }}</td>
                            <td>
                                @if(!empty($perm['category']))
                                    <span class="badge bg-secondary">{{ $perm['category'] }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-warning text-dark">
                                    @if(!empty($perm['tenant_domain']))
                                        {{ $perm['tenant_domain'] }}
                                    @elseif(!empty($perm['tenant_name']))
                                        {{ $perm['tenant_name'] }}
                                    @else
                                        Tenant #{{ $perm['tenant_id'] }}
                                    @endif
                                </span>
                            </td>
                            <td class="small text-nowrap">{{ format_datetime($perm['created_at']) }}</td>
                            <td class="d-flex gap-2">
                                <a href="/musedock/permissions/{{ $perm['id'] }}/edit" class="btn btn-sm btn-outline-secondary">
                                    Editar
                                </a>
                                <form method="POST" action="/musedock/permissions/{{ $perm['id'] }}/delete" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este permiso?')">
                                    {!! csrf_field() !!}
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi bi-check-circle text-success d-block" style="font-size: 2rem;"></i>
                                <p class="text-muted mb-0">No hay permisos personalizados por tenant.</p>
                                <small class="text-muted">Esto es normal. Usa los permisos globales de "Paneles de Tenant" para gestionar accesos.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @elseif(count($tenantSpecificPermissions) > 0)
    {{-- Mostrar advertencia si hay permisos de tenant pero multitenencia está desactivada --}}
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Nota:</strong> Existen {{ count($tenantSpecificPermissions) }} permiso(s) asignados a tenants específicos, pero la multitenencia está desactivada.
        Estos permisos no estarán activos hasta que se reactive la multitenencia en <code>.env</code> (MULTI_TENANT_ENABLED=true).
    </div>
    @endif
</div>
@endsection
