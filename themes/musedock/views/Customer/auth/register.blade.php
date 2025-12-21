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
      <div class="col-lg-10 col-xl-9">
        <!-- Titulo -->
        <div class="text-center mb-4">
          <h1 class="h3 mb-2" style="color:#243141;">{{ __('Crea tu sitio web') }}</h1>
          <p class="text-muted mb-0">{{ __('Elige como quieres empezar') }}</p>
        </div>

        <div class="card shadow-lg border-0">
          <div class="card-body p-4 p-md-5">
            <form id="registerForm" method="POST" action="/register">
              <input type="hidden" name="_csrf_token" value="{{ $csrf_token ?? csrf_token() }}">
              <input type="hidden" name="language" value="{{ detectLanguage() }}">
              <input type="hidden" name="domain_type" id="domain_type" value="subdomain">

              <!-- PASO 1: Seleccionar tipo de dominio -->
              <div class="mb-4">
                <h5 class="fw-bold mb-3" style="color:#243141;">
                  <span class="badge rounded-circle me-2" style="background: var(--primary-color);">1</span>
                  {{ __('Elige tu tipo de dominio') }}
                </h5>

                <div class="row g-3">
                  <!-- Opcion 1: Subdominio FREE -->
                  <div class="col-md-6 col-lg-3">
                    <div class="domain-option selected" data-type="subdomain">
                      <div class="option-icon">
                        <i class="bi bi-gift-fill"></i>
                      </div>
                      <div class="option-title">{{ __('Subdominio FREE') }}</div>
                      <div class="option-desc">tuempresa.{{ $baseDomain }}</div>
                      <div class="option-badge free">{{ __('GRATIS') }}</div>
                      <div class="option-check"><i class="bi bi-check-circle-fill"></i></div>
                    </div>
                  </div>

                  <!-- Opcion 2: Vincular Dominio Existente -->
                  <div class="col-md-6 col-lg-3">
                    <div class="domain-option" data-type="connect">
                      <div class="option-icon">
                        <i class="bi bi-link-45deg"></i>
                      </div>
                      <div class="option-title">{{ __('Vincular Dominio') }}</div>
                      <div class="option-desc">{{ __('Usa tu dominio existente') }}</div>
                      <div class="option-badge available">{{ __('DISPONIBLE') }}</div>
                      <div class="option-check"><i class="bi bi-check-circle-fill"></i></div>
                    </div>
                  </div>

                  <!-- Opcion 3: Transferir Dominio -->
                  <div class="col-md-6 col-lg-3">
                    <div class="domain-option disabled" data-type="transfer">
                      <div class="option-icon">
                        <i class="bi bi-arrow-left-right"></i>
                      </div>
                      <div class="option-title">{{ __('Transferir Dominio') }}</div>
                      <div class="option-desc">{{ __('Traslada tu dominio aqui') }}</div>
                      <div class="option-badge coming">{{ __('PROXIMAMENTE') }}</div>
                      <div class="option-check"><i class="bi bi-check-circle-fill"></i></div>
                    </div>
                  </div>

                  <!-- Opcion 4: Registrar Nuevo -->
                  <div class="col-md-6 col-lg-3">
                    <div class="domain-option disabled" data-type="register">
                      <div class="option-icon">
                        <i class="bi bi-cart-plus"></i>
                      </div>
                      <div class="option-title">{{ __('Registrar Nuevo') }}</div>
                      <div class="option-desc">{{ __('Compra un dominio nuevo') }}</div>
                      <div class="option-badge coming">{{ __('PROXIMAMENTE') }}</div>
                      <div class="option-check"><i class="bi bi-check-circle-fill"></i></div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Linea de progreso -->
              <div class="progress-line mb-4">
                <div class="progress-step active" data-step="1">
                  <div class="step-circle">1</div>
                  <div class="step-label">{{ __('Dominio') }}</div>
                </div>
                <div class="progress-connector"></div>
                <div class="progress-step" data-step="2">
                  <div class="step-circle">2</div>
                  <div class="step-label">{{ __('Configurar') }}</div>
                </div>
                <div class="progress-connector"></div>
                <div class="progress-step" data-step="3">
                  <div class="step-circle">3</div>
                  <div class="step-label">{{ __('Cuenta') }}</div>
                </div>
              </div>

              <!-- PASO 2: Configurar dominio -->
              <div class="mb-4">
                <h5 class="fw-bold mb-3" style="color:#243141;">
                  <span class="badge rounded-circle me-2" style="background: var(--primary-color);">2</span>
                  {{ __('Configura tu dominio') }}
                </h5>

                <!-- Seccion Subdominio FREE -->
                <div class="domain-config" id="config_subdomain">
                  <div class="row align-items-center g-3">
                    <div class="col-lg-8">
                      <label for="subdomain" class="form-label fw-semibold">{{ __('Elige tu subdominio') }}</label>
                      <div class="input-group input-group-lg">
                        <input type="text" class="form-control" id="subdomain" name="subdomain"
                               placeholder="miempresa" pattern="[a-z0-9\\-]+"
                               title="{{ __('Solo letras minusculas, numeros y guiones') }}">
                        <span class="input-group-text">.{{ $baseDomain }}</span>
                      </div>
                      <div id="subdomain-indicator" class="mt-2"></div>
                    </div>
                    <div class="col-lg-4 d-flex align-items-center justify-content-center">
                      <div class="free-badge">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <span>{{ __('100% Gratis') }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Seccion Vincular Dominio Existente -->
                <div class="domain-config d-none" id="config_connect">
                  <div class="row">
                    <div class="col-md-7">
                      <label for="custom_domain" class="form-label fw-semibold">{{ __('Tu dominio') }}</label>
                      <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-globe"></i></span>
                        <input type="text" class="form-control" id="custom_domain" name="custom_domain"
                               placeholder="tudominio.com">
                      </div>
                      <div class="form-text">{{ __('Sin www - ejemplo: miempresa.com') }}</div>
                      <div id="custom-domain-indicator" class="mt-2"></div>
                    </div>
                    <div class="col-md-5">
                      <div class="info-card">
                        <div class="info-card-header">
                          <i class="bi bi-info-circle"></i> {{ __('Como funciona') }}
                        </div>
                        <ul class="info-card-list">
                          <li><i class="bi bi-check2"></i> {{ __('Tu dominio sigue en tu registrador') }}</li>
                          <li><i class="bi bi-check2"></i> {{ __('Solo cambias los nameservers') }}</li>
                          <li><i class="bi bi-check2"></i> {{ __('Hosting + SSL + Email incluidos') }}</li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="row mt-3">
                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="enable_email_routing" id="enable_email_routing" value="1">
                        <label class="form-check-label" for="enable_email_routing">
                          <i class="bi bi-envelope me-1"></i>
                          {{ __('Habilitar Email Routing') }}
                          <small class="text-muted d-block">{{ __('Recibe correos de tu dominio en tu email personal (ej: info@tudominio.com -> tu@gmail.com)') }}</small>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Seccion Transferir (placeholder) -->
                <div class="domain-config d-none" id="config_transfer">
                  <div class="placeholder-section">
                    <div class="placeholder-icon">
                      <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <h5>{{ __('Transferencia de Dominios') }}</h5>
                    <p class="text-muted">{{ __('Pronto podras transferir tu dominio a nuestra plataforma y gestionarlo todo desde un solo lugar.') }}</p>
                    <div class="placeholder-features">
                      <span><i class="bi bi-check"></i> {{ __('Renovacion automatica') }}</span>
                      <span><i class="bi bi-check"></i> {{ __('Gestion DNS completa') }}</span>
                      <span><i class="bi bi-check"></i> {{ __('Proteccion WHOIS') }}</span>
                    </div>
                  </div>
                </div>

                <!-- Seccion Registrar Nuevo (placeholder) -->
                <div class="domain-config d-none" id="config_register">
                  <div class="placeholder-section">
                    <div class="placeholder-icon">
                      <i class="bi bi-cart-plus"></i>
                    </div>
                    <h5>{{ __('Registro de Dominios') }}</h5>
                    <p class="text-muted">{{ __('Pronto podras buscar y registrar tu dominio ideal directamente desde aqui.') }}</p>
                    <div class="placeholder-features">
                      <span><i class="bi bi-check"></i> {{ __('Precios competitivos') }}</span>
                      <span><i class="bi bi-check"></i> {{ __('Multiples extensiones') }}</span>
                      <span><i class="bi bi-check"></i> {{ __('Configuracion automatica') }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- PASO 3: Datos de cuenta -->
              <div class="mb-0" id="account_section">
                <h5 class="fw-bold mb-3" style="color:#243141;">
                  <span class="badge rounded-circle me-2" style="background: var(--primary-color);">3</span>
                  {{ __('Crea tu cuenta') }}
                </h5>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="name" class="form-label fw-semibold">{{ __('Nombre completo') }}</label>
                    <input type="text" class="form-control form-control-lg" id="name" name="name" required placeholder="{{ __('Tu nombre') }}">
                  </div>
                  <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">{{ __('Email') }}</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" required placeholder="tu@email.com">
                  </div>
                  <div class="col-md-6">
                    <label for="password" class="form-label fw-semibold">{{ __('Contrasena') }}</label>
                    <div class="input-group input-group-lg">
                      <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="{{ __('Minimo 8 caracteres') }}">
                      <button class="btn btn-outline-secondary toggle-password" type="button">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label for="password_confirm" class="form-label fw-semibold">{{ __('Confirmar contrasena') }}</label>
                    <div class="input-group input-group-lg">
                      <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="{{ __('Repite tu contrasena') }}">
                      <button class="btn btn-outline-secondary toggle-password-confirm" type="button">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Seccion fuera del card: Terminos, Boton y Login -->
        <div class="register-footer mt-4">
          <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="terms" name="accept_terms" value="1" form="registerForm" required>
              <label class="form-check-label" for="terms" style="color:#243141;">
                {{ __('Acepto los') }}
                <a href="/p/terms" target="_blank" class="fw-semibold" style="color: var(--primary-color);">{{ __('terminos') }}</a>
                {{ __('y') }}
                <a href="/p/privacy" target="_blank" class="fw-semibold" style="color: var(--primary-color);">{{ __('privacidad') }}</a>
              </label>
            </div>
            <button type="submit" id="submitBtn" form="registerForm" class="btn btn-lg px-5 submit-btn" disabled>
              <i class="bi bi-rocket-takeoff me-2"></i>{{ __('Crear mi sitio') }}
            </button>
          </div>

          <div class="text-center mt-4">
            <span style="color:#6c757d;">{{ __('Ya tienes cuenta?') }}</span>
            <a href="/customer/login" class="fw-semibold ms-1" style="color: var(--primary-color);">{{ __('Inicia sesion') }}</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* Variables de color - Azul Cielo */
:root {
  --primary-color: #17a2b8;
  --primary-light: #5bc0de;
  --primary-gradient: linear-gradient(135deg, #17a2b8 0%, #5bc0de 100%);
  --primary-bg-light: #e8f7f9;
  --primary-bg-selected: linear-gradient(135deg, #e8f7f9 0%, #d4f1f4 100%);
  --primary-shadow: rgba(23, 162, 184, 0.25);
}

/* Footer de registro */
.register-footer {
  padding: 0 10px;
}
.submit-btn {
  background: var(--primary-gradient);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px var(--primary-shadow);
}
.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px var(--primary-shadow);
  color: white;
}
.submit-btn:disabled {
  background: #adb5bd;
  box-shadow: none;
  color: white;
}

/* Badge 100% Gratis */
.free-badge {
  background: var(--primary-gradient);
  color: white;
  padding: 12px 24px;
  border-radius: 50px;
  font-weight: 600;
  font-size: 1rem;
  display: inline-flex;
  align-items: center;
  box-shadow: 0 4px 15px var(--primary-shadow);
}
.free-badge i {
  font-size: 1.2rem;
}

/* Opciones de dominio */
.domain-option {
  background: #f8f9fa;
  border: 2px solid #e9ecef;
  border-radius: 12px;
  padding: 20px 15px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  height: 100%;
}
.domain-option:hover:not(.disabled) {
  border-color: var(--primary-color);
  background: var(--primary-bg-light);
}
.domain-option.selected {
  border-color: var(--primary-color);
  background: var(--primary-bg-selected);
  box-shadow: 0 4px 15px var(--primary-shadow);
}
.domain-option.disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.domain-option .option-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  background: var(--primary-gradient);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin: 0 auto 12px;
}
.domain-option.disabled .option-icon {
  background: #adb5bd;
}
.domain-option .option-title {
  font-weight: 600;
  color: #243141;
  font-size: 0.95rem;
  margin-bottom: 4px;
}
.domain-option .option-desc {
  font-size: 0.8rem;
  color: #6c757d;
  margin-bottom: 10px;
}
.domain-option .option-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
}
.option-badge.free {
  background: var(--primary-bg-light);
  color: var(--primary-color);
  border: 1px solid var(--primary-color);
}
.option-badge.available {
  background: var(--primary-bg-light);
  color: var(--primary-color);
  border: 1px solid var(--primary-color);
}
.option-badge.coming {
  background: #e2e3e5;
  color: #383d41;
}
.domain-option .option-check {
  position: absolute;
  top: 10px;
  right: 10px;
  color: var(--primary-color);
  font-size: 1.2rem;
  opacity: 0;
  transition: opacity 0.2s;
}
.domain-option.selected .option-check {
  opacity: 1;
}

/* Linea de progreso */
.progress-line {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px 0;
}
.progress-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}
.step-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #e9ecef;
  color: #6c757d;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.9rem;
  transition: all 0.3s;
}
.progress-step.active .step-circle,
.progress-step.completed .step-circle {
  background: var(--primary-gradient);
  color: white;
}
.step-label {
  font-size: 0.75rem;
  color: #6c757d;
  margin-top: 6px;
  font-weight: 500;
}
.progress-step.active .step-label {
  color: var(--primary-color);
  font-weight: 600;
}
.progress-connector {
  width: 80px;
  height: 3px;
  background: #e9ecef;
  margin: 0 10px;
  margin-bottom: 20px;
  border-radius: 2px;
}

