@extends('layouts.app')

@section('title', $title)

@section('content')
@php
    $port80Open = (bool)($firewall['ports']['80']['public_open'] ?? false);
    $port443Open = (bool)($firewall['ports']['443']['public_open'] ?? false);
    $firewallName = $firewall['firewall'] ?? 'unknown';
    $loadedProviders = $providers['loaded'] ?? [];
    $availableProviders = $providers['available'] ?? [];
    $acmeRuntime = $acmeStatus['runtime'] ?? [];
    $acmeHistory = $acmeStatus['history'] ?? [];
    $acmeLast = $acmeStatus['last'] ?? null;
@endphp

<div class="container">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0"><i class="bi bi-shield-check me-2"></i>Firewall y Let's Encrypt</h3>
            <a href="/musedock/settings/advanced" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-0">
                <strong>Objetivo:</strong> diagnosticar emisión/renovación ACME, validar proveedor DNS y abrir 80/443 temporalmente cuando uses HTTP-01 o TLS-ALPN-01.
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header"><strong>Firewall Detectado</strong></div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="badge bg-primary" id="fwName">{{ strtoupper($firewallName) }}</span>
                    </div>
                    <small class="text-muted">Policy INPUT: <strong id="fwPolicy">{{ $firewall['input_policy'] ?? 'unknown' }}</strong></small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header"><strong>Puerto 80 (HTTP-01)</strong></div>
                <div class="card-body">
                    <div id="port80Badge">
                        @if($port80Open)
                            <span class="badge bg-success">Abierto públicamente</span>
                        @else
                            <span class="badge bg-danger">Cerrado públicamente</span>
                        @endif
                    </div>
                    <small class="text-muted d-block mt-2">Necesario para `http-01`.</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header"><strong>Puerto 443 (TLS-ALPN-01)</strong></div>
                <div class="card-body">
                    <div id="port443Badge">
                        @if($port443Open)
                            <span class="badge bg-success">Abierto públicamente</span>
                        @else
                            <span class="badge bg-danger">Cerrado públicamente</span>
                        @endif
                    </div>
                    <small class="text-muted d-block mt-2">Necesario para `tls-alpn-01`.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-activity me-1"></i>Estado ACME Persistente</h5>
            <span id="acmeRuntimeBadge" class="badge bg-secondary">
                {{ strtoupper((string)($acmeRuntime['status'] ?? 'unknown')) }}
            </span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-7">
                    <p class="mb-1"><strong>Último evento:</strong></p>
                    <div id="acmeRuntimeMessage" class="small text-muted mb-2">
                        {{ $acmeRuntime['message'] ?? ($acmeLast['message'] ?? 'Sin eventos registrados todavía.') }}
                    </div>
                    <div id="acmeRuntimeDetail" class="small mb-2">
                        @if(!empty($acmeRuntime['detail']))
                            <code>{{ $acmeRuntime['detail'] }}</code>
                        @elseif(!empty($acmeLast['detail']))
                            <code>{{ $acmeLast['detail'] }}</code>
                        @endif
                    </div>
                    <div id="acmeRuntimeNextStep" class="alert alert-warning py-2 small mb-0">
                        <strong>Siguiente paso:</strong> {{ $acmeRuntime['next_step'] ?? ($acmeLast['next_step'] ?? 'Ejecuta un dry-run y corrige bloqueos detectados.') }}
                    </div>
                </div>
                <div class="col-lg-5">
                    <p class="mb-1"><strong>Histórico reciente</strong></p>
                    <ul class="list-group list-group-flush small" id="acmeHistoryList" style="max-height: 200px; overflow-y: auto;">
                        @forelse(array_reverse(array_slice($acmeHistory, -8)) as $event)
                            <li class="list-group-item px-0 py-2">
                                <span class="badge bg-{{ ($event['status'] ?? '') === 'error' ? 'danger' : (($event['status'] ?? '') === 'success' ? 'success' : (($event['status'] ?? '') === 'warning' ? 'warning text-dark' : 'secondary')) }} me-1">
                                    {{ strtoupper($event['status'] ?? 'info') }}
                                </span>
                                <span>{{ $event['title'] ?? 'Evento' }}</span><br>
                                <span class="text-muted">{{ $event['message'] ?? '' }}</span><br>
                                <span class="text-muted">{{ $event['ts'] ?? '' }}</span>
                            </li>
                        @empty
                            <li class="list-group-item px-0 py-2 text-muted">Sin eventos guardados.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-cloud me-1"></i>Proveedores DNS en Caddy</h5>
        </div>
        <div class="card-body">
            <p class="mb-2">
                Módulos `dns.providers.*` cargados:
            </p>
            <div id="loadedProviders">
                @if(!empty($loadedProviders))
                    @foreach($loadedProviders as $provider)
                        <span class="badge bg-success me-1 mb-1">{{ $provider }}</span>
                    @endforeach
                @else
                    <span class="badge bg-secondary">Ninguno detectado</span>
                @endif
            </div>

            <hr>
            <p class="mb-2">Matriz soportada por el asistente:</p>
            <div>
                @foreach($availableProviders as $provider)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ $provider }}</span>
                @endforeach
            </div>

            <hr>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="providerStatusTable">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Módulo</th>
                            <th>Credenciales</th>
                            <th>Diagnóstico</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($providerStatuses ?? []) as $provider => $status)
                            @php
                                $moduleLoaded = in_array($provider, $loadedProviders, true);
                                $credsOk = (bool)($status['ok'] ?? false);
                                $missingGroups = $status['missing'] ?? [];
                                $missingText = [];
                                foreach ($missingGroups as $groupVars) {
                                    $missingText[] = is_array($groupVars) ? implode(' o ', $groupVars) : '';
                                }
                            @endphp
                            <tr>
                                <td><code>{{ $provider }}</code></td>
                                <td>
                                    @if($moduleLoaded)
                                        <span class="badge bg-success">Cargado</span>
                                    @else
                                        <span class="badge bg-danger">Falta módulo</span>
                                    @endif
                                </td>
                                <td>
                                    @if($credsOk)
                                        <span class="badge bg-success">OK</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Incompleto</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @if($moduleLoaded && $credsOk)
                                        Listo para DNS-01.
                                    @elseif($moduleLoaded && !$credsOk)
                                        Módulo instalado, faltan credenciales: {{ implode(' | ', array_filter($missingText)) ?: 'revisar .env' }}.
                                    @elseif(!$moduleLoaded && $credsOk)
                                        Credenciales presentes, falta módulo dns.providers.
                                    @else
                                        Falta módulo y credenciales.
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-patch-check me-1"></i>Validación Post-Instalación de Proveedor</h5>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Proveedor DNS</label>
                    <select class="form-select" id="providerCheckSelect">
                        @foreach($availableProviders as $provider)
                            <option value="{{ $provider }}">{{ $provider }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn btn-outline-primary" id="btnProviderCheck">
                        <i class="bi bi-search me-1"></i>Validar proveedor
                    </button>
                </div>
            </div>
            <div id="providerCheckResult" class="mt-3"></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-lightning-charge me-1"></i>Abrir 80/443 Temporalmente</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Abre 80 y 443 durante 30 minutos para completar emisión ACME con `http-01` / `tls-alpn-01`.
                Después se cierran automáticamente.
            </p>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-warning" id="btnOpenTemporary">
                    <i class="bi bi-unlock me-1"></i>Abrir 30 min
                </button>
                <button type="button" class="btn btn-outline-danger" id="btnCloseTemporary">
                    <i class="bi bi-lock me-1"></i>Cerrar ahora
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnRefreshStatus">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar estado
                </button>
            </div>
            <div id="assistantResult" class="mt-3"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-journal-text me-1"></i>Referencia Rápida</h5>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li>`dns-01`: no requiere abrir 80/443, pero sí módulo DNS + credenciales válidas.</li>
                <li>`http-01`: requiere 80 público desde Internet.</li>
                <li>`tls-alpn-01`: requiere 443 público desde Internet.</li>
                <li>Si usas proxy naranja, normalmente conviene `dns-01`.</li>
            </ul>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-book me-1"></i>Guía DNS-01 y Escenarios</h5>
        </div>
        <div class="card-body">
            <h6 class="text-primary">1) Dominio apuntando directo al servidor</h6>
            <p class="mb-2 small">Ejemplo: <code>panel.midominio.com</code> apunta por A/AAAA al servidor.</p>
            <ul class="small">
                <li>Con 80/443 abiertos puedes usar `http-01` o `tls-alpn-01`.</li>
                <li>Si quieres mantener 80/443 cerrados, usa `dns-01`.</li>
            </ul>

            <h6 class="text-primary mt-3">2) Dominio detrás de proxy naranja</h6>
            <p class="mb-2 small">Si el DNS responde a un proxy intermedio, `http-01` suele fallar o ser inestable.</p>
            <ul class="small">
                <li>Recomendado: `dns-01` con API token del proveedor DNS.</li>
                <li>Verifica que Caddy tenga el módulo <code>dns.providers.&lt;proveedor&gt;</code>.</li>
            </ul>

            <h6 class="text-primary mt-3">3) Instalación nueva sin hostname definido</h6>
            <ul class="small">
                <li>Accede por IP para bootstrap inicial.</li>
                <li>Define hostname desde panel.</li>
                <li>Ejecuta dry-run y corrige firewall/proveedor antes de guardar definitivamente.</li>
            </ul>

            <h6 class="text-primary mt-3">4) ¿Qué pasa con renovaciones?</h6>
            <ul class="small">
                <li>Con `http-01`/`tls-alpn-01`, renovaciones futuras requieren que el puerto de challenge siga accesible.</li>
                <li>Con `dns-01`, no dependes de exponer 80/443 para renovar.</li>
            </ul>

            <div class="alert alert-light border small mb-0">
                El asistente está pensado para flujo “guardar y operar”: primero diagnostica, luego te propone apertura temporal o migración a DNS-01 según el contexto real.
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const csrfToken = '<?= csrf_token() ?>';
const tempTicketId = 'settings-assistant';
const defaultTtlSeconds = {{ (int)($defaultTtlSeconds ?? 1800) }};

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderAssistantResult(type, message) {
    const el = document.getElementById('assistantResult');
    const cls = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'danger');
    el.innerHTML = `<div class="alert alert-${cls} py-2 mb-0">${message}</div>`;
}

