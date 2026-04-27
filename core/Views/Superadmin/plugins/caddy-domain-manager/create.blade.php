@extends('layouts.app')

@section('title', 'Nuevo Dominio')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-plus-circle"></i> Nuevo Dominio</h2>
                <p class="text-muted mb-0">Crea un nuevo tenant con dominio custom o subdominio</p>
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
                Puedes crear el tenant, pero la configuración automática de Caddy no funcionará.
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Información del Tenant</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/musedock/domain-manager" id="tenantCreateForm">
                    {!! csrf_field() !!}

                    <div class="row">
                        <!-- Datos del tenant -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-building"></i> Datos del Tenant</h6>

                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre del Tenant <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       placeholder="Mi Empresa" value="{{ old('name') }}">
                                <div class="form-text">Nombre identificativo del tenant</div>
                            </div>

                            <div class="mb-3">
                                <label for="domain" class="form-label">Dominio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">https://</span>
                                    <input type="text" class="form-control" id="domain" name="domain" required
                                           placeholder="miempresa.com o sub.musedock.com" value="{{ old('domain') }}">
                                </div>
                                <div class="form-text" id="domain-help-default">Acepta dominios custom (miempresa.com) o subdominios (mi-sitio.musedock.com)</div>
                                <div class="form-text text-success d-none" id="domain-help-subdomain">
                                    <i class="bi bi-check-circle"></i> <strong>Subdominio detectado.</strong> Se creará un CNAME automáticamente en Cloudflare. No necesita configuración DNS adicional.
                                </div>
                                <div class="form-text text-info d-none" id="domain-help-external-sub">
                                    <i class="bi bi-info-circle"></i> <strong>Subdominio externo detectado.</strong> Asegúrate de que el subdominio apunte a este servidor (CNAME o A record).
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="include_www" name="include_www" checked>
                                    <label class="form-check-label" for="include_www">
                                        Incluir www.dominio.com
                                    </label>
                                    <div class="form-text">Caddy configurará tanto el dominio base como con www</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="configure_caddy" name="configure_caddy"
                                           {{ $caddyApiAvailable ? 'checked' : 'disabled' }}>
                                    <label class="form-check-label" for="configure_caddy">
                                        Configurar automáticamente en Caddy
                                    </label>
                                    <div class="form-text">
                                        @if($caddyApiAvailable)
                                            Se creará la ruta en Caddy con SSL automático via Let's Encrypt
                                        @else
                                            Caddy API no disponible - configuración manual requerida
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <hr class="my-3">

                            <h6 class="text-success mb-3"><i class="bi bi-cloud"></i> DNS del dominio</h6>

                            <div class="mb-3">
                                <label class="form-label" for="dns_provider">Proveedor DNS</label>
                                <select class="form-select" id="dns_provider" name="dns_provider">
                                    @foreach(($dnsProviders ?? []) as $providerKey => $providerInfo)
                                        <option value="{{ $providerKey }}" {{ ($defaultDnsProvider ?? 'cloudflare') === $providerKey ? 'selected' : '' }}>
                                            {{ $providerInfo['label'] ?? $providerKey }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text" id="dns-provider-help">
                                    Cloudflare mantiene el flujo actual. Otros proveedores usan Cuentas DNS si estan configuradas, o quedan guardados para ACME/DNS-01.
                                </div>
                            </div>

                            <div class="mb-3" id="cloudflare-zone-option">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="configure_cloudflare" name="configure_cloudflare">
                                    <label class="form-check-label" for="configure_cloudflare">
                                        <strong>Añadir dominio a Cloudflare</strong>
                                    </label>
                                    <div class="form-text">
                                        Añade el dominio a Cloudflare Account 2 (Full Setup) con CNAMEs automáticos a mortadelo.musedock.com
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3" id="skip-cloudflare-option">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="skip_cloudflare" name="skip_cloudflare" checked>
                                    <label class="form-check-label" for="skip_cloudflare">
                                        <strong>No crear zona en Cloudflare</strong>
                                    </label>
                                    <div class="form-text">
                                        Marca esta opción si el dominio ya apunta a este servidor desde otro registrador/proveedor DNS.
                                    </div>
                                </div>
                            </div>

                            <div id="cloudflare-options" class="ps-4 d-none">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="enable_email_routing" name="enable_email_routing">
                                        <label class="form-check-label" for="enable_email_routing">
                                            <i class="bi bi-envelope-at"></i> Activar Email Routing
                                        </label>
                                        <div class="form-text">
                                            Configura Email Routing de Cloudflare para el dominio
                                        </div>
                                    </div>
                                </div>

                                <div id="email-routing-options" class="mb-3 d-none">
                                    <label for="email_routing_destination" class="form-label">
                                        Email Destino para Catch-All <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control" id="email_routing_destination"
                                           name="email_routing_destination" placeholder="destino@gmail.com">
                                    <div class="form-text">
                                        Todos los emails enviados a <strong id="domain-preview">tudominio.com</strong>
                                        serán redirigidos a este email. Por defecto se usa el email del admin.
                                    </div>
                                </div>

                                <div class="alert alert-info alert-sm mb-0">
                                    <small>
                                        <i class="bi bi-info-circle"></i> <strong>Importante:</strong>
                                        Al activar Cloudflare, se te proporcionarán los nameservers que debes configurar en tu registrador de dominios.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Datos del admin -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-person-badge"></i> Administrador del Tenant</h6>

                            <div class="mb-3">
                                <label for="admin_name" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="admin_name" name="admin_name" required
                                       placeholder="Juan García" value="{{ old('admin_name') }}">
                            </div>

                            <div class="mb-3">
                                <label for="admin_email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" required
                                       placeholder="admin@miempresa.com" value="{{ old('admin_email') }}">
                                <div class="form-text">Se usará para el login del admin del tenant</div>
                            </div>

                            <div class="mb-3">
                                <label for="admin_password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="admin_password" name="admin_password"
                                           required minlength="8" placeholder="Mínimo 8 caracteres">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" type="button" onclick="generatePassword()">
                                        <i class="bi bi-shuffle"></i> Generar
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="send_welcome_email" name="send_welcome_email" checked>
                                    <label class="form-check-label" for="send_welcome_email">
                                        <i class="bi bi-envelope"></i> Enviar email de bienvenida
                                    </label>
                                    <div class="form-text">Se enviará un email al administrador con los datos de acceso</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Info DNS -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Requisitos DNS</h6>
                        <p class="mb-2">Dependiendo del tipo de dominio:</p>
                        <ul class="mb-0">
                            <li><strong>Subdominio .{{ \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com') }}:</strong> DNS automático (CNAME). Solo configura Caddy.</li>
                            <li><strong>Dominio custom con Cloudflare:</strong> Se crearán nameservers. Configúralos en tu registrador.</li>
                            <li><strong>Dominio/subdominio sin Cloudflare:</strong> Asegúrate de que apunte a este servidor (A/CNAME) antes de configurar Caddy.</li>
                        </ul>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Proveedor DNS para DNS-01 (preflight)</label>
                            <select class="form-select" id="acme_dns_provider_create">
                                <option value="">Auto (sin DNS-01 forzado)</option>
                                @foreach(($dnsProviders ?? []) as $providerKey => $providerInfo)
                                    @if(($providerInfo['dns01'] ?? false) || $providerKey === 'cloudflare')
                                        <option value="{{ $providerKey }}">{{ $providerKey }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <div class="form-text">
                                Si eliges proveedor, el preflight validará DNS-01 con ese módulo/credenciales.
                            </div>
                        </div>
                    </div>

                    <div id="acmePreflightBanner" class="alert alert-warning d-none">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <strong><i class="bi bi-shield-exclamation me-1"></i>Firewall y Let's Encrypt</strong>
                                <div class="small mt-1" id="acmePreflightText">
                                    Detectando estado de puertos 80/443...
                                </div>
                            </div>
                            <a href="/musedock/settings/acme-assistant" class="btn btn-sm btn-outline-dark">
                                Abrir asistente
                            </a>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary" id="btnSubmit">
                            <span class="btn-text"><i class="bi bi-check-lg"></i> Crear Tenant</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                Configurando dominio...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
const csrfToken = '<?= csrf_token() ?>';
let acmeCreatePreflight = null;
let acmeCreateFormValidated = false;

function togglePassword() {
    const input = document.getElementById('admin_password');
    const icon = document.getElementById('togglePasswordIcon');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 16; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('admin_password').value = password;
    document.getElementById('admin_password').type = 'text';
    document.getElementById('togglePasswordIcon').classList.remove('bi-eye');
    document.getElementById('togglePasswordIcon').classList.add('bi-eye-slash');
}

// Detección de tipo de dominio
const baseDomain = '{{ \Screenart\Musedock\Env::get("TENANT_BASE_DOMAIN", "musedock.com") }}';

function detectDomainType(domain) {
    domain = domain.toLowerCase().replace(/^www\./, '').replace(/^https?:\/\//, '').split('/')[0];
    if (!domain) return 'empty';
    if (domain.endsWith('.' + baseDomain)) return 'musedock_sub';
    // Count dots: domain.com = 1 dot (TLD), sub.domain.com = 2+ dots
    const dots = (domain.match(/\./g) || []).length;
    if (dots >= 2) return 'external_sub';
    return 'custom';
}

function updateDomainUI() {
    const domain = document.getElementById('domain').value.trim();
    const type = detectDomainType(domain);

    const helpDefault = document.getElementById('domain-help-default');
    const helpSubdomain = document.getElementById('domain-help-subdomain');
    const helpExternalSub = document.getElementById('domain-help-external-sub');
    const cfZoneOption = document.getElementById('cloudflare-zone-option');
    const skipCfOption = document.getElementById('skip-cloudflare-option');
    const cfCheckbox = document.getElementById('configure_cloudflare');
    const skipCfCheckbox = document.getElementById('skip_cloudflare');
    const cloudflareOptions = document.getElementById('cloudflare-options');
    const dnsProvider = document.getElementById('dns_provider')?.value || 'cloudflare';

    // Reset
    helpDefault.classList.add('d-none');
    helpSubdomain.classList.add('d-none');
    helpExternalSub.classList.add('d-none');

    if (type === 'musedock_sub') {
        helpSubdomain.classList.remove('d-none');
        if (document.getElementById('dns_provider')) {
            document.getElementById('dns_provider').value = 'cloudflare';
        }
        // Ocultar opciones de Cloudflare — CNAME automático
        cfZoneOption.classList.add('d-none');
        skipCfOption.classList.add('d-none');
        cloudflareOptions.classList.add('d-none');
        cfCheckbox.checked = false;
        skipCfCheckbox.checked = false;
    } else if (type === 'external_sub') {
        helpExternalSub.classList.remove('d-none');
        cfZoneOption.classList.remove('d-none');
        skipCfOption.classList.remove('d-none');
    } else if (type === 'custom') {
        helpDefault.classList.remove('d-none');
        cfZoneOption.classList.remove('d-none');
        skipCfOption.classList.remove('d-none');
    } else {
        helpDefault.classList.remove('d-none');
        cfZoneOption.classList.remove('d-none');
        skipCfOption.classList.remove('d-none');
    }

    if (type !== 'musedock_sub' && dnsProvider !== 'cloudflare') {
        cfZoneOption.classList.add('d-none');
        cloudflareOptions.classList.add('d-none');
        cfCheckbox.checked = false;
        skipCfCheckbox.checked = true;
    }
}

document.getElementById('domain').addEventListener('input', updateDomainUI);

function syncDnsProviderUI() {
    const provider = document.getElementById('dns_provider')?.value || 'cloudflare';
    const acmeProvider = document.getElementById('acme_dns_provider_create');
    if (acmeProvider) {
        acmeProvider.value = provider === 'manual' ? '' : provider;
    }

    if (provider !== 'cloudflare') {
        document.getElementById('configure_cloudflare').checked = false;
        document.getElementById('skip_cloudflare').checked = true;
        document.getElementById('cloudflare-options').classList.add('d-none');
        document.getElementById('enable_email_routing').checked = false;
        document.getElementById('email-routing-options').classList.add('d-none');
    }

    updateDomainUI();
    runCreateAcmePreflight().catch(() => {});
}

document.getElementById('dns_provider')?.addEventListener('change', syncDnsProviderUI);

// Exclusión mutua: configure_cloudflare vs skip_cloudflare
document.getElementById('configure_cloudflare').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('skip_cloudflare').checked = false;
    }
});
document.getElementById('skip_cloudflare').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('configure_cloudflare').checked = false;
        document.getElementById('cloudflare-options').classList.add('d-none');
        document.getElementById('enable_email_routing').checked = false;
        document.getElementById('email-routing-options').classList.add('d-none');
    }
});