/* Info card */
.info-card {
  background: #f0f7ff;
  border: 1px solid #b8daff;
  border-radius: 10px;
  padding: 15px;
  height: 100%;
}
.info-card-header {
  font-weight: 600;
  color: #004085;
  margin-bottom: 10px;
  font-size: 0.9rem;
}
.info-card-list {
  list-style: none;
  padding: 0;
  margin: 0;
  font-size: 0.85rem;
}
.info-card-list li {
  color: #004085;
  margin-bottom: 5px;
}
.info-card-list li i {
  color: #28a745;
  margin-right: 5px;
}

/* Placeholder sections */
.placeholder-section {
  text-align: center;
  padding: 40px 20px;
  background: #f8f9fa;
  border-radius: 12px;
  border: 2px dashed #dee2e6;
}
.placeholder-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #e9ecef;
  color: #6c757d;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.5rem;
  margin: 0 auto 20px;
}
.placeholder-section h5 {
  color: #495057;
  margin-bottom: 10px;
}
.placeholder-features {
  display: flex;
  justify-content: center;
  gap: 20px;
  flex-wrap: wrap;
  margin-top: 15px;
}
.placeholder-features span {
  font-size: 0.85rem;
  color: #6c757d;
}
.placeholder-features i {
  color: #28a745;
}