function updateBadge(elId, isOpen) {
    const el = document.getElementById(elId);
    el.innerHTML = isOpen
        ? '<span class="badge bg-success">Abierto públicamente</span>'
        : '<span class="badge bg-danger">Cerrado públicamente</span>';
}

function renderAcmeStatus(acmeStatus) {
    const runtime = acmeStatus?.runtime || {};
    const history = Array.isArray(acmeStatus?.history) ? acmeStatus.history : [];
    const status = String(runtime.status || 'unknown').toLowerCase();
    const badge = document.getElementById('acmeRuntimeBadge');
    const statusClass = status === 'error'
        ? 'danger'
        : (status === 'success' ? 'success' : (status === 'warning' ? 'warning text-dark' : 'secondary'));
    badge.className = `badge bg-${statusClass}`;
    badge.textContent = status.toUpperCase();

    document.getElementById('acmeRuntimeMessage').textContent = runtime.message || 'Sin eventos ACME recientes.';
    const detailEl = document.getElementById('acmeRuntimeDetail');
    detailEl.innerHTML = runtime.detail ? `<code>${escapeHtml(runtime.detail)}</code>` : '';
    document.getElementById('acmeRuntimeNextStep').innerHTML = `<strong>Siguiente paso:</strong> ${escapeHtml(runtime.next_step || 'Ejecuta un dry-run y corrige bloqueos detectados.')}`;

    const historyEl = document.getElementById('acmeHistoryList');
    if (!history.length) {
        historyEl.innerHTML = '<li class="list-group-item px-0 py-2 text-muted">Sin eventos guardados.</li>';
        return;
    }

    const items = history.slice(-8).reverse().map(event => {
        const st = String(event.status || 'info').toLowerCase();
        const badgeCls = st === 'error' ? 'danger' : (st === 'success' ? 'success' : (st === 'warning' ? 'warning text-dark' : 'secondary'));
        return `
            <li class="list-group-item px-0 py-2">
                <span class="badge bg-${badgeCls} me-1">${escapeHtml(st.toUpperCase())}</span>
                <span>${escapeHtml(event.title || 'Evento')}</span><br>
                <span class="text-muted">${escapeHtml(event.message || '')}</span><br>
                <span class="text-muted">${escapeHtml(event.ts || '')}</span>
            </li>
        `;
    });
    historyEl.innerHTML = items.join('');
}

