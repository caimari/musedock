@extends('layouts.app')

@section('title', 'Cuentas DNS')

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-hdd-network me-2"></i>Cuentas DNS</h2>
                <p class="text-muted mb-0">Credenciales cifradas para proveedores DNS no Cloudflare.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Domain Manager
                </a>
                <a href="/musedock/plugins/caddy-domain-manager/cloudflare-accounts" class="btn btn-outline-warning">
                    <i class="bi bi-cloud-fill"></i> Cloudflare
                </a>
                <button type="button" class="btn btn-primary" onclick="openDnsAccountForm()">
                    <i class="bi bi-plus-lg"></i> Nueva cuenta
                </button>
            </div>
        </div>

        @if(!$tableReady)
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Falta la tabla <code>dns_provider_accounts</code>. Ejecuta el instalador/migraciones del plugin antes de guardar cuentas.
            </div>
        @endif

        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            Cloudflare conserva su pantalla propia para no romper el flujo actual. Estas cuentas se usan para DNS-01 y, si el proveedor lo permite,
            para crear los registros <code>A</code> y <code>CNAME</code> automaticamente en nuevos dominios, alias y redirecciones.
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-muted">{{ count($accounts) }} cuenta(s) configurada(s)</span>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Proveedor</th>
                            <th>Nombre</th>
                            <th>Credenciales</th>
                            <th>Referencia</th>
                            <th style="width:100px;">Default</th>
                            <th style="width:110px;">Estado</th>
                            <th>Ultima prueba</th>
                            <th style="width:150px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($accounts as $account)
                        @php
                            $providerInfo = $providers[$account['provider']] ?? [];
                            $editPayload = [
                                'id' => (int)$account['id'],
                                'provider' => $account['provider'],
                                'name' => $account['name'],
                                'account_ref' => $account['account_ref'] ?? '',
                                'is_default' => (int)($account['is_default'] ?? 0),
                                'enabled' => (int)($account['enabled'] ?? 0),
                            ];
                        @endphp
                        <tr>
                            <td><code>{{ $account['id'] }}</code></td>
                            <td>
                                <strong>{{ $providerInfo['label'] ?? $account['provider'] }}</strong>
                                @if(!empty($providerInfo['managed_dns']))
                                    <span class="badge bg-success ms-1">API DNS</span>
                                @elseif(!empty($providerInfo['dns01']))
                                    <span class="badge bg-info text-dark ms-1">DNS-01</span>
                                @endif
                            </td>
                            <td>{{ $account['name'] }}</td>
                            <td>
                                @foreach(($account['credentials_masked'] ?? []) as $field => $value)
                                    <div><code>{{ $field }}</code>: <span class="text-muted">{{ $value ?: 'vacio' }}</span></div>
                                @endforeach
                            </td>
                            <td><code>{{ $account['account_ref'] ?: '-' }}</code></td>
                            <td class="text-center">
                                @if(!empty($account['is_default']))
                                    <i class="bi bi-star-fill text-warning" title="Cuenta por defecto"></i>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($account['enabled']))
                                    <span class="badge bg-success">Activa</span>
                                @else
                                    <span class="badge bg-secondary">Pausada</span>
                                @endif
                            </td>
                            <td class="small">
                                @if(($account['last_test_status'] ?? '') === 'ok')
                                    <span class="text-success"><i class="bi bi-check-circle"></i> OK</span>
                                @elseif(($account['last_test_status'] ?? '') === 'error')
                                    <span class="text-danger"><i class="bi bi-x-circle"></i> Error</span>
                                @else
                                    <span class="text-muted">Sin probar</span>
                                @endif
                                @if(!empty($account['last_test_message']))
                                    <div class="text-muted">{{ $account['last_test_message'] }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" onclick="testDnsAccount({{ (int)$account['id'] }})" title="Probar">
                                        <i class="bi bi-wifi"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" onclick='openDnsAccountForm({!! json_encode($editPayload, JSON_HEX_APOS | JSON_HEX_QUOT) !!})' title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteDnsAccount({{ (int)$account['id'] }}, '{{ addslashes($account['name']) }}')" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No hay cuentas DNS configuradas todavia.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body small text-muted">
                <h6 class="card-title"><i class="bi bi-diagram-3 me-1"></i>Como funciona</h6>
                <ul class="mb-0">
                    <li><strong>DigitalOcean, Hetzner, Vultr, Linode, Porkbun y PowerDNS</strong> pueden crear registros DNS desde MuseDock si hay una cuenta activa por defecto.</li>
                    <li><strong>Route53, OVH, Namecheap, Gandi y RFC2136</strong> quedan preparados para DNS-01 y diagnostico de credenciales, sin cambiar el flujo Cloudflare existente.</li>
                    <li>Las credenciales se guardan cifradas y no se muestran completas despues de guardarlas.</li>
                    <li>Para crear registros <code>A</code> automaticamente, configura <code>DNS_WEB_TARGET_IP</code> o <code>SERVER_PUBLIC_IP</code>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '/musedock/plugins/caddy-domain-manager/dns-accounts';
const FIELD_MAP = {!! json_encode($fieldsByProvider, JSON_UNESCAPED_SLASHES) !!};
const PROVIDERS = {!! json_encode($providers, JSON_UNESCAPED_SLASHES) !!};
const csrfToken = '<?= csrf_token() ?>';

function ajaxPost(url, formData) {
    formData.append('_csrf_token', csrfToken);
    return fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(async response => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.error || ('HTTP ' + response.status));
        return data;
    });
}

