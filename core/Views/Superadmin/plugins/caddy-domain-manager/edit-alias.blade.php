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

                            <div class="mb-3">
                                <label class="form-label" for="dns_provider">Proveedor DNS</label>
                                <select class="form-select" id="dns_provider" name="dns_provider">
                                    @php
                                        $currentDnsProvider = $alias->dns_provider ?? (!empty($alias->cloudflare_zone_id) || !empty($alias->cloudflare_record_id) ? 'cloudflare' : ($defaultDnsProvider ?? 'cloudflare'));
                                    @endphp
                                    @foreach(($dnsProviders ?? []) as $key => $provider)
                                        <option value="{{ $key }}" {{ $currentDnsProvider === $key ? 'selected' : '' }}>
                                            {{ $provider['label'] ?? $key }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">No mueve DNS existente. Solo marca qué proveedor debe usarse para diagnóstico y DNS-01.</div>
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
                                    <th>DNS</th>
                                    <td>
                                        @php
                                            $dnsLabel = ($dnsProviders[$alias->dns_provider ?? ''] ?? null)['label'] ?? ($alias->dns_provider ?? 'Manual / externo');
                                        @endphp
                                        <span class="badge {{ ($alias->dns_provider ?? '') === 'cloudflare' ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ $dnsLabel }}</span>
                                        <small class="text-muted">{{ $alias->dns_mode ?? 'manual' }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Cloudflare</th>
                                    <td>
                                        @if(!empty($alias->cloudflare_zone_id))
                                            <span class="text-success"><i class="bi bi-shield-fill-check"></i> Zona: {{ $alias->cloudflare_zone_id }}</span>
                                        @else
                                            <span class="text-muted">No gestionado por el panel</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>CF Proxy</th>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span id="alias-proxy-status"><small class="text-muted"><span class="spinner-border spinner-border-sm"></span> Consultando...</small></span>
                                            <div class="form-check form-switch mb-0 d-none" id="alias-proxy-toggle-wrap">
                                                <input class="form-check-input" type="checkbox" id="aliasProxyToggle" disabled>
                                            </div>
                                        </div>
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
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-warning" onclick="recreateCaddyRoute({{ $alias->id }}, '{{ $alias->domain }}')">
                            <i class="bi bi-arrow-repeat"></i> Recrear ruta Caddy
                        </button>
                        <div class="d-flex gap-2">
                            <a href="/musedock/domain-manager" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
async function recreateCaddyRoute(aliasId, domain) {
    const { isConfirmed } = await Swal.fire({
        title: '<i class="bi bi-arrow-repeat text-warning"></i> Recrear ruta Caddy',
        html: `<p>Se va a recrear la ruta de Caddy para <strong>${domain}</strong>.</p>
               <p class="text-muted small mb-0">Esto es util si la ruta se perdio tras un reinicio de Caddy o si el certificado SSL no se genero correctamente.</p>`,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Recrear',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#e67e22',
    });

    if (!isConfirmed) return;

    Swal.fire({ title: 'Recreando ruta...', html: 'Configurando Caddy...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch(`/musedock/domain-manager/alias/${aliasId}/recreate-route`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ _csrf: '<?= csrf_token() ?>' })
        });
        const data = await res.json();

        await Swal.fire({
            icon: data.success ? 'success' : 'error',
            title: data.success ? 'Ruta recreada' : 'Error',
            html: data.message + (data.route_id ? `<br><small class="text-muted">Route ID: <code>${data.route_id}</code></small>` : ''),
            confirmButtonColor: '#0d6efd'
        });

        if (data.success) location.reload();
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de conexion', text: e.message, confirmButtonColor: '#0d6efd' });
    }
}

// Auto-check CF proxy status for this alias
const csrfToken = '<?= csrf_token() ?>';
let aliasProxyData = {};

(async function() {
    const statusEl = document.getElementById('alias-proxy-status');
    const toggleWrap = document.getElementById('alias-proxy-toggle-wrap');
    const toggle = document.getElementById('aliasProxyToggle');

    try {
        const cfToken = '{{ \Screenart\Musedock\Env::get("CLOUDFLARE_API_TOKEN", "") }}';
        if (!cfToken) {
            statusEl.innerHTML = '<small class="text-muted">No hay token CF configurado</small>';
            return;
        }

        // Use the tenant's check-proxy endpoint which checks all aliases
        const res = await fetch('/musedock/domain-manager/{{ $alias->tenant_id }}/check-proxy', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) {
            statusEl.innerHTML = `<small class="text-danger">${data.message}</small>`;
            return;
        }

        // Find this alias in the results
        const aliasDomain = '{{ $alias->domain }}';
        const found = (data.domains || []).find(d => d.domain === aliasDomain);

        if (!found || found.proxied === null) {
            statusEl.innerHTML = '<small class="text-muted"><i class="bi bi-question-circle me-1"></i> No encontrado en Cloudflare</small>';
            return;
        }

        aliasProxyData = found;

        if (found.proxied) {
            statusEl.innerHTML = '<small class="text-warning"><i class="bi bi-cloud-fill me-1"></i> Proxy activo (nube naranja)</small>';
        } else {
            statusEl.innerHTML = '<small class="text-secondary"><i class="bi bi-cloud me-1"></i> Solo DNS (nube gris)</small>';
        }

        // Show toggle if we have zone_id + record_id
        if (found.zone_id && found.record_id) {
            toggle.checked = found.proxied;
            toggle.disabled = false;
            toggleWrap.classList.remove('d-none');

            toggle.addEventListener('change', async function() {
                const newState = this.checked;
                const action = newState ? 'activar el proxy (nube naranja)' : 'desactivar el proxy (nube gris)';
                const warning = newState
                    ? 'El trafico pasara por Cloudflare. Necesitaras certificado SSL via DNS-01.'
                    : 'La conexion sera directa al servidor. Cloudflare no filtrara ni cacheara el trafico.';

                const confirm = await Swal.fire({
                    title: `<i class="bi bi-cloud${newState ? '-fill text-warning' : ' text-secondary'}"></i> Cambiar proxy`,
                    html: `<p>¿${action} para <strong>${aliasDomain}</strong>?</p>
                           <div class="alert alert-light border small py-2 text-start mb-0">
                               <i class="bi bi-info-circle text-primary me-1"></i> ${warning}
                           </div>`,
                    showCancelButton: true,
                    confirmButtonText: newState ? '<i class="bi bi-cloud-fill me-1"></i> Activar' : '<i class="bi bi-cloud me-1"></i> Desactivar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: newState ? '#e67e22' : '#6c757d',
                });

                if (!confirm.isConfirmed) {
                    this.checked = !newState;
                    return;
                }

                this.disabled = true;
                try {
                    const res = await fetch('/musedock/domain-manager/toggle-domain-proxy', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({
                            _csrf: csrfToken,
                            zone_id: aliasProxyData.zone_id,
                            record_id: aliasProxyData.record_id,
                            proxied: newState,
                            domain: aliasDomain
                        })
                    });
                    const result = await res.json();
                    if (result.success) {
                        if (result.proxied) {
                            statusEl.innerHTML = '<small class="text-warning"><i class="bi bi-cloud-fill me-1"></i> Proxy activo (nube naranja)</small>';
                        } else {
                            statusEl.innerHTML = '<small class="text-secondary"><i class="bi bi-cloud me-1"></i> Solo DNS (nube gris)</small>';
                        }
                    } else {
                        this.checked = !newState;
                        Swal.fire({ icon: 'error', title: 'Error', text: result.message, confirmButtonColor: '#0d6efd' });
                    }
                } catch (e) {
                    this.checked = !newState;
                    Swal.fire({ icon: 'error', title: 'Error', text: e.message, confirmButtonColor: '#0d6efd' });
                } finally {
                    this.disabled = false;
                }
            });
        }
    } catch (e) {
        statusEl.innerHTML = '<small class="text-danger">Error consultando CF</small>';
    }
})();
</script>
@endpush