function renderProviderStatusTable(loaded, providerStatuses) {
    const tbody = document.querySelector('#providerStatusTable tbody');
    if (!tbody) return;

    const rows = [];
    Object.keys(providerStatuses || {}).forEach(provider => {
        const status = providerStatuses[provider] || {};
        const moduleLoaded = loaded.includes(provider);
        const credsOk = !!status.ok;
        const missing = status.missing || {};
        const missingText = Object.values(missing)
            .map(groupVars => Array.isArray(groupVars) ? groupVars.join(' o ') : '')
            .filter(Boolean)
            .join(' | ');

        let diagnosis = 'Listo para DNS-01.';
        if (!moduleLoaded && !credsOk) {
            diagnosis = 'Falta módulo y credenciales.';
        } else if (!moduleLoaded && credsOk) {
            diagnosis = 'Credenciales presentes, falta módulo dns.providers.';
        } else if (moduleLoaded && !credsOk) {
            diagnosis = `Módulo instalado, faltan credenciales: ${missingText || 'revisar .env'}.`;
        }

        rows.push(`
            <tr>
                <td><code>${escapeHtml(provider)}</code></td>
                <td>${moduleLoaded ? '<span class="badge bg-success">Cargado</span>' : '<span class="badge bg-danger">Falta módulo</span>'}</td>
                <td>${credsOk ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-warning text-dark">Incompleto</span>'}</td>
                <td class="small text-muted">${escapeHtml(diagnosis)}</td>
            </tr>
        `);
    });
    tbody.innerHTML = rows.join('');
}

