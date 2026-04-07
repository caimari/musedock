@extends('layouts.app')

@section('title')
{{ ($page_title ?? __('Registro')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('content')
@php
  $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');
@endphp

<div class="padding-none ziph-page_content">
  <div class="container" style="padding-top:28px; padding-bottom:40px;">
    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">

        {{-- Title --}}
        <div class="text-center" style="margin-bottom:20px;">
          <h1 style="color:#243141; font-size:1.4rem; font-weight:700; margin:0 0 4px;">{{ __('Crea tu sitio web') }}</h1>
          <p style="color:#8a94a6; font-size:0.9rem; margin:0;">{{ __('Elige como quieres empezar') }}</p>
        </div>

        {{-- Main card --}}
        <div style="background:#fff; border-radius:12px; box-shadow:0 2px 16px rgba(0,0,0,0.08); border:1px solid #edf0f5; padding:24px;">

          <form id="registerForm" method="POST" action="/register">
            <input type="hidden" name="_csrf_token" value="{{ $csrf_token ?? csrf_token() }}">
            <input type="hidden" name="language" value="{{ detectLanguage() }}">
            <input type="hidden" name="domain_type" id="domain_type" value="subdomain">

            {{-- STEP 1: Domain type --}}
            <div style="margin-bottom:20px;">
              <div style="font-size:0.8rem; font-weight:700; color:#243141; margin-bottom:10px; text-transform:uppercase; letter-spacing:0.5px;">
                <span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#4e73df; color:#fff; font-size:0.65rem; margin-right:6px;">1</span>
                {{ __('Tipo de dominio') }}
              </div>

              <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:8px;">
                @php
                  $domainOptions = [
                    ['type' => 'subdomain', 'icon' => 'gift-fill', 'title' => 'Subdominio', 'badge' => 'GRATIS', 'badgeColor' => '#28a745', 'enabled' => true],
                    ['type' => 'register', 'icon' => 'cart-plus', 'title' => 'Registrar', 'badge' => 'PRONTO', 'badgeColor' => '#6c757d', 'enabled' => false],
                    ['type' => 'transfer', 'icon' => 'arrow-left-right', 'title' => 'Transferir', 'badge' => 'PRONTO', 'badgeColor' => '#6c757d', 'enabled' => false],
                    ['type' => 'connect', 'icon' => 'link-45deg', 'title' => 'Vincular', 'badge' => 'OK', 'badgeColor' => '#4e73df', 'enabled' => true],
                  ];
                @endphp

                @foreach($domainOptions as $opt)
                <div class="reg-domain-opt {{ !$opt['enabled'] ? 'disabled' : '' }} {{ $opt['type'] === 'subdomain' ? 'selected' : '' }}"
                     data-type="{{ $opt['type'] }}"
                     style="
                       padding:12px 8px; border-radius:8px; text-align:center; cursor:{{ $opt['enabled'] ? 'pointer' : 'not-allowed' }};
                       border:2px solid {{ $opt['type'] === 'subdomain' ? '#4e73df' : '#e5e7eb' }};
                       background:{{ $opt['type'] === 'subdomain' ? '#f0f4ff' : ($opt['enabled'] ? '#fff' : '#f9fafb') }};
                       opacity:{{ $opt['enabled'] ? '1' : '0.5' }};
                       transition:all 0.15s;
                     ">
                  <i class="bi bi-{{ $opt['icon'] }}" style="font-size:1.2rem; color:{{ $opt['enabled'] ? '#4e73df' : '#adb5bd' }}; display:block; margin-bottom:4px;"></i>
                  <div style="font-size:0.72rem; font-weight:600; color:#243141;">{{ __($opt['title']) }}</div>
                  <span style="font-size:0.6rem; font-weight:700; color:{{ $opt['badgeColor'] }}; letter-spacing:0.3px;">{{ $opt['badge'] }}</span>
                </div>
                @endforeach
              </div>
            </div>

            {{-- STEP 2: Domain config --}}
            <div style="margin-bottom:20px;">
              <div style="font-size:0.8rem; font-weight:700; color:#243141; margin-bottom:10px; text-transform:uppercase; letter-spacing:0.5px;">
                <span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#4e73df; color:#fff; font-size:0.65rem; margin-right:6px;">2</span>
                {{ __('Tu dominio') }}
              </div>

              {{-- Subdomain --}}
              <div class="domain-config" id="config_subdomain">
                <div style="display:flex; gap:0; align-items:stretch;">
                  <input type="text" id="subdomain" name="subdomain" placeholder="miempresa" pattern="[a-z0-9\-]+"
                         style="flex:1; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px 0 0 8px; font-size:0.9rem; outline:none; border-right:none;"
                         onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
                  <span style="padding:10px 14px; background:#f3f4f6; border:1px solid #d1d5db; border-radius:0 8px 8px 0; font-size:0.85rem; color:#6b7280; white-space:nowrap; display:flex; align-items:center;">.{{ $baseDomain }}</span>
                </div>
                <div id="subdomain-indicator" style="margin-top:6px; font-size:0.8rem;"></div>
              </div>

              {{-- Custom domain --}}
              <div class="domain-config d-none" id="config_connect">
                <div style="display:flex; gap:0; align-items:stretch;">
                  <span style="padding:10px 12px; background:#f3f4f6; border:1px solid #d1d5db; border-radius:8px 0 0 8px; color:#6b7280; display:flex; align-items:center;">
                    <i class="bi bi-globe"></i>
                  </span>
                  <input type="text" id="custom_domain" name="custom_domain" placeholder="tudominio.com"
                         style="flex:1; padding:10px 12px; border:1px solid #d1d5db; border-radius:0 8px 8px 0; font-size:0.9rem; outline:none; border-left:none;"
                         onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
                </div>
                <div id="custom-domain-indicator" style="margin-top:6px; font-size:0.8rem;"></div>
                <div style="margin-top:8px;">
                  <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer; font-size:0.8rem; color:#6b7280;">
                    <input type="checkbox" name="enable_email_routing" id="enable_email_routing" value="1" style="margin-top:2px; accent-color:#4e73df;">
                    <span><i class="bi bi-envelope" style="margin-right:2px;"></i> {{ __('Habilitar Email Routing') }} <span style="color:#9ca3af;">— {{ __('info@tudominio.com → tu@gmail.com') }}</span></span>
                  </label>
                </div>
              </div>

              {{-- Placeholders --}}
              <div class="domain-config d-none" id="config_transfer">
                <div style="text-align:center; padding:20px; background:#f9fafb; border-radius:8px; border:1px dashed #d1d5db;">
                  <i class="bi bi-arrow-left-right" style="font-size:1.5rem; color:#adb5bd;"></i>
                  <p style="font-size:0.85rem; color:#6b7280; margin:8px 0 0;">{{ __('Transferencia de dominios disponible próximamente.') }}</p>
                </div>
              </div>
              <div class="domain-config d-none" id="config_register">
                <div style="text-align:center; padding:20px; background:#f9fafb; border-radius:8px; border:1px dashed #d1d5db;">
                  <i class="bi bi-cart-plus" style="font-size:1.5rem; color:#adb5bd;"></i>
                  <p style="font-size:0.85rem; color:#6b7280; margin:8px 0 0;">{{ __('Registro de dominios disponible próximamente.') }}</p>
                </div>
              </div>
            </div>

            {{-- STEP 3: Account --}}
            <div id="account_section">
              <div style="font-size:0.8rem; font-weight:700; color:#243141; margin-bottom:10px; text-transform:uppercase; letter-spacing:0.5px;">
                <span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#4e73df; color:#fff; font-size:0.65rem; margin-right:6px;">3</span>
                {{ __('Tu cuenta') }}
              </div>

              <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                  <label style="display:block; font-size:0.8rem; font-weight:600; color:#4a5568; margin-bottom:5px;">{{ __('Nombre') }}</label>
                  <input type="text" id="name" name="name" required placeholder="{{ __('Tu nombre') }}"
                         style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; outline:none;"
                         onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
                </div>
                <div>
                  <label style="display:block; font-size:0.8rem; font-weight:600; color:#4a5568; margin-bottom:5px;">{{ __('Email') }}</label>
                  <input type="email" id="email" name="email" required placeholder="tu@email.com"
                         style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; outline:none;"
                         onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
                </div>
                <div>
                  <label style="display:block; font-size:0.8rem; font-weight:600; color:#4a5568; margin-bottom:5px;">{{ __('Contraseña') }}</label>
                  <div style="position:relative;">
                    <input type="password" id="password" name="password" required minlength="8" placeholder="{{ __('Mínimo 8 caracteres') }}"
                           style="width:100%; padding:10px 12px; padding-right:40px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; outline:none;"
                           onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
                    <button type="button" class="toggle-password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:#9ca3af; cursor:pointer; padding:4px;">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>
                <div>
                  <label style="display:block; font-size:0.8rem; font-weight:600; color:#4a5568; margin-bottom:5px;">{{ __('Confirmar') }}</label>
                  <div style="position:relative;">
                    <input type="password" id="password_confirm" name="password_confirm" required placeholder="{{ __('Repite contraseña') }}"
                           style="width:100%; padding:10px 12px; padding-right:40px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; outline:none;"
                           onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
                    <button type="button" class="toggle-password-confirm" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:#9ca3af; cursor:pointer; padding:4px;">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>

        {{-- Footer: Terms + Submit --}}
        <div style="margin-top:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.8rem; color:#4a5568;">
            <input type="checkbox" id="terms" name="accept_terms" value="1" form="registerForm" required style="width:16px; height:16px; accent-color:#4e73df;">
            <span>{{ __('Acepto los') }} <a href="/p/terms-and-conditions" target="_blank" style="color:#4e73df; font-weight:600;">{{ __('términos') }}</a> {{ __('y') }} <a href="/p/privacy" target="_blank" style="color:#4e73df; font-weight:600;">{{ __('privacidad') }}</a></span>
          </label>
          <button type="submit" id="submitBtn" form="registerForm" disabled style="
            padding:11px 28px; background:#4e73df; color:#fff; border:none; border-radius:8px;
            font-size:0.9rem; font-weight:600; cursor:pointer; transition:all 0.2s; opacity:0.5;
          " onmouseenter="if(!this.disabled)this.style.background='#3d5fc4'" onmouseleave="this.style.background='#4e73df'">
            <i class="bi bi-rocket-takeoff" style="margin-right:6px;"></i>{{ __('Crear mi sitio') }}
          </button>
        </div>

        {{-- Login link --}}
        <div style="text-align:center; margin-top:16px; font-size:0.85rem;">
          <span style="color:#8a94a6;">{{ __('¿Ya tienes cuenta?') }}</span>
          <a href="/customer/login" style="color:#4e73df; font-weight:600; margin-left:4px; text-decoration:none;">{{ __('Inicia sesión') }}</a>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
#subdomain-indicator .available,
#custom-domain-indicator .available { color: #28a745; font-weight: 600; }
#subdomain-indicator .not-available,
#custom-domain-indicator .not-available { color: #dc3545; font-weight: 600; }
#subdomain-indicator .checking,
#custom-domain-indicator .checking { color: #6c757d; }

@media (max-width: 640px) {
  div[style*="grid-template-columns:repeat(4"] { grid-template-columns: repeat(2, 1fr) !important; }
  div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
  div[style*="display:flex; align-items:center; justify-content:space-between"] { flex-direction: column !important; }
  #submitBtn { width: 100% !important; }
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

// Domain type selection
document.querySelectorAll('.reg-domain-opt:not(.disabled)').forEach(opt => {
  opt.addEventListener('click', function() {
    const type = this.dataset.type;
    document.querySelectorAll('.reg-domain-opt').forEach(o => {
      o.style.borderColor = '#e5e7eb';
      o.style.background = '#fff';
    });
    this.style.borderColor = '#4e73df';
    this.style.background = '#f0f4ff';

    currentDomainType = type;
    document.getElementById('domain_type').value = type === 'connect' ? 'custom' : type;

    document.querySelectorAll('.domain-config').forEach(c => c.classList.add('d-none'));
    document.getElementById('config_' + type)?.classList.remove('d-none');

    const acct = document.getElementById('account_section');
    if (type === 'transfer' || type === 'register') {
      acct.style.opacity = '0.4'; acct.style.pointerEvents = 'none';
    } else {
      acct.style.opacity = '1'; acct.style.pointerEvents = 'auto';
    }
    updateSubmitButton();
  });
});

// Subdomain check
document.getElementById('subdomain')?.addEventListener('input', function(e) {
  e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
  const v = e.target.value;
  const ind = document.getElementById('subdomain-indicator');
  clearTimeout(checkTimeout);
  if (v.length < 3) { ind.innerHTML = '<span style="color:#9ca3af"><i class="bi bi-info-circle"></i> {{ __("Mínimo 3 caracteres") }}</span>'; subdomainAvailable = false; updateSubmitButton(); return; }
  ind.innerHTML = '<span class="checking"><i class="bi bi-hourglass-split"></i> {{ __("Verificando...") }}</span>';
  checkTimeout = setTimeout(() => {
    fetch('/customer/check-subdomain?subdomain=' + encodeURIComponent(v)).then(r=>r.json()).then(d => {
      if (d.available) { ind.innerHTML = '<span class="available"><i class="bi bi-check-circle"></i> {{ __("Disponible") }}</span>'; subdomainAvailable = true; }
      else { ind.innerHTML = '<span class="not-available"><i class="bi bi-x-circle"></i> ' + (d.reason||d.error||'{{ __("No disponible") }}') + '</span>'; subdomainAvailable = false; }
      updateSubmitButton();
    }).catch(() => { ind.innerHTML = '<span style="color:#dc3545"><i class="bi bi-exclamation-circle"></i> {{ __("Error") }}</span>'; subdomainAvailable = false; updateSubmitButton(); });
  }, 500);
});

// Custom domain check
document.getElementById('custom_domain')?.addEventListener('input', function(e) {
  const v = e.target.value.toLowerCase().trim(); e.target.value = v;
  const ind = document.getElementById('custom-domain-indicator');
  clearTimeout(checkTimeout);
  if (v.length < 4 || !v.includes('.')) { ind.innerHTML = '<span style="color:#9ca3af"><i class="bi bi-info-circle"></i> {{ __("Introduce un dominio válido") }}</span>'; customDomainValid = false; updateSubmitButton(); return; }
  if (v.includes('musedock.com')) { ind.innerHTML = '<span class="not-available"><i class="bi bi-x-circle"></i> {{ __("Usa Subdominio para musedock") }}</span>'; customDomainValid = false; updateSubmitButton(); return; }
  ind.innerHTML = '<span class="checking"><i class="bi bi-hourglass-split"></i> {{ __("Verificando...") }}</span>';
  checkTimeout = setTimeout(() => {
    fetch('/customer/check-custom-domain?domain=' + encodeURIComponent(v)).then(r=>r.json()).then(d => {
      if (d.available) { ind.innerHTML = '<span class="available"><i class="bi bi-check-circle"></i> ' + (d.message||'{{ __("Listo para vincular") }}') + '</span>'; customDomainValid = true; }
      else { ind.innerHTML = '<span class="not-available"><i class="bi bi-x-circle"></i> ' + (d.error||'{{ __("No disponible") }}') + '</span>'; customDomainValid = false; }
      updateSubmitButton();
    }).catch(() => { ind.innerHTML = '<span class="available"><i class="bi bi-check-circle"></i> {{ __("Formato válido") }}</span>'; customDomainValid = true; updateSubmitButton(); });
  }, 500);
});

function updateSubmitButton() {
  const btn = document.getElementById('submitBtn');
  const terms = document.getElementById('terms')?.checked;
  if (currentDomainType === 'transfer' || currentDomainType === 'register') {
    btn.disabled = true; btn.style.opacity = '0.5';
    btn.innerHTML = '<i class="bi bi-clock" style="margin-right:6px;"></i>{{ __("Próximamente") }}';
    return;
  }
  btn.innerHTML = '<i class="bi bi-rocket-takeoff" style="margin-right:6px;"></i>{{ __("Crear mi sitio") }}';
  let domainOk = currentDomainType === 'subdomain' ? subdomainAvailable : customDomainValid;
  let nameOk = document.getElementById('name').value.trim().length >= 3;
  let emailOk = document.getElementById('email').value.includes('@');
  let passOk = document.getElementById('password').value.length >= 8;
  let matchOk = document.getElementById('password').value === document.getElementById('password_confirm').value;
  let ok = domainOk && terms && nameOk && emailOk && passOk && matchOk;
  btn.disabled = !ok;
  btn.style.opacity = ok ? '1' : '0.5';
}

document.getElementById('terms')?.addEventListener('change', updateSubmitButton);
document.getElementById('registerForm')?.addEventListener('input', updateSubmitButton);

// Submit
document.getElementById('registerForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  if (currentDomainType === 'transfer' || currentDomainType === 'register') {
    Swal.fire({ icon: 'info', title: '{{ __("Próximamente") }}', text: '{{ __("Esta opción estará disponible pronto.") }}' }); return;
  }
  const formData = new FormData(this);
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.style.opacity = '0.5';
  btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="margin-right:6px;"></span>{{ __("Creando...") }}';

  fetch('/register', { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      if (data.nameservers && data.nameservers.length > 0) {
        let ns = '<div style="text-align:left"><p>{{ __("Tu dominio") }} <strong>' + data.domain + '</strong> {{ __("está configurado.") }}</p>';
        ns += '<div style="background:#fff3cd;padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:0.85rem"><strong>{{ __("Acción requerida:") }}</strong> {{ __("Cambia los nameservers a:") }}</div>';
        ns += '<div style="background:#f3f4f6;padding:10px;border-radius:6px;font-family:monospace;font-size:0.85rem">';
        data.nameservers.forEach((n,i) => { ns += '<div>NS'+(i+1)+': <strong>'+n+'</strong></div>'; });
        ns += '</div></div>';
        Swal.fire({ icon: 'success', title: '{{ __("¡Cuenta creada!") }}', html: ns, confirmButtonColor: '#4e73df', confirmButtonText: '{{ __("Ir al Dashboard") }}' })
          .then(() => { window.location.href = '/customer/dashboard'; });
      } else {
        Swal.fire({ icon: 'success', title: '{{ __("¡Cuenta creada!") }}', html: '<p>{{ __("Tu sitio está listo en:") }}</p><p style="font-size:1.1rem;font-weight:700;color:#4e73df">' + data.domain + '</p>', showConfirmButton: false, timer: 2500 })
          .then(() => window.location.href = data.redirect || '/customer/dashboard');
      }
      return;
    }
    Swal.fire({ icon: 'error', title: '{{ __("Error") }}', text: data.error || '{{ __("No se pudo crear la cuenta") }}' });
    btn.disabled = false; btn.style.opacity = '1'; btn.innerHTML = '<i class="bi bi-rocket-takeoff" style="margin-right:6px;"></i>{{ __("Crear mi sitio") }}';
  })
  .catch(() => {
    Swal.fire({ icon: 'error', title: '{{ __("Error") }}', text: '{{ __("Error de conexión. Intenta de nuevo.") }}' });
    btn.disabled = false; btn.style.opacity = '1'; btn.innerHTML = '<i class="bi bi-rocket-takeoff" style="margin-right:6px;"></i>{{ __("Crear mi sitio") }}';
  });
});

// Toggle password
document.querySelector('.toggle-password')?.addEventListener('click', function() {
  const i = document.getElementById('password'); const ic = this.querySelector('i');
  i.type = i.type === 'password' ? 'text' : 'password';
  ic.classList.toggle('bi-eye'); ic.classList.toggle('bi-eye-slash');
});
document.querySelector('.toggle-password-confirm')?.addEventListener('click', function() {
  const i = document.getElementById('password_confirm'); const ic = this.querySelector('i');
  i.type = i.type === 'password' ? 'text' : 'password';
  ic.classList.toggle('bi-eye'); ic.classList.toggle('bi-eye-slash');
});
</script>
@endpush