/* Indicadores */
#subdomain-indicator .available,
#custom-domain-indicator .available { color: #28a745; font-weight: bold; }
#subdomain-indicator .not-available,
#custom-domain-indicator .not-available { color: #dc3545; font-weight: bold; }
#subdomain-indicator .checking,
#custom-domain-indicator .checking { color: #6c757d; }

/* Card principal */
.card.shadow-lg {
  border-radius: 16px;
  overflow: hidden;
}

/* Inputs mejorados */
.form-control:focus,
.form-check-input:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 0.2rem var(--primary-shadow);
}
.form-check-input:checked {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}
.input-group-text {
  background: #f8f9fa;
  border-color: #dee2e6;
  color: #495057;
  font-weight: 500;
}

/* Responsive */
@media (max-width: 991px) {
  .free-badge {
    margin-top: 10px;
  }
}
@media (max-width: 768px) {
  .progress-connector {
    width: 40px;
  }
  .placeholder-features {
    flex-direction: column;
    gap: 8px;
  }
  .register-footer .d-flex {
    flex-direction: column !important;
    text-align: center;
  }
  .register-footer .form-check {
    margin-bottom: 15px;
  }
  .submit-btn {
    width: 100%;
  }
}
@media (max-width: 576px) {
  .domain-option {
    padding: 15px 10px;
  }
  .option-icon {
    width: 40px;
    height: 40px;
    font-size: 1.2rem !important;
  }
  .option-title {
    font-size: 0.85rem !important;
  }
  .option-desc {
    font-size: 0.75rem !important;
  }
  .progress-connector {
    width: 25px;
  }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let subdomainAvailable = false;
let customDomainValid = false;
let currentDomainType = 'subdomain';
let checkTimeout;

// Seleccionar tipo de dominio
document.querySelectorAll('.domain-option:not(.disabled)').forEach(option => {
  option.addEventListener('click', function() {
    const type = this.dataset.type;

    // Actualizar seleccion visual
    document.querySelectorAll('.domain-option').forEach(o => o.classList.remove('selected'));
    this.classList.add('selected');

    // Actualizar tipo
    currentDomainType = type;
    document.getElementById('domain_type').value = type === 'connect' ? 'custom' : type;

    // Mostrar/ocultar secciones de configuracion
    document.querySelectorAll('.domain-config').forEach(c => c.classList.add('d-none'));
    document.getElementById('config_' + type)?.classList.remove('d-none');

    // Mostrar/ocultar seccion de cuenta
    const accountSection = document.getElementById('account_section');
    if (type === 'transfer' || type === 'register') {
      accountSection.style.opacity = '0.5';
      accountSection.style.pointerEvents = 'none';
    } else {
      accountSection.style.opacity = '1';
      accountSection.style.pointerEvents = 'auto';
    }

    // Actualizar pasos visuales
    updateProgressSteps(type);
    updateSubmitButton();
  });
});

function updateProgressSteps(type) {
  const steps = document.querySelectorAll('.progress-step');
  if (type === 'transfer' || type === 'register') {
    steps[1].classList.remove('active');
    steps[2].classList.remove('active');
  } else {
    steps[1].classList.add('active');
    steps[2].classList.add('active');
  }
}

// Validar dominio personalizado
document.getElementById('custom_domain')?.addEventListener('input', function(e) {
  const domain = e.target.value.toLowerCase().trim();
  const indicator = document.getElementById('custom-domain-indicator');

  e.target.value = domain;
  clearTimeout(checkTimeout);

  if (domain.length < 4 || !domain.includes('.')) {
    indicator.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle me-1"></i>{{ __('Introduce un dominio valido') }}</span>';
    customDomainValid = false;
    updateSubmitButton();
    return;
  }

  if (domain.includes('musedock.com') || domain.includes('musedock.net')) {
    indicator.innerHTML = '<span class="not-available"><i class="bi bi-x-circle me-1"></i>{{ __('Usa la opcion Subdominio FREE para musedock') }}</span>';
    customDomainValid = false;
    updateSubmitButton();
    return;
  }

  indicator.innerHTML = '<span class="checking"><i class="bi bi-hourglass-split me-1"></i>{{ __('Verificando...') }}</span>';

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
        indicator.innerHTML = '<span class="available"><i class="bi bi-check-circle me-1"></i>' + (data.message || '{{ __('Dominio listo para vincular') }}') + '</span>';
        customDomainValid = true;
      } else {
        indicator.innerHTML = '<span class="not-available"><i class="bi bi-x-circle me-1"></i>' + (data.error || '{{ __('Este dominio ya esta en uso') }}') + '</span>';
        customDomainValid = false;
      }
      updateSubmitButton();
    })
    .catch(error => {
      indicator.innerHTML = '<span class="available"><i class="bi bi-check-circle me-1"></i>{{ __('Formato valido') }}</span>';
      customDomainValid = true;
      updateSubmitButton();
    });
}

