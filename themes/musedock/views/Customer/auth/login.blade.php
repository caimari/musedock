@extends('layouts.app')

@section('title')
{{ ($page_title ?? __('Iniciar sesión')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('content')
<div class="padding-none ziph-page_content">
  <div class="container" style="padding-top:40px;padding-bottom:40px;">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <!-- Título fuera del card -->
        <div class="text-center mb-4">
          <h1 class="h4 mb-0" style="color:#243141;">{{ __('Accede a tu panel') }}</h1>
        </div>

        <div class="card shadow-lg border-0">
          <div class="card-body p-5">

            @if(!empty($flash_error ?? null))
            <div class="alert alert-danger" role="alert">
              {{ $flash_error }}
            </div>
            @endif

            <form id="loginForm" method="POST" action="/customer/login">
              <input type="hidden" name="_csrf_token" value="{{ $csrf_token ?? csrf_token() }}">
              @if(!empty($redirect_after_login ?? null))
              <input type="hidden" name="redirect" value="{{ $redirect_after_login }}">
              @endif

              <div class="mb-3">
                <label for="email" class="form-label fw-semibold">{{ __('Email') }}</label>
                <input type="email" class="form-control form-control-lg" id="email" name="email" required placeholder="tu@email.com" autofocus>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label fw-semibold">{{ __('Contraseña') }}</label>
                <input type="password" class="form-control form-control-lg" id="password" name="password" required placeholder="{{ __('Tu contraseña') }}">
              </div>

              <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                <label class="form-check-label" for="remember">
                  {{ __('Recordarme (30 días)') }}
                </label>
              </div>

              <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100">
                {{ __('Iniciar sesión') }}
              </button>
            </form>

            <div class="text-center mt-4">
              <p class="mb-2">
                <a href="/customer/forgot-password" class="text-decoration-none" style="color:#243141;">
                  {{ __('¿Olvidaste tu contraseña?') }}
                </a>
              </p>
              <p class="mb-0" style="color:#243141;">
                {{ __('¿No tienes cuenta?') }}
                <a href="/register" class="fw-semibold" style="color:#243141;">
                  {{ __('Regístrate gratis') }}
                </a>
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
document.getElementById('loginForm')?.addEventListener('submit', function(e) {
  e.preventDefault();

  const form = this;
  const formData = new FormData(form);
  const submitBtn = document.getElementById('submitBtn');

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Iniciando sesión...') }}';
  }

  fetch('/customer/login', {
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
        title: '{{ __('¡Bienvenido!') }}',
        text: '{{ __('Redirigiendo...') }}',
        showConfirmButton: false,
        timer: 1200
      }).then(() => {
        window.location.href = data.redirect || '/customer/dashboard';
      });
      return;
    }

    Swal.fire({
      icon: 'error',
      title: '{{ __('Error de acceso') }}',
      text: (data && (data.error || data.message)) ? (data.error || data.message) : '{{ __('Email o contraseña incorrectos') }}'
    });

    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '{{ __('Iniciar sesión') }}';
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
      submitBtn.innerHTML = '{{ __('Iniciar sesión') }}';
    }
  });
});
</script>
@endpush