// Mostrar/ocultar opciones de Cloudflare
document.getElementById('configure_cloudflare').addEventListener('change', function() {
    const cloudflareOptions = document.getElementById('cloudflare-options');
    const emailRoutingCheckbox = document.getElementById('enable_email_routing');

    if (this.checked) {
        cloudflareOptions.classList.remove('d-none');
    } else {
        cloudflareOptions.classList.add('d-none');
        emailRoutingCheckbox.checked = false;
        document.getElementById('email-routing-options').classList.add('d-none');
    }
});

// Mostrar/ocultar campo de email routing destination
document.getElementById('enable_email_routing').addEventListener('change', function() {
    const emailRoutingOptions = document.getElementById('email-routing-options');
    const destinationInput = document.getElementById('email_routing_destination');

    if (this.checked) {
        emailRoutingOptions.classList.remove('d-none');
        // Auto-rellenar con email del admin si está vacío
        if (!destinationInput.value) {
            const adminEmail = document.getElementById('admin_email').value;
            if (adminEmail) {
                destinationInput.value = adminEmail;
            }
        }
    } else {
        emailRoutingOptions.classList.add('d-none');
    }
});

// Actualizar preview del dominio
document.getElementById('domain').addEventListener('input', function() {
    const domainPreview = document.getElementById('domain-preview');
    if (domainPreview) {
        domainPreview.textContent = this.value || 'tudominio.com';
    }
});