// Validar subdominio
document.getElementById('subdomain')?.addEventListener('input', function(e) {
  const raw = e.target.value.toLowerCase().trim();
  e.target.value = raw.replace(/[^a-z0-9\-]/g, '');

  const subdomain = e.target.value;
  const indicator = document.getElementById('subdomain-indicator');

  clearTimeout(checkTimeout);

  if (subdomain.length < 3) {
    indicator.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle me-1"></i>{{ __('Minimo 3 caracteres') }}</span>';
    subdomainAvailable = false;
    updateSubmitButton();
    return;
  }

  indicator.innerHTML = '<span class="checking"><i class="bi bi-hourglass-split me-1"></i>{{ __('Verificando...') }}</span>';

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
        indicator.innerHTML = '<span class="available"><i class="bi bi-check-circle me-1"></i>{{ __('Disponible') }}</span>';
        subdomainAvailable = true;
      } else {
        indicator.innerHTML = '<span class="not-available"><i class="bi bi-x-circle me-1"></i>' + (data.reason || data.error || '{{ __('No disponible') }}') + '</span>';
        subdomainAvailable = false;
      }
      updateSubmitButton();
    })
    .catch(() => {
      indicator.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ __('Error al verificar') }}</span>';
      subdomainAvailable = false;
      updateSubmitButton();
    });
}

