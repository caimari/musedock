@extends('layouts.app')

@section('content')
<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h1 class="h2 fw-bold text-primary">Recuperar Contraseña</h1>
                        <p class="text-muted">Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña</p>
                    </div>

                    <form id="forgotForm" method="POST" action="/customer/forgot-password">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">

                        <div class="mb-4">
                            <label for="email" class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control form-control-lg" id="email"
                                   name="email" required placeholder="tu@email.com" autofocus>
                        </div>

                        <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100">
                            Enviar enlace de recuperación
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-2">
                            <a href="/customer/login" class="text-decoration-none">← Volver al login</a>
                        </p>
                        <p class="mb-0">
                            ¿No tienes cuenta? <a href="/register" class="fw-semibold">Regístrate gratis</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('forgotForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = document.getElementById('submitBtn');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';

    fetch('/customer/forgot-password', {
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
                title: '¡Email enviado!',
                text: data.message || 'Revisa tu bandeja de entrada',
                showConfirmButton: true
            }).then(() => {
                window.location.href = '/customer/login';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'No se pudo enviar el email'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Enviar enlace de recuperación';
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
        submitBtn.innerHTML = 'Enviar enlace de recuperación';
    });
});
</script>
@endsection
