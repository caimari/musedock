@extends('layouts.app')

@section('title')
{{ ($page_title ?? __('Registro')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('content')
@php
  $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');
@endphp

<div class="padding-none ziph-page_content">
  <div class="container" style="padding-top:10px;padding-bottom:10px;">
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card shadow-lg border-0">
          <div class="card-body p-5">
            <div class="text-center mb-4">
              <h1 class="h2 fw-bold mb-2" style="color:#243141;">{{ __('MuseDock') }}</h1>
              <p class="text-muted mb-0">{{ __('Crea tu sitio web gratis en segundos') }}</p>
            </div>

            <form id="registerForm" method="POST" action="/register">
              <input type="hidden" name="_csrf_token" value="{{ $csrf_token ?? csrf_token() }}">
              <input type="hidden" name="language" value="{{ detectLanguage() }}">

              <div class="mb-3">
                <label for="name" class="form-label fw-semibold">{{ __('Nombre completo') }}</label>
                <input type="text" class="form-control" id="name" name="name" required placeholder="{{ __('Tu nombre') }}">
              </div>

              <div class="mb-3">
                <label for="email" class="form-label fw-semibold">{{ __('Email') }}</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="tu@email.com">
              </div>

              <div class="mb-3">
                <label for="subdomain" class="form-label fw-semibold">{{ __('Elige tu subdominio') }}</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="subdomain" name="subdomain" required
                         placeholder="miempresa" pattern="[a-z0-9\\-]+"
                         title="{{ __('Solo letras minúsculas, números y guiones') }}">
                  <span class="input-group-text">.{{ $baseDomain }}</span>
                </div>
                <div id="subdomain-indicator" class="mt-2"></div>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label fw-semibold">{{ __('Contraseña') }}</label>
                <div class="input-group input-group-flat">
                  <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="{{ __('Mínimo 8 caracteres') }}">
                  <span class="input-group-text">
                    <a href="#" class="link-secondary toggle-password" title="{{ __('Mostrar contraseña') }}">
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
                <label for="password_confirm" class="form-label fw-semibold">{{ __('Confirmar contraseña') }}</label>
                <div class="input-group input-group-flat">
                  <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="{{ __('Repite tu contraseña') }}">
                  <span class="input-group-text">
                    <a href="#" class="link-secondary toggle-password-confirm" title="{{ __('Mostrar contraseña') }}">
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
                <summary style="cursor:pointer;color:#243141;">+ {{ __('Añadir información adicional (opcional)') }}</summary>
                <div class="mt-3">
                  <div class="mb-3">
                    <label for="company" class="form-label">{{ __('Empresa') }}</label>
                    <input type="text" class="form-control" id="company" name="company" placeholder="{{ __('Nombre de tu empresa') }}">
                  </div>
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label for="phone" class="form-label">{{ __('Teléfono') }}</label>
                      <input type="tel" class="form-control" id="phone" name="phone" placeholder="+34 600 000 000">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="country" class="form-label">{{ __('País (código de 2 letras: ES)') }}</label>
                      <input type="text" class="form-control" id="country" name="country" placeholder="ES" maxlength="2" pattern="[A-Z]{2}">
                    </div>
                  </div>
                </div>
              </details>

              <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="terms" name="accept_terms" value="1" required>
                <label class="form-check-label" for="terms" style="color:#243141;">
                  {!! __('Acepto los') !!}
                  <a href="/p/terms" target="_blank" class="text-decoration-underline fw-semibold" style="color:#243141;">{{ __('términos y condiciones') }}</a>
                  {!! __('y la') !!}
                  <a href="/p/privacy" target="_blank" class="text-decoration-underline fw-semibold" style="color:#243141;">{{ __('política de privacidad') }}</a>
                </label>
              </div>

              <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100" disabled>
                {{ __('Crear cuenta gratis') }}
              </button>
            </form>

            <p class="text-center mt-4 mb-0" style="color:#243141;">
              {{ __('¿Ya tienes cuenta?') }}
              <a href="/customer/login" style="color:#243141;">{{ __('Inicia sesión') }}</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
#subdomain-indicator .available { color: #28a745; font-weight: bold; }
#subdomain-indicator .not-available { color: #dc3545; font-weight: bold; }
#subdomain-indicator .checking { color: #6c757d; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let subdomainAvailable = false;
let checkTimeout;

function updateSubmitButton() {
  const form = document.getElementById('registerForm');
  const submitBtn = document.getElementById('submitBtn');
  const termsChecked = document.getElementById('terms')?.checked;
  const formValid = form ? form.checkValidity() : false;

  if (submitBtn) {
    submitBtn.disabled = !(subdomainAvailable && termsChecked && formValid);
  }
}

function checkSubdomainAvailability(subdomain) {
  const indicator = document.getElementById('subdomain-indicator');

  fetch('/customer/check-subdomain?subdomain=' + encodeURIComponent(subdomain))
    .then(r => r.json())
    .then(data => {
      if (data.available) {
        indicator.innerHTML = '<span class="available">✅ {{ __('Disponible') }}</span>';
        subdomainAvailable = true;
      } else {
        indicator.innerHTML = '<span class="not-available">❌ ' + (data.reason || data.error || '{{ __('No disponible') }}') + '</span>';
        subdomainAvailable = false;
      }
      updateSubmitButton();
    })
    .catch(() => {
      indicator.innerHTML = '<span class="text-danger">{{ __('Error al verificar') }}</span>';
      subdomainAvailable = false;
      updateSubmitButton();
    });
}

document.getElementById('subdomain')?.addEventListener('input', function(e) {
  const raw = e.target.value.toLowerCase().trim();
  e.target.value = raw.replace(/[^a-z0-9\-]/g, '');

  const subdomain = e.target.value;
  const indicator = document.getElementById('subdomain-indicator');

  clearTimeout(checkTimeout);

  if (subdomain.length < 3) {
    indicator.innerHTML = '<span class="text-muted">{{ __('Mínimo 3 caracteres') }}</span>';
    subdomainAvailable = false;
    updateSubmitButton();
    return;
  }

  indicator.innerHTML = '<span class="checking">⏳ {{ __('Verificando...') }}</span>';

  checkTimeout = setTimeout(() => {
    checkSubdomainAvailability(subdomain);
  }, 500);
});

document.getElementById('terms')?.addEventListener('change', updateSubmitButton);
document.getElementById('registerForm')?.addEventListener('input', updateSubmitButton);

document.getElementById('registerForm')?.addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(this);
  const submitBtn = document.getElementById('submitBtn');

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Creando cuenta...') }}';
  }

  fetch('/register', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: '{{ __('¡Cuenta creada!') }}',
        html: '<p>{{ __('Tu sitio está listo en:') }}</p><p class="h5 text-primary">' + (data.domain || '') + '</p><p class="mt-3">{{ __('Redirigiendo al dashboard...') }}</p>',
        showConfirmButton: false,
        timer: 2500
      }).then(() => window.location.href = data.redirect || '/customer/dashboard');
      return;
    }

    Swal.fire({
      icon: 'error',
      title: '{{ __('Error') }}',
      text: data.error || '{{ __('No se pudo crear la cuenta') }}'
    });

    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '{{ __('Crear cuenta gratis') }}';
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
      submitBtn.innerHTML = '{{ __('Crear cuenta gratis') }}';
    }
  });
});

