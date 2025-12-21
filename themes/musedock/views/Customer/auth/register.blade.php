@extends('layouts.app')

@section('title')
{{ ($page_title ?? __('Registro')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('content')
@php
  $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');
@endphp

<div class="padding-none ziph-page_content">
  <div class="container" style="padding-top:40px;padding-bottom:40px;">
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <!-- Titulo fuera del card -->
        <div class="text-center mb-4">
          <h1 class="h4 mb-0" style="color:#243141;">{{ __('Crea tu sitio web gratis en segundos') }}</h1>
        </div>

        <div class="card shadow-lg border-0">
          <div class="card-body p-5">
            <form id="registerForm" method="POST" action="/register">
              <input type="hidden" name="_csrf_token" value="{{ $csrf_token ?? csrf_token() }}">
              <input type="hidden" name="language" value="{{ detectLanguage() }}">
              <input type="hidden" name="domain_type" id="domain_type" value="subdomain">

              <div class="mb-3">
                <label for="name" class="form-label fw-semibold">{{ __('Nombre completo') }}</label>
                <input type="text" class="form-control" id="name" name="name" required placeholder="{{ __('Tu nombre') }}">
              </div>

              <div class="mb-3">
                <label for="email" class="form-label fw-semibold">{{ __('Email') }}</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="tu@email.com">
              </div>

              <!-- Selector de tipo de dominio -->
              <div class="mb-3">
                <label class="form-label fw-semibold">{{ __('Tipo de dominio') }}</label>
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="domain_option" id="option_subdomain" value="subdomain" checked>
                  <label class="btn btn-outline-primary" for="option_subdomain">
                    <i class="bi bi-gift me-1"></i> {{ __('Subdominio FREE') }}
                  </label>
                  <input type="radio" class="btn-check" name="domain_option" id="option_custom" value="custom">
                  <label class="btn btn-outline-primary" for="option_custom">
                    <i class="bi bi-globe me-1"></i> {{ __('Mi Dominio Propio') }}
                  </label>
                </div>
              </div>

              <!-- Opcion 1: Subdominio FREE -->
              <div class="mb-3" id="subdomain_section">
                <label for="subdomain" class="form-label fw-semibold">{{ __('Elige tu subdominio') }}</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="subdomain" name="subdomain"
                         placeholder="miempresa" pattern="[a-z0-9\\-]+"
                         title="{{ __('Solo letras minusculas, numeros y guiones') }}">
                  <span class="input-group-text">.{{ $baseDomain }}</span>
                </div>
                <div id="subdomain-indicator" class="mt-2"></div>
              </div>

              <!-- Opcion 2: Dominio Propio -->
              <div class="mb-3 d-none" id="custom_domain_section">
                <label for="custom_domain" class="form-label fw-semibold">{{ __('Tu dominio') }}</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-globe"></i></span>
                  <input type="text" class="form-control" id="custom_domain" name="custom_domain"
                         placeholder="miempresa.com">
                </div>
                <div class="form-text">{{ __('Introduce tu dominio sin www (ejemplo: miempresa.com)') }}</div>
                <div id="custom-domain-indicator" class="mt-2"></div>

                <div class="alert alert-info mt-3 py-2 px-3" style="font-size: 0.85rem;">
                  <i class="bi bi-info-circle me-1"></i>
                  <strong>{{ __('Proximo paso:') }}</strong> {{ __('Despues del registro, te daremos los nameservers de Cloudflare para que configures tu dominio.') }}
                </div>

                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" name="enable_email_routing" id="enable_email_routing" value="1">
                  <label class="form-check-label" for="enable_email_routing">
                    <small>{{ __('Habilitar Email Routing (recibir correos del dominio en mi email)') }}</small>
                  </label>
                </div>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label fw-semibold">{{ __('Contrasena') }}</label>
                <div class="input-group input-group-flat">
                  <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="{{ __('Minimo 8 caracteres') }}">
                  <span class="input-group-text">
                    <a href="#" class="link-secondary toggle-password" title="{{ __('Mostrar contrasena') }}">
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
                <label for="password_confirm" class="form-label fw-semibold">{{ __('Confirmar contrasena') }}</label>
                <div class="input-group input-group-flat">
                  <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="{{ __('Repite tu contrasena') }}">
                  <span class="input-group-text">
                    <a href="#" class="link-secondary toggle-password-confirm" title="{{ __('Mostrar contrasena') }}">
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

              <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="terms" name="accept_terms" value="1" required>
                <label class="form-check-label" for="terms" style="color:#243141;">
                  {!! __('Acepto los') !!}
                  <a href="/p/terms" target="_blank" class="text-decoration-underline fw-semibold" style="color:#243141;">{{ __('terminos y condiciones') }}</a>
                  {!! __('y la') !!}
                  <a href="/p/privacy" target="_blank" class="text-decoration-underline fw-semibold" style="color:#243141;">{{ __('politica de privacidad') }}</a>
                </label>
              </div>

              <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100" disabled>
                {{ __('Crear cuenta gratis') }}
              </button>
            </form>

            <p class="text-center mt-4 mb-0" style="color:#243141;">
              {{ __('Ya tienes cuenta?') }}
              <a href="/customer/login" style="color:#243141;">{{ __('Inicia sesion') }}</a>
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
#subdomain-indicator .available,
#custom-domain-indicator .available { color: #28a745; font-weight: bold; }
#subdomain-indicator .not-available,
#custom-domain-indicator .not-available { color: #dc3545; font-weight: bold; }
#subdomain-indicator .checking,
#custom-domain-indicator .checking { color: #6c757d; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let subdomainAvailable = false;
let customDomainValid = false;
let currentDomainType = 'subdomain';
let checkTimeout;

// Toggle entre subdomain y custom domain
document.querySelectorAll('input[name="domain_option"]').forEach(radio => {
  radio.addEventListener('change', function() {
    currentDomainType = this.value;
    document.getElementById('domain_type').value = this.value;

    if (this.value === 'subdomain') {
      document.getElementById('subdomain_section').classList.remove('d-none');
      document.getElementById('custom_domain_section').classList.add('d-none');
      document.getElementById('subdomain').required = true;
      document.getElementById('custom_domain').required = false;
    } else {
      document.getElementById('subdomain_section').classList.add('d-none');
      document.getElementById('custom_domain_section').classList.remove('d-none');
      document.getElementById('subdomain').required = false;
      document.getElementById('custom_domain').required = true;
    }
    updateSubmitButton();
  });
});

// Validar dominio personalizado
document.getElementById('custom_domain')?.addEventListener('input', function(e) {
  const domain = e.target.value.toLowerCase().trim();
  const indicator = document.getElementById('custom-domain-indicator');

  e.target.value = domain;
  clearTimeout(checkTimeout);

  if (domain.length < 4 || !domain.includes('.')) {
    indicator.innerHTML = '<span class="text-muted">{{ __('Introduce un dominio valido (ej: miempresa.com)') }}</span>';
    customDomainValid = false;
    updateSubmitButton();
    return;
  }

  // No permitir subdominios de musedock.com
  if (domain.includes('musedock.com')) {
    indicator.innerHTML = '<span class="not-available">{{ __('Para subdominios de musedock.com usa la opcion Subdominio FREE') }}</span>';
    customDomainValid = false;
    updateSubmitButton();
    return;
  }

  indicator.innerHTML = '<span class="checking">{{ __('Verificando disponibilidad...') }}</span>';

  checkTimeout = setTimeout(() => {
    checkCustomDomainAvailability(domain);
  }, 500);
});

function checkCustomDomainAvailability(domain) {
  const indicator = document.getElementById('custom-domain-indicator');

  fetch('/customer/check-custom-domain?domain=' + encodeURIComponent(domain))
    .then(response => response.json())
    .then(data => {
      if (data.available) {
        indicator.innerHTML = '<span class="available">' + (data.message || '{{ __('Dominio disponible para registro') }}') + '</span>';
        customDomainValid = true;
      } else {
        indicator.innerHTML = '<span class="not-available">' + (data.error || '{{ __('Este dominio ya esta registrado en el sistema') }}') + '</span>';
        customDomainValid = false;
      }
      updateSubmitButton();
    })
    .catch(error => {
      indicator.innerHTML = '<span class="available">{{ __('Formato de dominio valido') }}</span>';
      customDomainValid = true;
      updateSubmitButton();
    });
}

// Validar subdominio en tiempo real
document.getElementById('subdomain')?.addEventListener('input', function(e) {
  const raw = e.target.value.toLowerCase().trim();
  e.target.value = raw.replace(/[^a-z0-9\-]/g, '');

  const subdomain = e.target.value;
  const indicator = document.getElementById('subdomain-indicator');

  clearTimeout(checkTimeout);

  if (subdomain.length < 3) {
    indicator.innerHTML = '<span class="text-muted">{{ __('Minimo 3 caracteres') }}</span>';
    subdomainAvailable = false;
    updateSubmitButton();
    return;
  }

  indicator.innerHTML = '<span class="checking">{{ __('Verificando...') }}</span>';

  checkTimeout = setTimeout(() => {
    checkSubdomainAvailability(subdomain);
  }, 500);
});

function checkSubdomainAvailability(subdomain) {
  const indicator = document.getElementById('subdomain-indicator');

  fetch('/customer/check-subdomain?subdomain=' + encodeURIComponent(subdomain))
    .then(r => r.json())
    .then(data => {
      if (data.available) {
        indicator.innerHTML = '<span class="available">{{ __('Disponible') }}</span>';
        subdomainAvailable = true;
      } else {
        indicator.innerHTML = '<span class="not-available">' + (data.reason || data.error || '{{ __('No disponible') }}') + '</span>';
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

function updateSubmitButton() {
  const form = document.getElementById('registerForm');
  const submitBtn = document.getElementById('submitBtn');
  const termsChecked = document.getElementById('terms')?.checked;

  let domainValid = false;
  if (currentDomainType === 'subdomain') {
    domainValid = subdomainAvailable;
  } else {
    domainValid = customDomainValid;
  }

  // Verificar otros campos del formulario
  const nameValid = document.getElementById('name').value.trim().length >= 3;
  const emailValid = document.getElementById('email').value.includes('@');
  const passwordValid = document.getElementById('password').value.length >= 8;
  const passwordMatch = document.getElementById('password').value === document.getElementById('password_confirm').value;

  if (submitBtn) {
    submitBtn.disabled = !(domainValid && termsChecked && nameValid && emailValid && passwordValid && passwordMatch);
  }
}

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
      // Si es dominio custom, mostrar nameservers
      if (data.nameservers && data.nameservers.length > 0) {
        let nsHtml = `
          <p class="mb-3">{{ __('Tu dominio') }} <strong class="text-primary">${data.domain}</strong> {{ __('ha sido registrado.') }}</p>
          <div class="alert alert-warning text-start py-2 px-3">
            <strong><i class="bi bi-exclamation-triangle me-1"></i> {{ __('Importante:') }}</strong><br>
            {{ __('Cambia los nameservers de tu dominio a:') }}
          </div>
          <div class="bg-light p-3 rounded text-start mb-3">
        `;
        data.nameservers.forEach((ns, i) => {
          nsHtml += `<code class="d-block mb-1">NS${i+1}: ${ns}</code>`;
        });
        nsHtml += `
          </div>
          <p class="text-muted small">{{ __('Te hemos enviado un email con instrucciones detalladas.') }}</p>
        `;

        Swal.fire({
          icon: 'success',
          title: '{{ __('Cuenta creada!') }}',
          html: nsHtml,
          confirmButtonColor: '#667eea',
          confirmButtonText: '{{ __('Ir al Dashboard') }}'
        }).then(() => {
          window.location.href = '/customer/dashboard';
        });
      } else {
        // Subdominio FREE - flujo normal
        Swal.fire({
          icon: 'success',
          title: '{{ __('Cuenta creada!') }}',
          html: '<p>{{ __('Tu sitio esta listo en:') }}</p><p class="h5 text-primary">' + (data.domain || '') + '</p><p class="mt-3">{{ __('Redirigiendo al dashboard...') }}</p>',
          showConfirmButton: false,
          timer: 2500
        }).then(() => window.location.href = data.redirect || '/customer/dashboard');
      }
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
      text: '{{ __('Error de conexion. Por favor, intenta de nuevo.') }}'
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
      this.setAttribute('title', type === 'password' ? '{{ __('Mostrar contrasena') }}' : '{{ __('Ocultar contrasena') }}');
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
      this.setAttribute('title', type === 'password' ? '{{ __('Mostrar contrasena') }}' : '{{ __('Ocultar contrasena') }}');
    });
  }
});
</script>
@endpush