function updateSubmitButton() {
  const submitBtn = document.getElementById('submitBtn');
  const termsChecked = document.getElementById('terms')?.checked;

  // Si es placeholder, deshabilitar siempre
  if (currentDomainType === 'transfer' || currentDomainType === 'register') {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-clock me-2"></i>{{ __('Proximamente') }}';
    return;
  }

  submitBtn.innerHTML = '<i class="bi bi-rocket-takeoff me-2"></i>{{ __('Crear mi sitio') }}';

  let domainValid = false;
  if (currentDomainType === 'subdomain') {
    domainValid = subdomainAvailable;
  } else if (currentDomainType === 'connect') {
    domainValid = customDomainValid;
  }

  const nameValid = document.getElementById('name').value.trim().length >= 3;
  const emailValid = document.getElementById('email').value.includes('@');
  const passwordValid = document.getElementById('password').value.length >= 8;
  const passwordMatch = document.getElementById('password').value === document.getElementById('password_confirm').value;

  submitBtn.disabled = !(domainValid && termsChecked && nameValid && emailValid && passwordValid && passwordMatch);
}

document.getElementById('terms')?.addEventListener('change', updateSubmitButton);
document.getElementById('registerForm')?.addEventListener('input', updateSubmitButton);

// Submit
document.getElementById('registerForm')?.addEventListener('submit', function(e) {
  e.preventDefault();

  if (currentDomainType === 'transfer' || currentDomainType === 'register') {
    Swal.fire({
      icon: 'info',
      title: '{{ __('Proximamente') }}',
      text: '{{ __('Esta opcion estara disponible pronto.') }}'
    });
    return;
  }

  const formData = new FormData(this);
  const submitBtn = document.getElementById('submitBtn');

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Creando...') }}';

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
      if (data.nameservers && data.nameservers.length > 0) {
        let nsHtml = `
          <div class="text-start">
            <p class="mb-3">{{ __('Tu dominio') }} <strong class="text-primary">${data.domain}</strong> {{ __('esta configurado.') }}</p>
            <div class="alert alert-warning py-2 px-3 mb-3">
              <strong><i class="bi bi-exclamation-triangle me-1"></i> {{ __('Accion requerida:') }}</strong><br>
              <small>{{ __('Cambia los nameservers en tu registrador actual a:') }}</small>
            </div>
            <div class="bg-light p-3 rounded mb-3" style="font-family: monospace;">
        `;
        data.nameservers.forEach((ns, i) => {
          nsHtml += `<div class="mb-1"><strong>NS${i+1}:</strong> ${ns}</div>`;
        });
        nsHtml += `
            </div>
            <p class="text-muted small mb-0"><i class="bi bi-envelope me-1"></i>{{ __('Instrucciones enviadas a tu email.') }}</p>
          </div>
        `;

        Swal.fire({
          icon: 'success',
          title: '{{ __('Cuenta creada!') }}',
          html: nsHtml,
          confirmButtonColor: '#667eea',
          confirmButtonText: '<i class="bi bi-speedometer2 me-1"></i> {{ __('Ir al Dashboard') }}'
        }).then(() => {
          window.location.href = '/customer/dashboard';
        });
      } else {
        Swal.fire({
          icon: 'success',
          title: '{{ __('Cuenta creada!') }}',
          html: `<p>{{ __('Tu sitio esta listo en:') }}</p><p class="h5 text-primary">${data.domain}</p>`,
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

    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="bi bi-rocket-takeoff me-2"></i>{{ __('Crear mi sitio') }}';
  })
  .catch(() => {
    Swal.fire({
      icon: 'error',
      title: '{{ __('Error') }}',
      text: '{{ __('Error de conexion. Intenta de nuevo.') }}'
    });
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="bi bi-rocket-takeoff me-2"></i>{{ __('Crear mi sitio') }}';
  });
});

// Toggle password visibility
document.querySelector('.toggle-password')?.addEventListener('click', function() {
  const input = document.getElementById('password');
  const icon = this.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('bi-eye', 'bi-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('bi-eye-slash', 'bi-eye');
  }
});

document.querySelector('.toggle-password-confirm')?.addEventListener('click', function() {
  const input = document.getElementById('password_confirm');
  const icon = this.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('bi-eye', 'bi-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('bi-eye-slash', 'bi-eye');
  }
});

// Inicializar pasos
document.addEventListener('DOMContentLoaded', function() {
  updateProgressSteps('subdomain');
});
</script>
@endpush
