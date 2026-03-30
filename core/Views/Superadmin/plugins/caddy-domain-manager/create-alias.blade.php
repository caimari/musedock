@extends('layouts.app')

@section('title', 'Nuevo Alias de Dominio')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-link-45deg"></i> Nuevo Alias de Dominio</h2>
                <p class="text-muted mb-0">Crea un alias que apunte a un tenant existente</p>
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
                Puedes crear el alias, pero la configuración automática de Caddy no funcionará.
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Configuración del Alias</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/musedock/domain-manager/store-alias">
                    {!! csrf_field() !!}

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-globe2"></i> Dominio</h6>

                            <div class="mb-3">
                                <label for="domain" class="form-label">Dominio del Alias <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="domain" name="domain" required
                                       placeholder="alias.ejemplo.com" value="{{ old('domain') }}">
                                <div class="form-text">El dominio que funcionará como alias (sin www)</div>
                            </div>

                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">Tenant Destino <span class="text-danger">*</span></label>
                                <select class="form-select" id="tenant_id" name="tenant_id" required>
                                    <option value="">-- Seleccionar Tenant --</option>
                                    @foreach($tenants as $tenant)
                                        <option value="{{ $tenant->id }}" {{ old('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                            {{ $tenant->name }} ({{ $tenant->domain }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">El tenant al que apuntará este alias</div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_www" name="include_www" checked>
                                    <label class="form-check-label" for="include_www">
                                        <strong>Incluir www</strong>
                                        <br><small class="text-muted">También configurar www.alias.ejemplo.com</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-cloud"></i> Cloudflare & Caddy</h6>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="skip_cloudflare" name="skip_cloudflare" checked>
                                    <label class="form-check-label" for="skip_cloudflare">
                                        <strong>No crear zona en Cloudflare</strong>
                                        <br><small class="text-muted">Marcar si el DNS ya está configurado manualmente</small>
                                    </label>
                                </div>
                            </div>

                            <div id="cloudflareOptions" style="display: none;">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="configure_cloudflare" name="configure_cloudflare" checked>
                                        <label class="form-check-label" for="configure_cloudflare">
                                            <strong>Crear zona y registros DNS en Cloudflare</strong>
                                            <br><small class="text-muted">Se creará la zona y los registros A/AAAA apuntando al servidor</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>Caddy:</strong> Se configurará automáticamente para que el alias apunte al mismo tenant que el dominio principal.
                                El certificado SSL se generará automáticamente.
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/musedock/domain-manager" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Crear Alias
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
document.getElementById('skip_cloudflare').addEventListener('change', function() {
    document.getElementById('cloudflareOptions').style.display = this.checked ? 'none' : 'block';
});
</script>
@endpush

@endsection
