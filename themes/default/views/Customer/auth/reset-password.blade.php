<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h1 class="h2 fw-bold text-primary">Nueva Contraseña</h1>
                        <p class="text-muted">Crea una nueva contraseña para tu cuenta</p>
                        <p class="small text-muted"><?= htmlspecialchars($email) ?></p>
                    </div>

                    <form id="resetForm" method="POST" action="/customer/reset-password">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Nueva Contraseña</label>
                            <input type="password" class="form-control form-control-lg" id="password"
                                   name="password" required minlength="8"
                                   placeholder="Mínimo 8 caracteres" autofocus>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label fw-semibold">Confirmar Contraseña</label>
                            <input type="password" class="form-control form-control-lg" id="password_confirmation"
                                   name="password_confirmation" required
                                   placeholder="Repite tu contraseña">
                        </div>

                        <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100">
                            Restablecer contraseña
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0">
                            <a href="/customer/login" class="text-decoration-none">← Volver al login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('resetForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const password = document.getElementById('password').value;
    const passwordConfirmation = document.getElementById('password_confirmation').value;

    // Validación en cliente
    if (password !== passwordConfirmation) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Las contraseñas no coinciden'
        });
        return;
    }

    if (password.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'La contraseña debe tener al menos 8 caracteres'
        });
        return;
    }

    const formData = new FormData(this);
    const submitBtn = document.getElementById('submitBtn');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Actualizando...';

    fetch('/customer/reset-password', {
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
                title: '¡Contraseña actualizada!',
                text: 'Redirigiendo al login...',
                showConfirmButton: false,
                timer: 2000
            }).then(() => {
                window.location.href = data.redirect || '/customer/login';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'No se pudo actualizar la contraseña'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Restablecer contraseña';
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
        submitBtn.innerHTML = 'Restablecer contraseña';
    });
});
</script>
