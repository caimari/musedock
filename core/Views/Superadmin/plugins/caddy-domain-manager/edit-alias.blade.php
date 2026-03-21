@extends('layouts.app')

@section('title', 'Editar Alias: ' . ($alias->domain ?? ''))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-pencil"></i> Editar Alias de Dominio</h2>
                <p class="text-muted mb-0">{{ $alias->domain ?? '' }}</p>
            </div>
            <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @include('partials.alerts-sweetalert2')

        @if(!$caddyApiAvailable)
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Caddy API no disponible.</strong>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Configuración del Alias</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/musedock/domain-manager/alias/{{ $alias->id }}/update">
                    {!! csrf_field() !!}

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-globe2"></i> Dominio</h6>

                            <div class="mb-3">
                                <label class="form-label">Dominio del Alias</label>
                                <input type="text" class="form-control" value="{{ $alias->domain }}" disabled>
                                <div class="form-text">El dominio no se puede cambiar. Para usar otro dominio, elimina este alias y crea uno nuevo.</div>
                            </div>

                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">Tenant Destino <span class="text-danger">*</span></label>
                                <select class="form-select" id="tenant_id" name="tenant_id" required>
                                    <option value="">-- Seleccionar Tenant --</option>
                                    @foreach($tenants as $tenant)
                                        <option value="{{ $tenant->id }}" {{ ($alias->tenant_id ?? '') == $tenant->id ? 'selected' : '' }}>
                                            {{ $tenant->name }} ({{ $tenant->domain }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Puedes reasignar este alias a otro tenant</div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_www" name="include_www"
                                           {{ ($alias->include_www ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="include_www">
                                        <strong>Incluir www</strong>
                                        <br><small class="text-muted">También configurar www.{{ $alias->domain }}</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-info-circle"></i> Estado Actual</h6>

                            <table class="table table-sm">
                                <tr>
                                    <th>Estado</th>
                                    <td>
                                        @php
                                            $statusClass = match($alias->status ?? 'pending') {
                                                'active' => 'success',
                                                'pending' => 'warning',
                                                'error' => 'danger',
                                                'suspended' => 'secondary',
                                                default => 'dark'
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $statusClass }}">{{ ucfirst($alias->status ?? 'pending') }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Caddy</th>
                                    <td>
                                        @if($alias->caddy_configured ?? false)
                                            <span class="text-success"><i class="bi bi-check-circle"></i> Configurado</span>
                                        @else
                                            <span class="text-muted"><i class="bi bi-x-circle"></i> No configurado</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Cloudflare</th>
                                    <td>
                                        @if(!empty($alias->cloudflare_zone_id))
                                            <span class="text-success"><i class="bi bi-shield-fill-check"></i> Zona: {{ $alias->cloudflare_zone_id }}</span>
                                        @else
                                            <span class="text-muted">No configurado</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($alias->error_log ?? false)
                                <tr>
                                    <th>Error</th>
                                    <td><small class="text-danger">{{ $alias->error_log }}</small></td>
                                </tr>
                                @endif
                                <tr>
                                    <th>Creado</th>
                                    <td>{{ !empty($alias->created_at) ? date('d/m/Y H:i', strtotime($alias->created_at)) : '—' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/musedock/domain-manager" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