document.addEventListener('DOMContentLoaded', function() {
  const togglePassword = document.querySelector('.toggle-password');
  const passwordInput = document.getElementById('password');

  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function(e) {
      e.preventDefault();
      const iconEye = this.querySelector('.icon-eye');
      const iconEyeOff = this.querySelector('.icon-eye-off');
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      iconEye?.classList.toggle('d-none');
      iconEyeOff?.classList.toggle('d-none');
      this.setAttribute('title', type === 'password' ? '{{ __('Mostrar contraseña') }}' : '{{ __('Ocultar contraseña') }}');
    });
  }

  const togglePasswordConfirm = document.querySelector('.toggle-password-confirm');
  const passwordConfirmInput = document.getElementById('password_confirm');

  if (togglePasswordConfirm && passwordConfirmInput) {
    togglePasswordConfirm.addEventListener('click', function(e) {
      e.preventDefault();
      const iconEye = this.querySelector('.icon-eye');
      const iconEyeOff = this.querySelector('.icon-eye-off');
      const type = passwordConfirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordConfirmInput.setAttribute('type', type);
      iconEye?.classList.toggle('d-none');
      iconEyeOff?.classList.toggle('d-none');
      this.setAttribute('title', type === 'password' ? '{{ __('Mostrar contraseña') }}' : '{{ __('Ocultar contraseña') }}');
    });
  }
});
</script>
@endpush