// Auto-rellenar email routing con admin_email cuando cambia
document.getElementById('admin_email').addEventListener('input', function() {
    const destinationInput = document.getElementById('email_routing_destination');
    const emailRoutingEnabled = document.getElementById('enable_email_routing').checked;

    // Solo auto-rellenar si Email Routing está activo y el campo está vacío
    if (emailRoutingEnabled && !destinationInput.value) {
        destinationInput.value = this.value;
    }
});

function setCreateSubmitLoading(loading) {
    const btn = document.getElementById('btnSubmit');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    if (loading) {
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        btn.disabled = true;
    } else {
        btnText.classList.remove('d-none');
        btnLoading.classList.add('d-none');
        btn.disabled = false;
    }
}

function getCreateAcmeMethod() {
    const provider = (document.getElementById('acme_dns_provider_create')?.value || '').trim();
    if (provider !== '') {
        return 'dns-01';
    }
    const cfChecked = document.getElementById('configure_cloudflare')?.checked;
    return cfChecked ? 'dns-01' : 'auto';
}

function getCreateProvider() {
    const provider = (document.getElementById('acme_dns_provider_create')?.value || '').trim();
    if (provider !== '') {
        return provider;
    }
    const cfChecked = document.getElementById('configure_cloudflare')?.checked;
    return cfChecked ? 'cloudflare' : 'cloudflare';
}

