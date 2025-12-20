@extends('layouts.app')

@section('title', 'Nuevo Dominio Custom')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-plus-circle"></i> Nuevo Dominio Custom</h2>
                <p class="text-muted mb-0">Crea un nuevo tenant con dominio personalizado</p>
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
                <form method="POST" action="/musedock/domain-manager">
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
                                           placeholder="miempresa.com" value="{{ old('domain') }}">
                                </div>
                                <div class="form-text">El dominio debe apuntar a este servidor antes de configurar Caddy</div>
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

                            <h6 class="text-success mb-3"><i class="bi bi-cloud"></i> Cloudflare (Opcional)</h6>

                            <div class="mb-3">
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

                            <div id="cloudflare-options" class="ps-4 d-none">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="enable_email_routing" name="enable_email_routing">
                                        <label class="form-check-label" for="enable_email_routing">
                                            <i class="bi bi-envelope-at"></i> Activar Email Routing
                                        </label>
                                        <div class="form-text">
                                            Configura Email Routing de Cloudflare para el dominio.
                                            Se creará un catch-all que redirige a <strong id="email-routing-dest">admin@miempresa.com</strong>
                                        </div>
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
                        <p class="mb-2">Antes de configurar el dominio en Caddy, asegúrate de que:</p>
                        <ol class="mb-0">
                            <li>El registro A del dominio apunte a la IP de este servidor</li>
                            <li>Si incluyes www, también el registro CNAME o A para www</li>
                            <li>La propagación DNS esté completa (puede tardar hasta 48h)</li>
                        </ol>
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

// Mostrar/ocultar opciones de Cloudflare
document.getElementById('configure_cloudflare').addEventListener('change', function() {
    const cloudflareOptions = document.getElementById('cloudflare-options');
    if (this.checked) {
        cloudflareOptions.classList.remove('d-none');
    } else {
        cloudflareOptions.classList.add('d-none');
        document.getElementById('enable_email_routing').checked = false;
    }
});

// Actualizar email destino dinámicamente cuando cambia el admin_email
document.getElementById('admin_email').addEventListener('input', function() {
    const emailRoutingDest = document.getElementById('email-routing-dest');
    if (emailRoutingDest) {
        emailRoutingDest.textContent = this.value || 'admin@miempresa.com';
    }
});

// Spinner en el botón de submit
document.querySelector('form').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnSubmit');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');

    // Mostrar spinner
    btnText.classList.add('d-none');
    btnLoading.classList.remove('d-none');
    btn.disabled = true;
});
</script>
@endpush

@endsection
