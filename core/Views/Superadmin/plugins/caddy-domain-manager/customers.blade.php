@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-people"></i> Clientes</h2>
                <p class="text-muted mb-0">Customers creados desde <code>/register</code> y desde “Crear Subdominio FREE”</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a Domain Manager
                </a>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="/musedock/domain-manager/customers" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="search" class="form-control" placeholder="Nombre, email o empresa..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Por página</label>
                        <select name="per_page" class="form-select">
                            @php($pp = (int)($pagination['per_page'] ?? 50))
                            <option value="25" {{ $pp === 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ $pp === 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $pp === 100 ? 'selected' : '' }}>100</option>
                            <option value="200" {{ $pp === 200 ? 'selected' : '' }}>200</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Total: <strong>{{ (int)($pagination['total'] ?? 0) }}</strong>
                </div>
                @php
                    $page = (int)($pagination['page'] ?? 1);
                    $pages = (int)($pagination['pages'] ?? 1);
                    $search = $filters['search'] ?? '';
                    $perPage = (int)($pagination['per_page'] ?? 50);
                    $qsBase = 'search=' . urlencode($search) . '&per_page=' . urlencode((string)$perPage);
                @endphp
                <div class="btn-group" role="group" aria-label="Pagination">
                    <a class="btn btn-outline-secondary {{ $page <= 1 ? 'disabled' : '' }}"
                       href="/musedock/domain-manager/customers?{{ $qsBase }}&page={{ max(1, $page - 1) }}">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <span class="btn btn-outline-secondary disabled">Página {{ $page }} / {{ $pages }}</span>
                    <a class="btn btn-outline-secondary {{ $page >= $pages ? 'disabled' : '' }}"
                       href="/musedock/domain-manager/customers?{{ $qsBase }}&page={{ min($pages, $page + 1) }}">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th class="text-center">Tenants</th>
                            <th class="text-center">Subdominios</th>
                            <th>Último tenant</th>
                            <th>Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $c)
                            <tr>
                                <td class="text-muted">{{ $c->id }}</td>
                                <td class="fw-semibold">{{ $c->name }}</td>
                                <td><a href="mailto:{{ $c->email }}">{{ $c->email }}</a></td>
                                <td>{{ $c->company ?? '-' }}</td>
                                <td>
                                    @php($status = $c->status ?? 'unknown')
                                    <span class="badge {{ $status === 'active' ? 'bg-success' : ($status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                        {{ $status }}
                                    </span>
                                </td>
                                <td class="text-center">{{ (int)($c->tenants_count ?? 0) }}</td>
                                <td class="text-center">{{ (int)($c->subdomains_count ?? 0) }}</td>
                                <td class="text-muted">{{ $c->last_tenant_created_at ? format_datetime($c->last_tenant_created_at) : '-' }}</td>
                                <td class="text-muted">{{ $c->created_at ? format_datetime($c->created_at) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center p-4 text-muted">
                                    No hay clientes que coincidan con la búsqueda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