function renderCreateAcmeBanner(preflight) {
    const banner = document.getElementById('acmePreflightBanner');
    const text = document.getElementById('acmePreflightText');
    if (!banner || !text) return;

    const port80Open = !!(preflight?.firewall?.ports?.['80']?.public_open);
    const port443Open = !!(preflight?.firewall?.ports?.['443']?.public_open);
    const method = preflight?.challenge || 'auto';
    const estimate = preflight?.estimate || {};

    if (method === 'dns-01') {
        const providerReady = !!preflight?.provider_check?.ready;
        if (!providerReady) {
            const nextSteps = (preflight?.provider_check?.next_steps || []).join(' ');
            banner.classList.remove('d-none', 'alert-info', 'alert-warning');
            banner.classList.add('alert-danger');
            text.innerHTML = `DNS-01 bloqueado: ${(preflight?.provider_check?.summary || 'proveedor no listo')}. ${nextSteps}`;
            return;
        }

        banner.classList.remove('d-none', 'alert-warning', 'alert-danger');
        banner.classList.add('alert-info');
        text.innerHTML = `Modo DNS-01 listo. Estimación: ${estimate?.expected_duration || '60-240s'}.`;
        return;
    }

    if (!port80Open || !port443Open) {
        banner.classList.remove('d-none', 'alert-info');
        banner.classList.add('alert-warning');
        text.innerHTML = `Firewall detectado: ${preflight?.firewall?.firewall || 'unknown'}. No hay apertura pública para: ${!port80Open ? '80 ' : ''}${!port443Open ? '443' : ''}. Si guardas con HTTP-01/TLS-ALPN-01, el panel pedirá contraseña y abrirá puertos temporalmente.`;
        return;
    }

    banner.classList.remove('d-none', 'alert-warning', 'alert-danger');
    banner.classList.add('alert-info');
    text.innerHTML = `Puertos 80/443 detectados como públicos para ACME. Estimación: ${estimate?.expected_duration || '60-180s'}.`;
}

