@extends('Customer.layout')

@section('styles')
<style>
    .request-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .request-card .card-header {
        background: #17a2b8;
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 25px;
    }
    .info-box {
        background: #f8f9fa;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .warning-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .btn-primary {
        background: #17a2b8;
        border: none;
        padding: 12px 30px;
    }
    .btn-primary:hover {
        background: #138496;
    }
</style>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card request-card">
            <div class="card-header text-center">
                <h3 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Vincular Dominio Existente</h3>
                <p class="mb-0 mt-2 opacity-75">Conecta tu dominio a MuseDock cambiando solo los nameservers</p>
            </div>
            <div class="card-body p-4">
                <div class="info-box">
                    <h6><i class="bi bi-info-circle me-2"></i>Como funciona (solo cambio de DNS)</h6>
                    <ul class="mb-0 ps-3">
                        <li><strong>Tu dominio sigue siendo tuyo</strong> - no se transfiere, solo cambias los nameservers</li>
                        <li>Tu dominio sera protegido por Cloudflare automaticamente (SSL gratuito)</li>
                        <li>Recibiras instrucciones para cambiar los nameservers en tu registrador</li>
                        <li>Tu sitio se activara automaticamente cuando detectemos el cambio de DNS</li>
                    </ul>
                </div>

                <div class="warning-box">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Importante</h6>
                    <p class="mb-2">Debes ser el propietario del dominio y tener acceso para cambiar los nameservers en tu registrador (GoDaddy, Namecheap, Google Domains, etc.)</p>
                    <p class="mb-0 small"><i class="bi bi-shield-check me-1"></i>El dominio permanece registrado en tu proveedor actual - <strong>no es una transferencia</strong>.</p>
                </div>

                <form id="customDomainForm" onsubmit="submitRequest(event)">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Tu Dominio</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-globe"></i></span>
                            <input type="text" class="form-control" name="domain"
                                   placeholder="tudominio.com"
                                   pattern="^[a-zA-Z0-9][a-zA-Z0-9-]*\.[a-zA-Z]{2,}$"
                                   required>
                        </div>
                        <div class="form-text">Introduce tu dominio sin www (ejemplo: miempresa.com)</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enable_email_routing" id="enableEmailRouting">
                            <label class="form-check-label" for="enableEmailRouting">
                                <strong>Habilitar Email Routing</strong>
                                <br><small class="text-muted">Los correos enviados a cualquier direccion de tu dominio seran redirigidos a <?= htmlspecialchars($customer['email'] ?? '') ?></small>
                            </label>
                        </div>
                        <div class="form-text mt-2">
                            <i class="bi bi-lightbulb me-1 text-info"></i>
                            <em>Puedes activar o configurar el Email Routing mas adelante desde tu panel de control.</em>
                        </div>
                    </div>

                    <!-- Credenciales de Admin (opcionales) -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="customAdminToggle" onchange="toggleCustomAdmin()">
                                <label class="form-check-label fw-bold" for="customAdminToggle">
                                    Personalizar credenciales de admin
                                </label>
                            </div>
                        </div>
                        <div class="form-text mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Por defecto se generan credenciales automaticamente (admin@tudominio.com + password aleatorio).
                            Puedes personalizarlas si lo prefieres.
                        </div>

                        <div id="customAdminFields" style="display: none;">
                            <div class="card bg-light border-0 p-3">
                                <div class="mb-3">
                                    <label class="form-label">Email del Admin</label>
                                    <input type="email" class="form-control" name="admin_email"
                                           id="adminEmailInput"
                                           placeholder="admin@ejemplo.com">
                                    <div class="form-text">Debe ser un email valido y unico en el sistema.</div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Password del Admin</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="admin_password"
                                               id="adminPasswordInput"
                                               placeholder="Minimo 8 caracteres"
                                               minlength="8">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">
                                            <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimo 8 caracteres. Usa una combinacion segura.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-link-45deg me-2"></i>Vincular Dominio
                        </button>
                        <a href="/customer/dashboard" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Volver al Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleCustomAdmin() {
    const checkbox = document.getElementById('customAdminToggle');
    const fields = document.getElementById('customAdminFields');
    const emailInput = document.getElementById('adminEmailInput');
    const passwordInput = document.getElementById('adminPasswordInput');

    if (checkbox.checked) {
        fields.style.display = 'block';
    } else {
        fields.style.display = 'none';
        // Limpiar campos cuando se desactiva
        emailInput.value = '';
        passwordInput.value = '';
    }
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('adminPasswordInput');
    const icon = document.getElementById('passwordToggleIcon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function submitRequest(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const domain = formData.get('domain').toLowerCase().trim();

    // Validar dominio
    if (!domain || domain.includes('musedock.com')) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Para subdominios de musedock.com usa "Solicitar Subdominio FREE"'
        });
        return;
    }

    // Validar credenciales personalizadas si estan activadas
    const customAdminEnabled = document.getElementById('customAdminToggle').checked;
    if (customAdminEnabled) {
        const adminEmail = formData.get('admin_email').trim();
        const adminPassword = formData.get('admin_password');

        if (!adminEmail || !adminPassword) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Si personalizas las credenciales, debes completar email y password'
            });
            return;
        }

        // Validar formato de email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(adminEmail)) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El email del admin no tiene un formato valido'
            });
            return;
        }

        if (adminPassword.length < 8) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El password del admin debe tener al menos 8 caracteres'
            });
            return;
        }
    }

    Swal.fire({
        title: 'Procesando solicitud...',
        html: 'Estamos configurando tu dominio en Cloudflare...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('/customer/request-custom-domain', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let nsHtml = '<div class="text-start mt-3">';
            nsHtml += '<p><strong>Cambia los nameservers de tu dominio a:</strong></p>';
            nsHtml += '<div class="bg-light p-3 rounded">';
            data.nameservers.forEach((ns, i) => {
                nsHtml += `<p class="mb-1"><code>NS${i+1}: ${ns}</code></p>`;
            });
            nsHtml += '</div>';

            // Mostrar credenciales si fueron generadas automaticamente
            if (data.admin_credentials && !customAdminEnabled) {
                nsHtml += `<div class="mt-3 p-3 bg-success bg-opacity-10 rounded">
                    <strong>Credenciales de Admin:</strong><br>
                    <small>Email: <code>${data.admin_credentials.email}</code></small><br>
                    <small>Password: <code>${data.admin_credentials.password}</code></small><br>
                    <small class="text-muted">Guarda estas credenciales!</small>
                </div>`;
            }

            nsHtml += '<p class="mt-3 text-muted small">Te hemos enviado un email con instrucciones detalladas.</p>';
            nsHtml += '</div>';

            Swal.fire({
                icon: 'success',
                title: 'Dominio Registrado!',
                html: data.message + nsHtml,
                confirmButtonColor: '#667eea',
                confirmButtonText: 'Ir al Dashboard'
            }).then(() => {
                window.location.href = '/customer/dashboard';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Ocurrio un error al procesar la solicitud'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexion. Por favor intenta de nuevo.'
        });
    });
}
</script>
@endsection
