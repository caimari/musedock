<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h1 class="h2 fw-bold text-primary">MuseDock</h1>
                        <p class="text-muted">Crea tu sitio web gratis en segundos</p>
                    </div>

                    <form id="registerForm" method="POST" action="/register">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Nombre completo</label>
                            <input type="text" class="form-control" id="name" name="name" required placeholder="Tu nombre">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="tu@email.com">
                        </div>

                        <div class="mb-3">
                            <label for="subdomain" class="form-label fw-semibold">Elige tu subdominio</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="subdomain" name="subdomain" required
                                       placeholder="miempresa" pattern="[a-z0-9\-]+"
                                       title="Solo letras minúsculas, números y guiones">
                                <span class="input-group-text">.musedock.com</span>
                            </div>
                            <div id="subdomain-indicator" class="mt-2"></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Contraseña</label>
                            <div class="input-group input-group-flat">
                                <input type="password" class="form-control" id="password" name="password" required
                                       minlength="8" placeholder="Mínimo 8 caracteres">
                                <span class="input-group-text">
                                    <a href="#" class="link-secondary toggle-password" title="Mostrar contraseña" data-bs-toggle="tooltip">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-eye" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                                            <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                            <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-eye-off d-none" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                                            <path d="M10.585 10.587a2 2 0 0 0 2.829 2.828" />
                                            <path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87" />
                                            <path d="M3 3l18 18" />
                                        </svg>
                                    </a>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirm" class="form-label fw-semibold">Confirmar contraseña</label>
                            <div class="input-group input-group-flat">
                                <input type="password" class="form-control" id="password_confirm"
                                       name="password_confirm" required placeholder="Repite tu contraseña">
                                <span class="input-group-text">
                                    <a href="#" class="link-secondary toggle-password-confirm" title="Mostrar contraseña" data-bs-toggle="tooltip">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-eye" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                                            <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                            <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-eye-off d-none" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                                            <path d="M10.585 10.587a2 2 0 0 0 2.829 2.828" />
                                            <path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87" />
                                            <path d="M3 3l18 18" />
                                        </svg>
                                    </a>
                                </span>
                            </div>
                        </div>

                        <details class="mb-3">
                            <summary class="text-primary" style="cursor:pointer">+ Añadir información adicional (opcional)</summary>
                            <div class="mt-3">
                                <div class="mb-3">
                                    <label for="company" class="form-label">Empresa</label>
                                    <input type="text" class="form-control" id="company" name="company" placeholder="Nombre de tu empresa">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="+34 600 000 000">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="country" class="form-label">País (código de 2 letras: ES)</label>
                                        <input type="text" class="form-control" id="country" name="country"
                                               placeholder="ES" maxlength="2" pattern="[A-Z]{2}">
                                    </div>
                                </div>
                            </div>
                        </details>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" name="accept_terms" value="1" required>
                            <label class="form-check-label text-dark" for="terms">
                                Acepto los <a href="/p/terms" target="_blank" class="text-dark text-decoration-underline fw-semibold">términos y condiciones</a> y la <a href="/p/privacy" target="_blank" class="text-dark text-decoration-underline fw-semibold">política de privacidad</a>
                            </label>
                        </div>

                        <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100" disabled>
                            Crear cuenta gratis
                        </button>
                    </form>

                    <p class="text-center mt-4 mb-0" style="color: #212529;">
                        ¿Ya tienes cuenta? <a href="/customer/login" style="color: #212529;">Inicia sesión</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#subdomain-indicator .available { color: #28a745; font-weight: bold; }
#subdomain-indicator .not-available { color: #dc3545; font-weight: bold; }
#subdomain-indicator .checking { color: #6c757d; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let subdomainAvailable = false;
let checkTimeout;

// Validar subdominio en tiempo real
document.getElementById('subdomain').addEventListener('input', function(e) {
    const subdomain = e.target.value.toLowerCase().trim();
    const indicator = document.getElementById('subdomain-indicator');

    // Convertir a minúsculas y remover caracteres inválidos
    e.target.value = subdomain.replace(/[^a-z0-9\-]/g, '');

    clearTimeout(checkTimeout);

    if (subdomain.length < 3) {
        indicator.innerHTML = '<span class="text-muted">Mínimo 3 caracteres</span>';
        subdomainAvailable = false;
        updateSubmitButton();
        return;
    }

    indicator.innerHTML = '<span class="checking">⏳ Verificando...</span>';

    // Debounce: esperar 500ms antes de verificar
    checkTimeout = setTimeout(() => {
        checkSubdomainAvailability(subdomain);
    }, 500);
});

function checkSubdomainAvailability(subdomain) {
    const indicator = document.getElementById('subdomain-indicator');

    fetch('/customer/check-subdomain?subdomain=' + encodeURIComponent(subdomain))
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                indicator.innerHTML = '<span class="available">✅ Disponible</span>';
                subdomainAvailable = true;
            } else {
                indicator.innerHTML = '<span class="not-available">❌ ' + (data.reason || data.error || 'No disponible') + '</span>';
                subdomainAvailable = false;
            }
            updateSubmitButton();
        })
        .catch(error => {
            indicator.innerHTML = '<span class="text-danger">Error al verificar</span>';
            subdomainAvailable = false;
            updateSubmitButton();
        });
}

// Habilitar/deshabilitar botón submit
function updateSubmitButton() {
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const termsChecked = document.getElementById('terms').checked;
    const formValid = form.checkValidity();

    submitBtn.disabled = !(subdomainAvailable && termsChecked && formValid);
}

document.getElementById('terms').addEventListener('change', updateSubmitButton);
document.getElementById('registerForm').addEventListener('input', updateSubmitButton);

// Submit del formulario
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = document.getElementById('submitBtn');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creando cuenta...';

    fetch('/register', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Cuenta creada!',
                html: `
                    <p>Tu sitio está listo en:</p>
                    <p class="h5 text-primary">${data.domain}</p>
                    <p class="mt-3">Redirigiendo al dashboard...</p>
                `,
                showConfirmButton: false,
                timer: 3000
            }).then(() => {
                window.location.href = '/customer/dashboard';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'No se pudo crear la cuenta'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Crear cuenta gratis';
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexión. Por favor, intenta de nuevo.'
        });
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Crear cuenta gratis';
    });
});

// Toggle mostrar/ocultar contraseña
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.querySelector('.toggle-password');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function(e) {
            e.preventDefault();

            const iconEye = this.querySelector('.icon-eye');
            const iconEyeOff = this.querySelector('.icon-eye-off');

            // Toggle password type
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icons
            iconEye.classList.toggle('d-none');
            iconEyeOff.classList.toggle('d-none');

            // Update tooltip
            this.setAttribute('title', type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña');
        });
    }

    // Toggle password confirm visibility
    const togglePasswordConfirm = document.querySelector('.toggle-password-confirm');
    const passwordConfirmInput = document.getElementById('password_confirm');

    if (togglePasswordConfirm && passwordConfirmInput) {
        togglePasswordConfirm.addEventListener('click', function(e) {
            e.preventDefault();

            const iconEye = this.querySelector('.icon-eye');
            const iconEyeOff = this.querySelector('.icon-eye-off');

            // Toggle password type
            const type = passwordConfirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordConfirmInput.setAttribute('type', type);

            // Toggle icons
            iconEye.classList.toggle('d-none');
            iconEyeOff.classList.toggle('d-none');

            // Update tooltip
            this.setAttribute('title', type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña');
        });
    }
});
</script>