function providerOptions(selected) {
    return Object.entries(PROVIDERS)
        .filter(([key]) => key !== 'manual' && key !== 'cloudflare')
        .map(([key, provider]) => `<option value="${key}" ${selected === key ? 'selected' : ''}>${provider.label || key}</option>`)
        .join('');
}

function credentialFields(provider, isEdit) {
    const fields = FIELD_MAP[provider] || [];
    return fields.map(field => `
        <div class="mb-3">
            <label class="form-label fw-bold">${field.replaceAll('_', ' ')}</label>
            <input type="password" class="form-control dns-credential" data-field="${field}" placeholder="${isEdit ? 'Dejar vacio para conservar' : field}">
        </div>
    `).join('');
}

function openDnsAccountForm(account = null) {
    const isEdit = !!account;
    const selectedProvider = isEdit ? account.provider : 'digitalocean';

    Swal.fire({
        title: isEdit ? '<i class="bi bi-pencil"></i> Editar cuenta DNS' : '<i class="bi bi-plus-lg"></i> Nueva cuenta DNS',
        width: 650,
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">Proveedor</label>
                    <select id="dns-provider" class="form-select" ${isEdit ? 'disabled' : ''}>${providerOptions(selectedProvider)}</select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre</label>
                    <input id="dns-name" class="form-control" value="${isEdit ? account.name : ''}" placeholder="Produccion, Cliente X, DNS principal">
                </div>
                <div id="dns-credentials">${credentialFields(selectedProvider, isEdit)}</div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Referencia externa</label>
                    <input id="dns-account-ref" class="form-control" value="${isEdit ? (account.account_ref || '') : ''}" placeholder="ID de cuenta/proyecto/zona opcional">
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="dns-default" ${isEdit && account.is_default ? 'checked' : ''}>
                    <label class="form-check-label" for="dns-default">Usar como cuenta por defecto para este proveedor</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="dns-enabled" ${!isEdit || account.enabled ? 'checked' : ''}>
                    <label class="form-check-label" for="dns-enabled">Cuenta activa</label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-save"></i> Guardar',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            const select = document.getElementById('dns-provider');
            select?.addEventListener('change', () => {
                document.getElementById('dns-credentials').innerHTML = credentialFields(select.value, false);
            });
        },
        preConfirm: () => {
            const provider = isEdit ? account.provider : document.getElementById('dns-provider').value;
            const name = document.getElementById('dns-name').value.trim();
            if (!name) {
                Swal.showValidationMessage('El nombre es obligatorio');
                return false;
            }

            const formData = new FormData();
            formData.append('provider', provider);
            formData.append('name', name);
            formData.append('account_ref', document.getElementById('dns-account-ref').value.trim());
            if (document.getElementById('dns-default').checked) formData.append('is_default', '1');
            if (document.getElementById('dns-enabled').checked) formData.append('enabled', '1');
            document.querySelectorAll('.dns-credential').forEach(input => {
                formData.append('cred_' + input.dataset.field, input.value);
            });

            return ajaxPost(isEdit ? `${BASE_URL}/${account.id}/update` : BASE_URL, formData)
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'No se pudo guardar');
                    return data;
                })
                .catch(error => Swal.showValidationMessage(error.message));
        }
    }).then(result => {
        if (result.isConfirmed) location.reload();
    });
}

function testDnsAccount(id) {
    const formData = new FormData();
    Swal.fire({ title: 'Probando credenciales...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    ajaxPost(`${BASE_URL}/${id}/test`, formData)
        .then(data => {
            Swal.fire({
                icon: data.success ? 'success' : 'error',
                title: data.success ? 'Conexion verificada' : 'Error de conexion',
                text: data.message || data.error || ''
            }).then(() => location.reload());
        })
        .catch(error => Swal.fire('Error', error.message, 'error'));
}

function deleteDnsAccount(id, name) {
    Swal.fire({
        icon: 'warning',
        title: 'Eliminar cuenta DNS',
        text: `Se eliminara "${name}". Los dominios existentes no se borran.`,
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        confirmButtonColor: '#dc3545'
    }).then(result => {
        if (!result.isConfirmed) return;
        const formData = new FormData();
        ajaxPost(`${BASE_URL}/${id}/delete`, formData)
            .then(data => {
                if (!data.success) throw new Error(data.error || 'No se pudo eliminar');
                location.reload();
            })
            .catch(error => Swal.fire('Error', error.message, 'error'));
    });
}
</script>
@endsection
