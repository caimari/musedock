@extends('layouts.app')

@section('title', 'Domain Manager')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <h2 class="mb-0"><i class="bi bi-globe2"></i> Domain Manager</h2>
                <p class="text-muted mb-0 small">Gestiona los dominios custom de tus tenants e integra con Caddy Server</p>
            </div>
            <div>
                @if($caddyApiAvailable)
                    <span class="badge bg-success fs-6"><i class="bi bi-check-circle"></i> Caddy API</span>
                @else
                    <span class="badge bg-danger fs-6"><i class="bi bi-x-circle"></i> Caddy API</span>
                @endif
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="/musedock/plugins/caddy-domain-manager/cloudflare-accounts" class="btn btn-outline-warning">
                <i class="bi bi-cloud-fill"></i> Cuentas Cloudflare
            </a>
            <a href="/musedock/domain-manager/customers" class="btn btn-outline-primary">
                <i class="bi bi-people"></i> Clientes
            </a>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-success" onclick="showCreateFreeSubdomainModal()">
                    <i class="bi bi-gift"></i> Crear Subdominio FREE
                </button>
                <div class="btn-group">
                    <a href="/musedock/domain-manager/create" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Nuevo Dominio
                    </a>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Opciones</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/musedock/domain-manager/create"><i class="bi bi-globe2 me-2"></i> Dominio (Tenant)</a></li>
                        <li><a class="dropdown-item" href="/musedock/domain-manager/create-alias"><i class="bi bi-link-45deg me-2"></i> Alias de Dominio</a></li>
                        <li><a class="dropdown-item" href="/musedock/domain-manager/create-redirect"><i class="bi bi-arrow-right-circle me-2"></i> Redirección</a></li>
                    </ul>
                </div>
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
                        <label class="form-label">Tipo</label>
                        <select name="domain_type" class="form-select">
                            <option value="">Todos</option>
                            <option value="musedock" {{ ($filters['domain_type'] ?? '') === 'musedock' ? 'selected' : '' }}>{{ $baseDomain ?? 'musedock.com' }}</option>
                            <option value="custom" {{ ($filters['domain_type'] ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-filter"></i>
                        </button>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <a href="/musedock/domain-manager" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-lg"></i>
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
                            <th>Admin</th>
                            <th>Tipo</th>
                            <th>Estado Caddy</th>
                            <th>SSL</th>
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
                                        <small class="text-muted">+ www</small>
                                    @endif
                                    @if(($aliasCounts[$tenant->id] ?? 0) > 0)
                                        <br><small class="text-info"><i class="bi bi-link-45deg"></i> {{ $aliasCounts[$tenant->id] }} alias</small>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($tenantAdminEmails[$tenant->id]))
                                        <small>{{ $tenantAdminEmails[$tenant->id]->email }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(str_ends_with($tenant->domain, '.' . ($baseDomain ?? 'musedock.com')))
                                        <span class="badge bg-info">{{ $baseDomain ?? 'musedock.com' }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Custom</span>
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
                                <td colspan="8" class="text-center text-muted py-4">
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
                                        <!-- Eliminar orden de dominio -->
                                        @if(!empty($order->id))
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteDomainOrder({{ $order->id }}, '{{ $fullDomain }}', {{ !empty($order->cloudflare_zone_id) ? 'true' : 'false' }})" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

        <!-- Alias de Dominio -->
        <div class="card mt-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="bi bi-link-45deg"></i> Alias de Dominio</h5>
                    <small class="text-muted">Dominios adicionales que apuntan a un tenant existente</small>
                </div>
                <a href="/musedock/domain-manager/create-alias" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg"></i> Nuevo Alias
                </a>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Dominio Alias</th>
                            <th>Tenant</th>
                            <th>WWW</th>
                            <th>Estado</th>
                            <th>Caddy</th>
                            <th>Cloudflare</th>
                            <th>Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allAliases ?? [] as $alias)
                            <tr>
                                <td>
                                    <a href="https://{{ $alias->domain }}" target="_blank" class="text-decoration-none fw-semibold">
                                        {{ $alias->domain }}
                                        <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                </td>
                                <td>
                                    @if(!empty($alias->tenant_name))
                                        <a href="/musedock/domain-manager/{{ $alias->tenant_id }}/edit" class="text-decoration-none">
                                            {{ $alias->tenant_name }}
                                        </a>
                                        <br><small class="text-muted">{{ $alias->tenant_domain ?? '' }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($alias->include_www ?? false)
                                        <span class="badge bg-info">Sí</span>
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $aliasStatusClass = match($alias->status ?? 'pending') {
                                            'active' => 'success',
                                            'pending' => 'warning',
                                            'error' => 'danger',
                                            'suspended' => 'secondary',
                                            default => 'dark'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $aliasStatusClass }}">{{ ucfirst($alias->status ?? 'pending') }}</span>
                                </td>
                                <td>
                                    @if($alias->caddy_configured ?? false)
                                        <span class="text-success"><i class="bi bi-check-circle"></i></span>
                                    @else
                                        <span class="text-muted"><i class="bi bi-x-circle"></i></span>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($alias->cloudflare_zone_id))
                                        <span class="badge bg-warning text-dark"><i class="bi bi-shield-fill-check"></i></span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @if(!empty($alias->created_at))
                                        {{ date('d/m/Y', strtotime($alias->created_at)) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/musedock/domain-manager/alias/{{ $alias->id }}/edit" class="btn btn-outline-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteAlias({{ $alias->id }}, '{{ $alias->domain }}', {{ $alias->is_subdomain ? 'true' : 'false' }}, {{ !empty($alias->cloudflare_zone_id) ? 'true' : 'false' }}, {{ !empty($alias->cloudflare_record_id) ? 'true' : 'false' }})" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                    No hay alias de dominio configurados.
                                    <br><a href="/musedock/domain-manager/create-alias">Crear el primero</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Redirecciones de Dominio -->
        <div class="card mt-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="bi bi-arrow-right-circle"></i> Redirecciones de Dominio</h5>
                    <small class="text-muted">Dominios que redirigen (301/302) a otra URL</small>
                </div>
                <a href="/musedock/domain-manager/create-redirect" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg"></i> Nueva Redirección
                </a>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Dominio Origen</th>
                            <th>Destino</th>
                            <th>Tipo</th>
                            <th>WWW</th>
                            <th>Path</th>
                            <th>Estado</th>
                            <th>Caddy</th>
                            <th>Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($domainRedirects ?? [] as $redir)
                            <tr>
                                <td>
                                    <a href="https://{{ $redir->domain }}" target="_blank" class="text-decoration-none fw-semibold">
                                        {{ $redir->domain }}
                                        <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ $redir->redirect_to }}" target="_blank" class="text-decoration-none">
                                        {{ $redir->redirect_to }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge {{ $redir->redirect_type == 301 ? 'bg-primary' : 'bg-secondary' }}">
                                        {{ $redir->redirect_type }}
                                    </span>
                                </td>
                                <td>
                                    @if($redir->include_www ?? false)
                                        <span class="badge bg-info">Sí</span>
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                                <td>
                                    @if($redir->preserve_path ?? false)
                                        <span class="text-success"><i class="bi bi-check-circle"></i></span>
                                    @else
                                        <span class="text-muted"><i class="bi bi-x-circle"></i></span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $redirStatusClass = match($redir->status ?? 'pending') {
                                            'active' => 'success',
                                            'pending' => 'warning',
                                            'error' => 'danger',
                                            'suspended' => 'secondary',
                                            default => 'dark'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $redirStatusClass }}">{{ ucfirst($redir->status ?? 'pending') }}</span>
                                    @if($redir->error_log ?? false)
                                        <br><small class="text-danger" title="{{ $redir->error_log }}"><i class="bi bi-exclamation-triangle"></i></small>
                                    @endif
                                </td>
                                <td>
                                    @if($redir->caddy_configured ?? false)
                                        <span class="text-success"><i class="bi bi-check-circle"></i></span>
                                    @else
                                        <span class="text-muted"><i class="bi bi-x-circle"></i></span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @if(!empty($redir->created_at))
                                        {{ date('d/m/Y', strtotime($redir->created_at)) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/musedock/domain-manager/redirect/{{ $redir->id }}/edit" class="btn btn-outline-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteRedirect({{ $redir->id }}, '{{ $redir->domain }}')" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                    No hay redirecciones configuradas.
                                    <br><a href="/musedock/domain-manager/create-redirect">Crear la primera</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- Cloudflare token data for JS --}}
@php $currentCfToken = \Screenart\Musedock\Env::get('CLOUDFLARE_API_TOKEN', ''); @endphp
<script>const CF_CURRENT_TOKEN = '{{ $currentCfToken }}'; const CF_MASKED = '{{ $currentCfToken ? substr($currentCfToken, 0, 8) . "..." . substr($currentCfToken, -4) : "No configurado" }}';</script>

@push('scripts')
<script>
const csrfToken = '<?= csrf_token() ?>';

// ── Cloudflare Token Management (SweetAlert2) ──

async function openCloudflareSettings() {
    const { value: action } = await Swal.fire({
        title: '<i class="bi bi-cloud-fill" style="color:#f48120"></i> Cloudflare API Token',
        html: `
            <div class="text-start">
                <div class="alert alert-light border small py-2 mb-3">
                    <i class="bi bi-shield-check text-primary me-1"></i>
                    Este token permite a <strong>Caddy</strong> generar certificados SSL via <strong>DNS-01</strong> para dominios alias.
                    Es imprescindible cuando el dominio usa proxy Cloudflare <span class="badge bg-warning text-dark" style="font-size:.65rem">naranja</span>.
                    <br><small class="text-muted">Haz clic en <strong>Guia</strong> para mas detalles.</small>
                </div>
                <div class="mb-3 p-2 bg-light rounded border">
                    <small class="text-muted d-block mb-1"><i class="bi bi-key me-1"></i>Token actual</small>
                    <code>${CF_MASKED}</code>
                </div>
                <label class="form-label fw-semibold small">Nuevo token (dejar vacio para solo verificar el actual)</label>
                <input type="password" id="swal-cf-token" class="form-control" placeholder="Pega aqui el nuevo API Token">
            </div>`,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="bi bi-search me-1"></i> Verificar',
        denyButtonText: '<i class="bi bi-question-circle me-1"></i> Guia',
        cancelButtonText: 'Cerrar',
        confirmButtonColor: '#0d6efd',
        denyButtonColor: '#6c757d',
        focusConfirm: false,
        preConfirm: () => {
            const token = document.getElementById('swal-cf-token').value.trim();
            return { action: 'verify', token: token || CF_CURRENT_TOKEN };
        },
        preDeny: () => { return { action: 'guide' }; }
    });

    if (!action) return;

    if (action.action === 'guide') {
        await showCfGuide();
        return openCloudflareSettings();
    }

    if (action.action === 'verify') {
        await verifyCfTokenSwal(action.token);
    }
}

async function showCfGuide() {
    await Swal.fire({
        title: '<i class="bi bi-book me-1"></i> Guia de Cloudflare API Token',
        html: `<div class="text-start small">

            <div class="alert alert-info py-2 mb-3">
                <i class="bi bi-shield-lock me-1"></i>
                <strong>&iquest;Por que es necesario este token?</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <li>Permite generar <strong>certificados SSL automaticos</strong> (via DNS-01) para dominios alias y custom.</li>
                    <li>Es <strong>imprescindible</strong> cuando un dominio usa el <strong>proxy de Cloudflare</strong> <span class="badge bg-warning text-dark" style="font-size:.7rem">nube naranja</span>, ya que el metodo HTTP-01 falla al no poder llegar directamente al servidor.</li>
                    <li>Permite a Caddy crear registros TXT temporales para verificar la propiedad del dominio ante la CA.</li>
                </ul>
            </div>

            <div class="alert alert-secondary py-2 mb-3">
                <i class="bi bi-diagram-2 me-1"></i>
                <strong>Tokens en el sistema</strong>
                <table class="table table-sm table-bordered mt-1 mb-0 bg-white" style="font-size:.78rem">
                    <tr>
                        <th style="width:40%">CLOUDFLARE_API_TOKEN</th>
                        <td>Token principal. Lo usa <strong>Caddy</strong> para certificados SSL de <code>*.musedock.com</code> y dominios alias. <strong>Es el que se gestiona aqui.</strong></td>
                    </tr>
                    <tr>
                        <th>CLOUDFLARE_CUSTOM_DOMAINS_*</th>
                        <td>Token secundario (otra cuenta CF). Lo usa el modulo de <strong>dominios personalizados de clientes</strong>. Se configura en <code>.env</code>.</td>
                    </tr>
                </table>
            </div>

            <h6 class="fw-bold mb-2"><i class="bi bi-gear me-1"></i> Crear / actualizar el token principal</h6>
            <ol>
                <li class="mb-2">Ve a <strong>dash.cloudflare.com</strong> &rarr; <strong>My Profile</strong> &rarr; <strong>API Tokens</strong></li>
                <li class="mb-2">Haz clic en <strong>Create Token</strong> &rarr; <strong>Custom Token</strong></li>
                <li class="mb-2">Configura los permisos:
                    <table class="table table-sm table-bordered mt-1 mb-0" style="font-size:.78rem">
                        <tr><th>Permiso</th><th>Acceso</th><th>Motivo</th></tr>
                        <tr><td>Zone : Zone : Read</td><td><strong>All zones</strong></td><td>Listar dominios</td></tr>
                        <tr><td>Zone : DNS : Edit</td><td><strong>All zones</strong></td><td>Crear TXT para SSL</td></tr>
                    </table>
                    <div class="text-danger mt-1" style="font-size:.75rem"><i class="bi bi-exclamation-triangle me-1"></i>Debe ser <strong>All zones</strong>, no una zona especifica, para que funcione con cualquier dominio alias.</div>
                </li>
                <li class="mb-2">Guarda y copia el token</li>
                <li>Pegalo en el campo y haz clic en <strong>Verificar</strong></li>
            </ol>

            <div class="alert alert-light border py-2 mb-0" style="font-size:.78rem">
                <i class="bi bi-hdd-rack me-1"></i>
                <strong>Al guardar</strong>, el token se actualiza en <code>.env</code> y en <code>/etc/default/caddy</code>, y se reinicia Caddy automaticamente para aplicarlo.
            </div>
        </div>`,
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#0d6efd',
        width: 580
    });
}

async function verifyCfTokenSwal(token) {
    if (!token) return;

    Swal.fire({ title: 'Verificando token...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('/musedock/domain-manager/cloudflare/verify-token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ _csrf: csrfToken, token: token })
        });
        const data = await res.json();

        if (!data.success) {
            await Swal.fire({ icon: 'error', title: 'Token invalido', text: data.message, confirmButtonColor: '#0d6efd' });
            return openCloudflareSettings();
        }

        let zonesHtml = '';
        if (data.zones && data.zones.length > 0) {
            zonesHtml = data.zones.map(z =>
                `<span class="badge bg-${z.status === 'active' ? 'success' : 'secondary'} me-1 mb-1">${z.name}</span>`
            ).join('');
        }

        const isNew = token !== CF_CURRENT_TOKEN;
        const allOk = data.zones_count > 1;

        const { isConfirmed } = await Swal.fire({
            icon: allOk ? 'success' : 'warning',
            title: allOk ? 'Token valido' : 'Token con acceso limitado',
            html: `<div class="text-start">
                <table class="table table-sm mb-2" style="font-size:.85rem">
                    <tr><td class="text-muted">Estado</td><td><strong>${data.status}</strong></td></tr>
                    <tr><td class="text-muted">Cuenta</td><td><strong>${data.account}</strong></td></tr>
                    <tr><td class="text-muted">Zonas</td><td><strong>${data.zones_count}</strong> ${allOk ? '&#10004;' : '&#9888; puede faltar "All zones"'}</td></tr>
                </table>
                ${zonesHtml ? '<div class="mb-2">' + zonesHtml + '</div>' : ''}
                ${!allOk ? '<div class="alert alert-warning small py-1 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>El token solo ve ' + data.zones_count + ' zona(s). Para gestionar todos los dominios necesita acceso a All zones.</div>' : ''}
            </div>`,
            showCancelButton: isNew,
            confirmButtonText: isNew ? '<i class="bi bi-save me-1"></i> Guardar y aplicar' : 'Cerrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: isNew ? '#198754' : '#0d6efd',
        });

        if (isConfirmed && isNew) {
            await saveCfTokenSwal(token);
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexion: ' + e.message, confirmButtonColor: '#0d6efd' });
    }
}

