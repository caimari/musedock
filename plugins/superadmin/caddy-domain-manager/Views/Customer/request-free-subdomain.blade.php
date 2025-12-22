@extends('Customer.layout')

@section('styles')
<style>
    .request-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .request-card .card-header {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 25px;
    }
    .info-box {
        background: #d4edda;
        border-left: 4px solid #28a745;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        padding: 12px 30px;
    }
    .btn-success:hover {
        background: linear-gradient(135deg, #218838, #1aa085);
    }
    .subdomain-preview {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        font-family: monospace;
        font-size: 1.1rem;
        text-align: center;
        margin-top: 10px;
    }
</style>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card request-card">
            <div class="card-header text-center">
                <h3 class="mb-0"><i class="bi bi-gift me-2"></i>Solicitar Subdominio FREE</h3>
                <p class="mb-0 mt-2 opacity-75">Obtiene tu sitio web gratuito en musedock.com</p>
            </div>
            <div class="card-body p-4">
                <div class="info-box">
                    <h6><i class="bi bi-check-circle me-2"></i>Incluido en tu plan FREE</h6>
                    <ul class="mb-0 ps-3">
                        <li>Subdominio gratuito: tuempresa.musedock.com</li>
                        <li>SSL automatico incluido</li>
                        <li>Proteccion Cloudflare</li>
                        <li>Activacion instantanea</li>
                    </ul>
                </div>

                <form id="freeSubdomainForm" onsubmit="submitRequest(event)">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Elige tu subdominio</label>
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control" name="subdomain"
                                   id="subdomainInput"
                                   placeholder="tuempresa"
                                   pattern="^[a-z0-9][a-z0-9-]{2,30}[a-z0-9]$"
                                   required
                                   oninput="updatePreview()">
                            <span class="input-group-text">.musedock.com</span>
                        </div>
                        <div class="form-text">Solo letras minusculas, numeros y guiones. Minimo 4 caracteres.</div>
                        <div class="subdomain-preview" id="subdomainPreview">
                            <span class="text-muted">tuempresa</span>.musedock.com
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
                            Por defecto se generan credenciales automaticamente (admin@tusubdominio.musedock.com + password aleatorio).
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
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-rocket-takeoff me-2"></i>Crear mi Subdominio FREE
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
function updatePreview() {
    const input = document.getElementById('subdomainInput');
    const preview = document.getElementById('subdomainPreview');
    const value = input.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
    input.value = value;
    preview.innerHTML = `<strong>${value || '<span class="text-muted">tuempresa</span>'}</strong>.musedock.com`;
}

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
    const subdomain = formData.get('subdomain').toLowerCase().trim();

    // Validar subdominio
    if (subdomain.length < 4) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El subdominio debe tener al menos 4 caracteres'
        });
        return;
    }

    if (!/^[a-z0-9][a-z0-9-]*[a-z0-9]$/.test(subdomain)) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El subdominio solo puede contener letras minusculas, numeros y guiones'
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
        html: 'Estamos creando tu subdominio...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('/customer/request-free-subdomain', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let successHtml = `Tu sitio web esta listo en:<br><strong><a href="https://${data.domain}" target="_blank">${data.domain}</a></strong>`;

            // Mostrar credenciales si fueron generadas automaticamente
            if (data.admin_credentials && !customAdminEnabled) {
                successHtml += `<br><br><div class="text-start mt-3 p-3 bg-light rounded">
                    <strong>Credenciales de Admin:</strong><br>
                    <small>Email: <code>${data.admin_credentials.email}</code></small><br>
                    <small>Password: <code>${data.admin_credentials.password}</code></small><br>
                    <small class="text-muted">Guarda estas credenciales!</small>
                </div>`;
            }

            Swal.fire({
                icon: 'success',
                title: 'Subdominio Creado!',
                html: successHtml,
                confirmButtonColor: '#28a745',
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