async function runCreateAcmePreflight() {
    const domain = (document.getElementById('domain')?.value || '').trim();
    if (!domain) return null;

    const resp = await fetch('/musedock/settings/acme-assistant/dry-run', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            _csrf: csrfToken,
            domain: domain,
            challenge: getCreateAcmeMethod(),
            provider: getCreateProvider()
        })
    });
    const data = await resp.json();
    if (!data.success) {
        throw new Error(data.message || 'No se pudo ejecutar preflight ACME');
    }
    acmeCreatePreflight = data.result;
    renderCreateAcmeBanner(acmeCreatePreflight);
    return acmeCreatePreflight;
}

async function maybeOpenTemporaryPortsBeforeCreateSubmit() {
    const preflight = acmeCreatePreflight || await runCreateAcmePreflight();
    if (!preflight) return true;

    if (!preflight.requires_temporary_ports) {
        return true;
    }

    const modal = await Swal.fire({
        title: 'Asistencia ACME',
        html: `
            <div class="text-start">
                <p><strong>Firewall y Let's Encrypt</strong></p>
                <p class="mb-2">Firewall detectado: ${preflight.firewall?.firewall || 'unknown'}. No hay apertura publica para 80 y/o 443.</p>
                <p class="mb-2">Si guardas con HTTP-01/TLS-ALPN-01, el panel puede abrir esos puertos temporalmente durante 30 minutos para emitir certificado.</p>
                <label class="form-label"><strong>Contraseña de administrador</strong></label>
                <input type="password" id="acmeAssistAdminPassword" class="form-control" autocomplete="current-password">
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Abrir temporalmente y guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f59f00',
        preConfirm: () => {
            const password = document.getElementById('acmeAssistAdminPassword')?.value || '';
            if (!password) {
                Swal.showValidationMessage('Debes introducir la contraseña de administrador');
                return false;
            }
            return password;
        }
    });

    if (!modal.isConfirmed) {
        return false;
    }

    const openResp = await fetch('/musedock/settings/acme-assistant/open-temporary', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            _csrf: csrfToken,
            ticket_id: 'domain-create',
            ttl_seconds: 1800,
            password: modal.value
        })
    });
    const openData = await openResp.json();
    if (!openData.success) {
        throw new Error(openData.message || 'No se pudieron abrir puertos temporalmente');
    }

    await Swal.fire({
        icon: 'success',
        title: 'Puertos abiertos temporalmente',
        text: 'Se abrirán durante 30 minutos para facilitar la emisión del certificado.',
        timer: 2200,
        showConfirmButton: false
    });

    return true;
}

document.getElementById('tenantCreateForm').addEventListener('submit', async function(e) {
    if (acmeCreateFormValidated) {
        setCreateSubmitLoading(true);
        return;
    }

    e.preventDefault();
    setCreateSubmitLoading(true);

    try {
        await runCreateAcmePreflight();
        const proceed = await maybeOpenTemporaryPortsBeforeCreateSubmit();
        if (!proceed) {
            setCreateSubmitLoading(false);
            return;
        }
        acmeCreateFormValidated = true;
        this.submit();
    } catch (error) {
        setCreateSubmitLoading(false);
        Swal.fire({
            icon: 'error',
            title: 'No se pudo continuar',
            text: error.message || 'Error en la asistencia ACME.'
        });
    }
});

document.getElementById('domain').addEventListener('blur', function() {
    runCreateAcmePreflight().catch(() => {});
});

document.getElementById('configure_cloudflare').addEventListener('change', function() {
    runCreateAcmePreflight().catch(() => {});
});

document.getElementById('acme_dns_provider_create')?.addEventListener('change', function() {
    const provider = this.value;
    if (provider && document.getElementById('dns_provider')) {
        document.getElementById('dns_provider').value = provider;
        updateDomainUI();
    }
    runCreateAcmePreflight().catch(() => {});
});

syncDnsProviderUI();
runCreateAcmePreflight().catch(() => {});
</script>
@endpush

@endsection
