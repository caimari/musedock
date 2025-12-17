<div class="container" style="max-width: 500px;">
    <div class="card shadow-lg border-0" style="border-radius: 15px;">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h1 class="h2 fw-bold" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Iniciar Sesión</h1>
                <p class="text-muted">Accede a tu panel de MuseDock</p>
            </div>

            <form id="loginForm" method="POST" action="/customer/login">
                <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control form-control-lg" id="email"
                           name="email" required placeholder="tu@email.com" autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Contraseña</label>
                    <input type="password" class="form-control form-control-lg" id="password"
                           name="password" required placeholder="Tu contraseña">
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label class="form-check-label" for="remember_me">
                        Recordarme (30 días)
                    </label>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-lg w-100" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none;">
                    Iniciar sesión
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="mb-2">
                    <a href="/customer/forgot-password" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
                </p>
                <p class="mb-0">
                    ¿No tienes cuenta? <a href="/register" class="fw-semibold">Regístrate gratis</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = document.getElementById('submitBtn');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Iniciando sesión...';

    fetch('/customer/login', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            console.error('HTTP Error:', response.status, response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Bienvenido!',
                text: 'Redirigiendo a tu dashboard...',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                window.location.href = '/customer/dashboard';
            });
        } else {
            if (data.error === 'csrf_token_mismatch' && data.new_csrf_token) {
                document.querySelector('input[name="_csrf_token"]').value = data.new_csrf_token;

                Swal.fire({
                    icon: 'warning',
                    title: 'Sesión expirada',
                    text: 'Tu sesión ha expirado. Por favor, intenta de nuevo.',
                    confirmButtonText: 'Entendido'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de acceso',
                    text: data.error || data.message || 'Email o contraseña incorrectos'
                });
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Iniciar sesión';
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexión. Por favor, intenta de nuevo.'
        });
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Iniciar sesión';
    });
});
</script>