async function saveCfTokenSwal(token) {
    Swal.fire({ title: 'Guardando token...', html: 'Actualizando .env + Caddy...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('/musedock/domain-manager/cloudflare/save-token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ _csrf: csrfToken, token: token })
        });
        const data = await res.json();

        let details = '';
        if (data.updated && data.updated.length) details += '<div class="mt-2"><strong>Actualizado:</strong> ' + data.updated.map(u => '<span class="badge bg-success me-1">' + u + '</span>').join('') + '</div>';
        if (data.errors && data.errors.length) details += '<div class="mt-2"><strong>Errores:</strong> ' + data.errors.map(e => '<span class="badge bg-danger me-1">' + e + '</span>').join('') + '</div>';

        await Swal.fire({
            icon: data.errors && data.errors.length ? 'warning' : 'success',
            title: data.success ? 'Token actualizado' : 'Error',
            html: data.message + details,
            confirmButtonColor: '#0d6efd'
        });

        if (data.success) location.reload();
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexion: ' + e.message, confirmButtonColor: '#0d6efd' });
    }
}

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

// ========== ELIMINAR DOMAIN ORDER (Customer) con SweetAlert2 ==========
function confirmDeleteDomainOrder(id, domain, hasCloudflare) {
    Swal.fire({
        title: '<i class="bi bi-exclamation-triangle text-danger"></i> Eliminar Dominio Registrado',
        html: `
            <div class="text-start">
                <p class="mb-3">¿Estás seguro de eliminar el registro del dominio <strong>${domain}</strong>?</p>
                <div class="alert alert-warning py-2 mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Importante:</strong> El dominio NO se eliminará de OpenProvider (los dominios solo pueden caducar, no eliminarse).</small>
                </div>
                <div class="alert alert-danger py-2 mb-3">
                    <i class="bi bi-trash me-2"></i>
                    <small><strong>Se eliminará:</strong> El registro de la base de datos local${hasCloudflare ? ' y opcionalmente de Cloudflare' : ''}.</small>
                </div>
                ${hasCloudflare ? `
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="deleteOrderFromCloudflare" checked>
                    <label class="form-check-label" for="deleteOrderFromCloudflare">
                        <i class="bi bi-cloud me-1"></i> Eliminar zona de Cloudflare
                        <br><small class="text-muted">Desmarcar si quieres mantener la zona DNS en Cloudflare</small>
                    </label>
                </div>
                ` : ''}
                <div class="mb-3">
                    <label class="form-label fw-bold">Introduce tu contraseña para confirmar:</label>
                    <input type="password" id="deleteOrderPassword" class="form-control" placeholder="Contraseña del superadmin" autocomplete="current-password">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar Registro',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        width: '550px',
        focusConfirm: false,
        didOpen: () => {
            document.getElementById('deleteOrderPassword').focus();
        },
        preConfirm: () => {
            const password = document.getElementById('deleteOrderPassword').value;
            if (!password) {
                Swal.showValidationMessage('La contraseña es requerida');
                return false;
            }
            const deleteFromCloudflare = hasCloudflare ? document.getElementById('deleteOrderFromCloudflare').checked : false;
            return {
                password: password,
                deleteFromCloudflare: deleteFromCloudflare
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Eliminando registro...',
                html: '<p class="mb-0">Por favor espera...</p>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/musedock/domain-manager/order/${id}/delete-secure`, {
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
                        title: 'Registro Eliminado',
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

// ========== ELIMINAR ALIAS con SweetAlert2 ==========
function confirmDeleteAlias(id, domain, isSubdomain, hasCfZone, hasCfRecord) {
    const hasCf = hasCfZone || hasCfRecord;

    let cfSection = '';
    if (hasCf) {
        if (isSubdomain) {
            cfSection = `
                <div class="form-check text-start mt-3">
                    <input class="form-check-input" type="checkbox" id="cfDeleteAliasCheck" checked>
                    <label class="form-check-label" for="cfDeleteAliasCheck">
                        <i class="bi bi-cloud me-1"></i> Eliminar registro CNAME de Cloudflare
                        <br><small class="text-muted">Solo el registro del subdominio.</small>
                    </label>
                </div>`;
        } else {
            cfSection = `
                <div class="form-check text-start mt-3">
                    <input class="form-check-input" type="checkbox" id="cfDeleteAliasCheck">
                    <label class="form-check-label" for="cfDeleteAliasCheck">
                        <i class="bi bi-cloud me-1 text-danger"></i> Eliminar ZONA COMPLETA de Cloudflare
                    </label>
                </div>
                <div class="alert alert-danger small py-2 mt-2 text-start">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>Peligro:</strong> Eliminara TODA la zona <strong>${domain}</strong> incluyendo
                    subdominios, MX, TXT, DKIM, SPF y cualquier otra configuracion.
                    <strong>Desmarcar si el dominio tiene otros servicios.</strong>
                </div>`;
        }
    }

    Swal.fire({
        title: '<i class="bi bi-exclamation-triangle text-danger"></i> Eliminar Alias',
        html: `
            <p>¿Eliminar el alias <strong>${domain}</strong>?</p>
            <div class="alert alert-light border small py-2 text-start mb-0">
                <i class="bi bi-info-circle text-primary me-1"></i>
                <strong>Se eliminara:</strong>
                <ul class="mb-0 mt-1">
                    <li>Ruta de Caddy (SSL y enrutamiento)</li>
                    <li>Registro en base de datos</li>
                </ul>
            </div>
            ${cfSection}`,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        preConfirm: () => {
            const cfCheck = document.getElementById('cfDeleteAliasCheck');
            return { deleteFromCloudflare: cfCheck ? cfCheck.checked : false };
        }
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: 'Eliminando alias...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        fetch(`/musedock/domain-manager/alias/${id}/delete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ _csrf: csrfToken, deleteFromCloudflare: result.value.deleteFromCloudflare })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success', title: 'Alias Eliminado',
                    html: `${data.message}<br><small class="text-muted">Cloudflare: ${data.cloudflare || 'n/a'}</small>`,
                    confirmButtonColor: '#0d6efd'
                }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#0d6efd' });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.', confirmButtonColor: '#0d6efd' });
        });
    });
}

// ========== ELIMINAR REDIRECT con SweetAlert2 ==========
function confirmDeleteRedirect(id, domain) {
    Swal.fire({
        title: '<i class="bi bi-exclamation-triangle text-danger"></i> Eliminar Redirección',
        html: `<p>¿Estás seguro de eliminar la redirección de <strong>${domain}</strong>?</p>
               <p class="text-muted small">Se eliminará de Caddy y Cloudflare (si aplica).</p>`,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar Redirección',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Eliminando redirección...',
                html: '<p class="mb-0">Por favor espera...</p>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/musedock/domain-manager/redirect/${id}/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ _csrf: csrfToken })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Redirección Eliminada', text: data.message, confirmButtonColor: '#0d6efd' })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#0d6efd' });
                }
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.', confirmButtonColor: '#0d6efd' });
            });
        }
    });
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
    let subdomainCheckTimeout = null;

    Swal.fire({
        title: '<i class="bi bi-gift"></i> Crear Subdominio FREE',
        html: `
            <div class="text-start">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Subdominio FREE:</strong> Se creará un nuevo tenant con subdominio .musedock.com gratuito.
                </div>

                <div class="mb-3">
                    <label for="swal-subdomain" class="form-label fw-semibold">Subdominio <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="swal-subdomain" required
                               pattern="[a-z0-9\\-]+" placeholder="ejemplo"
                               title="Solo letras minúsculas, números y guiones">
                        <span class="input-group-text">.musedock.com</span>
                    </div>
                    <div id="subdomain-status" class="form-text">Mínimo 3 caracteres. Solo letras minúsculas, números y guiones.</div>
                </div>

                <div class="mb-3">
                    <label for="swal-customer-name" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="swal-customer-name" required
                           placeholder="Juan Pérez">
                </div>

                <div class="mb-3">
                    <label for="swal-customer-email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="swal-customer-email" required
                           placeholder="juan@example.com">
                    <div class="form-text">Se usará para acceder al panel de administración.</div>
                </div>

                <div class="mb-3">
                    <label for="swal-customer-password" class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="swal-customer-password" required
                           minlength="8" placeholder="Mínimo 8 caracteres">
                </div>

                <hr class="my-3">

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="swal-send-welcome-email" checked>
                        <label class="form-check-label" for="swal-send-welcome-email">
                            <strong>Enviar email de bienvenida</strong>
                        </label>
                        <div class="form-text">Recibirá un email con sus credenciales de acceso al panel admin.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="swal-cf-proxy" checked>
                        <label class="form-check-label" for="swal-cf-proxy">
                            <strong>Proxy Cloudflare</strong> <span class="badge bg-warning text-dark">naranja</span>
                        </label>
                        <div class="form-text">Activar proxy (CDN + protección). Desmarcar para DNS-only (gris).</div>
                    </div>
                </div>

                <div id="free-subdomain-progress" class="d-none">
                    <div class="alert alert-warning mb-0">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                            <strong id="progress-title">Creando subdominio...</strong>
                        </div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" style="width: 0%"></div>
                        </div>
                        <small id="progress-step" class="text-muted">Iniciando...</small>
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
            const subdomainInput = document.getElementById('swal-subdomain');
            const statusEl = document.getElementById('subdomain-status');

            subdomainInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');

                // Verificar disponibilidad con debounce
                clearTimeout(subdomainCheckTimeout);
                const val = e.target.value;

                if (val.length < 3) {
                    statusEl.innerHTML = 'Mínimo 3 caracteres. Solo letras minúsculas, números y guiones.';
                    statusEl.className = 'form-text text-muted';
                    subdomainInput.classList.remove('is-valid', 'is-invalid');
                    return;
                }

                statusEl.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:12px;height:12px"></span> Verificando disponibilidad...';
                statusEl.className = 'form-text text-muted';

                subdomainCheckTimeout = setTimeout(() => {
                    fetch(`/customer/check-subdomain?subdomain=${encodeURIComponent(val)}`, {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (subdomainInput.value !== val) return; // Input changed
                        if (data.available) {
                            statusEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> <strong>' + val + '.musedock.com</strong> está disponible';
                            statusEl.className = 'form-text text-success';
                            subdomainInput.classList.add('is-valid');
                            subdomainInput.classList.remove('is-invalid');
                        } else {
                            statusEl.innerHTML = '<i class="bi bi-x-circle-fill"></i> ' + (data.error || 'Subdominio no disponible');
                            statusEl.className = 'form-text text-danger';
                            subdomainInput.classList.add('is-invalid');
                            subdomainInput.classList.remove('is-valid');
                        }
                    })
                    .catch(() => {
                        statusEl.innerHTML = 'Mínimo 3 caracteres. Solo letras minúsculas, números y guiones.';
                        statusEl.className = 'form-text text-muted';
                    });
                }, 500);
            });
        },
        preConfirm: () => {
            const subdomain = document.getElementById('swal-subdomain').value;
            const customerName = document.getElementById('swal-customer-name').value;
            const customerEmail = document.getElementById('swal-customer-email').value;
            const customerPassword = document.getElementById('swal-customer-password').value;
            const sendWelcomeEmail = document.getElementById('swal-send-welcome-email').checked;
            const cfProxy = document.getElementById('swal-cf-proxy').checked;

            // Validaciones
            if (!subdomain || subdomain.length < 3) {
                Swal.showValidationMessage('El subdominio debe tener al menos 3 caracteres');
                return false;
            }
            if (!/^[a-z0-9\-]+$/.test(subdomain)) {
                Swal.showValidationMessage('El subdominio solo puede contener letras minúsculas, números y guiones');
                return false;
            }
            if (document.getElementById('swal-subdomain').classList.contains('is-invalid')) {
                Swal.showValidationMessage('El subdominio no está disponible. Elige otro.');
                return false;
            }
            if (!customerName) {
                Swal.showValidationMessage('El nombre es requerido');
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

            // Mostrar barra de progreso y ocultar formulario
            const progressEl = document.getElementById('free-subdomain-progress');
            const progressBar = document.getElementById('progress-bar');
            const progressStep = document.getElementById('progress-step');
            const progressTitle = document.getElementById('progress-title');
            progressEl.classList.remove('d-none');

            // Ocultar campos del formulario durante el proceso
            progressEl.parentElement.querySelectorAll('.mb-3, .alert-info').forEach(el => {
                if (el !== progressEl) el.style.display = 'none';
            });

            // Simular progreso por pasos
            const steps = [
                { pct: 10, text: 'Validando datos...' },
                { pct: 25, text: 'Creando customer...' },
                { pct: 40, text: 'Creando tenant y admin...' },
                { pct: 55, text: 'Aplicando permisos y roles...' },
                { pct: 70, text: 'Configurando Cloudflare DNS...' },
                { pct: 80, text: 'Configurando Caddy SSL...' },
                { pct: 90, text: 'Ejecutando health check...' },
            ];
            let stepIdx = 0;
            const stepInterval = setInterval(() => {
                if (stepIdx < steps.length) {
                    progressBar.style.width = steps[stepIdx].pct + '%';
                    progressStep.textContent = steps[stepIdx].text;
                    stepIdx++;
                }
            }, 2000);

            // Crear FormData — el mismo email/password se usa para customer y admin
            const formData = new FormData();
            formData.append('_csrf_token', '<?= csrf_token() ?>');
            formData.append('subdomain', subdomain);
            formData.append('customer_name', customerName);
            formData.append('customer_email', customerEmail);
            formData.append('customer_password', customerPassword);
            formData.append('send_welcome_email', sendWelcomeEmail ? '1' : '0');
            formData.append('cf_proxy', cfProxy ? '1' : '0');
            formData.append('admin_email', customerEmail);
            formData.append('admin_password', customerPassword);

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
                clearInterval(stepInterval);
                if (!data.success) {
                    // Restaurar formulario si hay error
                    progressEl.classList.add('d-none');
                    progressEl.parentElement.querySelectorAll('.mb-3, .alert-info').forEach(el => {
                        el.style.display = '';
                    });
                    throw new Error(data.error || 'No se pudo crear el subdominio');
                }
                progressBar.style.width = '100%';
                progressBar.classList.replace('bg-warning', 'bg-success');
                progressStep.textContent = '¡Completado!';
                progressTitle.textContent = '¡Subdominio creado!';
                return data;
            })
            .catch(error => {
                clearInterval(stepInterval);
                // Restaurar formulario si hay error
                progressEl.classList.add('d-none');
                progressEl.parentElement.querySelectorAll('.mb-3, .alert-info').forEach(el => {
                    el.style.display = '';
                });
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