async function refreshStatus() {
    const res = await fetch('/musedock/settings/acme-assistant/status', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (!data.success) {
        throw new Error(data.message || 'No se pudo obtener el estado');
    }

    document.getElementById('fwName').textContent = String((data.firewall.firewall || 'unknown')).toUpperCase();
    document.getElementById('fwPolicy').textContent = data.firewall.input_policy || 'unknown';
    updateBadge('port80Badge', !!(data.firewall.ports?.['80']?.public_open));
    updateBadge('port443Badge', !!(data.firewall.ports?.['443']?.public_open));

    const loaded = Array.isArray(data.providers?.loaded) ? data.providers.loaded : [];
    const container = document.getElementById('loadedProviders');
    if (!loaded.length) {
        container.innerHTML = '<span class="badge bg-secondary">Ninguno detectado</span>';
    } else {
        container.innerHTML = loaded.map(p => `<span class="badge bg-success me-1 mb-1">${escapeHtml(p)}</span>`).join('');
    }

    renderProviderStatusTable(loaded, data.provider_statuses || {});
    renderAcmeStatus(data.acme_status || {});
    return data;
}

async function runProviderCheck() {
    const provider = document.getElementById('providerCheckSelect')?.value || 'cloudflare';
    const res = await fetch('/musedock/settings/acme-assistant/provider-check', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            _csrf: csrfToken,
            provider: provider
        })
    });
    const data = await res.json();
    if (!data.success) {
        throw new Error(data.message || 'No se pudo validar el proveedor.');
    }

    const check = data.check || {};
    const status = check.status || 'blocked';
    const cls = status === 'ready' ? 'success' : 'danger';
    const next = Array.isArray(check.next_steps) ? check.next_steps : [];
    const tips = Array.isArray(check.security_tips) ? check.security_tips : [];
    const resultEl = document.getElementById('providerCheckResult');
    resultEl.innerHTML = `
        <div class="alert alert-${cls} mb-2">
            <strong>${escapeHtml((check.provider || provider))}:</strong> ${escapeHtml(check.summary || '')}
        </div>
        <div class="small mb-2"><strong>Siguientes pasos:</strong><br>${next.map(step => `- ${escapeHtml(step)}`).join('<br>')}</div>
        <div class="small text-muted"><strong>Seguridad:</strong><br>${tips.map(t => `- ${escapeHtml(t)}`).join('<br>')}</div>
    `;
}

