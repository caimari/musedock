@extends('layouts.app')

@section('title')
{{ ($page_title ?? __('Recuperar contraseña')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('content')
<div class="padding-none ziph-page_content">
  <div class="container" style="padding-top:40px;padding-bottom:40px;">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <!-- Título fuera del card -->
        <div class="text-center mb-4">
          <h1 class="h4 mb-0" style="color:#243141;">{{ __('Recuperar contraseña') }}</h1>
        </div>

        <div class="card shadow-lg border-0">
          <div class="card-body p-5">

            <form id="forgotForm" method="POST" action="/customer/forgot-password">
              <input type="hidden" name="_csrf_token" value="{{ $csrf_token ?? csrf_token() }}">

              <div class="mb-4">
                <label for="email" class="form-label fw-semibold">{{ __('Email') }}</label>
                <input type="email" class="form-control form-control-lg" id="email" name="email" required placeholder="tu@email.com" autofocus>
              </div>

              <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100">
                {{ __('Enviar enlace de recuperación') }}
              </button>
            </form>

            <div class="text-center mt-4">
              <p class="mb-2">
                <a href="/customer/login" class="text-decoration-none" style="color:#243141;">← {{ __('Volver al login') }}</a>
              </p>
              <p class="mb-0" style="color:#243141;">
                {{ __('¿No tienes cuenta?') }}
                <a href="/register" class="fw-semibold" style="color:#243141;">{{ __('Regístrate gratis') }}</a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('forgotForm')?.addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(this);
  const submitBtn = document.getElementById('submitBtn');

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Enviando...') }}';
  }

  fetch('/customer/forgot-password', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: formData
  })
  .then(async (response) => {
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      return { success: false, error: data.error || data.message || 'HTTP ' + response.status };
    }
    return data;
  })
  .then((data) => {
    if (data && data.success) {
      Swal.fire({
        icon: 'success',
        title: '{{ __('¡Email enviado!') }}',
        text: data.message || '{{ __('Revisa tu bandeja de entrada') }}',
        showConfirmButton: true
      }).then(() => {
        window.location.href = '/customer/login';
      });
      return;
    }

    Swal.fire({
      icon: 'error',
      title: '{{ __('Error') }}',
      text: (data && (data.error || data.message)) ? (data.error || data.message) : '{{ __('No se pudo enviar el email') }}'
    });

    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '{{ __('Enviar enlace de recuperación') }}';
    }
  })
  .catch(() => {
    Swal.fire({
      icon: 'error',
      title: '{{ __('Error') }}',
      text: '{{ __('Error de conexión. Por favor, intenta de nuevo.') }}'
    });
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '{{ __('Enviar enlace de recuperación') }}';
    }
  });
});
</script>
@endpush