async function openTemporaryPorts() {
    const modal = await Swal.fire({
        title: 'Abrir puertos temporalmente',
        html: `
            <div class="text-start">
                <p class="mb-2">Se abrirán 80 y 443 durante 30 minutos para emisión ACME.</p>
                <label class="form-label"><strong>Contraseña de administrador</strong></label>
                <input type="password" id="acmeAdminPassword" class="form-control" autocomplete="current-password">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Abrir temporalmente',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f59f00',
        preConfirm: () => {
            const password = document.getElementById('acmeAdminPassword').value;
            if (!password) {
                Swal.showValidationMessage('Debes introducir la contraseña de administrador');
                return false;
            }
            return password;
        }
    });

    if (!modal.isConfirmed) return;

    const res = await fetch('/musedock/settings/acme-assistant/open-temporary', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            _csrf: csrfToken,
            ticket_id: tempTicketId,
            ttl_seconds: defaultTtlSeconds,
            password: modal.value
        })
    });
    const data = await res.json();
    if (!data.success) {
        throw new Error(data.message || 'No se pudieron abrir los puertos');
    }

    renderAssistantResult('success', `Puertos abiertos temporalmente. Expiran: <strong>${escapeHtml(data.expires_at || 'en 30 minutos')}</strong>.`);
    await refreshStatus();
}

async function closeTemporaryPorts() {
    const modal = await Swal.fire({
        title: 'Cerrar puertos temporales',
        html: `
            <div class="text-start">
                <p class="mb-2">Se eliminarán las reglas temporales de ACME para 80/443.</p>
                <label class="form-label"><strong>Contraseña de administrador</strong></label>
                <input type="password" id="acmeClosePassword" class="form-control" autocomplete="current-password">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Cerrar puertos',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        preConfirm: () => {
            const password = document.getElementById('acmeClosePassword').value;
            if (!password) {
                Swal.showValidationMessage('Debes introducir la contraseña de administrador');
                return false;
            }
            return password;
        }
    });

    if (!modal.isConfirmed) return;

    const res = await fetch('/musedock/settings/acme-assistant/close-temporary', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            _csrf: csrfToken,
            ticket_id: tempTicketId,
            password: modal.value
        })
    });
    const data = await res.json();
    if (!data.success) {
        throw new Error(data.message || 'No se pudieron cerrar los puertos');
    }

    renderAssistantResult('warning', data.message || 'Reglas temporales eliminadas.');
    await refreshStatus();
}

document.getElementById('btnRefreshStatus')?.addEventListener('click', async () => {
    try {
        await refreshStatus();
        renderAssistantResult('success', 'Estado actualizado.');
    } catch (err) {
        renderAssistantResult('danger', err.message || 'Error actualizando estado.');
    }
});

document.getElementById('btnProviderCheck')?.addEventListener('click', async () => {
    try {
        await runProviderCheck();
    } catch (err) {
        const result = document.getElementById('providerCheckResult');
        result.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(err.message || 'Error validando proveedor.')}</div>`;
    }
});

document.getElementById('btnOpenTemporary')?.addEventListener('click', async () => {
    try {
        await openTemporaryPorts();
    } catch (err) {
        renderAssistantResult('danger', err.message || 'Error al abrir puertos.');
    }
});

document.getElementById('btnCloseTemporary')?.addEventListener('click', async () => {
    try {
        await closeTemporaryPorts();
    } catch (err) {
        renderAssistantResult('danger', err.message || 'Error al cerrar puertos.');
    }
});
</script>
@endpush
