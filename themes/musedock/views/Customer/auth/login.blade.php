@extends('layouts.app')

@section('title')
{{ ($page_title ?? __('Iniciar sesión')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('content')
<div class="padding-none ziph-page_content">
  <div class="container" style="padding-top:32px; padding-bottom:48px;">
    <div class="row justify-content-center">
      <div class="col-md-5 col-lg-4">

        {{-- Title --}}
        <div class="text-center" style="margin-bottom:24px;">
          <h1 style="color:#243141; font-size:1.25rem; font-weight:600; margin:0;">{{ __('Accede a tu panel') }}</h1>
        </div>

        {{-- Login Card --}}
        <div style="background:#fff; border-radius:12px; box-shadow:0 2px 16px rgba(0,0,0,0.08); padding:28px 24px; border:1px solid #edf0f5;">

          @if(!empty($flash_error ?? null))
          <div class="alert alert-danger py-2" role="alert" style="font-size:0.85rem;">
            {{ $flash_error }}
          </div>
          @endif

          <form id="loginForm" method="POST" action="/customer/login">
            <input type="hidden" name="_csrf_token" value="{{ $csrf_token ?? csrf_token() }}">
            @if(!empty($redirect_after_login ?? null))
            <input type="hidden" name="redirect" value="{{ $redirect_after_login }}">
            @endif

            <div style="margin-bottom:16px;">
              <label for="email" style="display:block; font-size:0.8rem; font-weight:600; color:#4a5568; margin-bottom:5px;">{{ __('Email') }}</label>
              <input type="email" id="email" name="email" required placeholder="tu@email.com" autofocus
                     style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; outline:none; transition:border-color 0.2s;"
                     onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
            </div>

            <div style="margin-bottom:16px;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                <label for="password" style="font-size:0.8rem; font-weight:600; color:#4a5568; margin:0;">{{ __('Contraseña') }}</label>
                <a href="/customer/forgot-password" style="font-size:0.75rem; color:#6b7280; text-decoration:none;" onmouseenter="this.style.color='#4e73df'" onmouseleave="this.style.color='#6b7280'">{{ __('¿Olvidaste?') }}</a>
              </div>
              <div style="position:relative;">
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="{{ __('Tu contraseña') }}"
                       style="width:100%; padding:10px 46px 10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; outline:none; transition:border-color 0.2s;"
                       onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
                <button type="button" id="togglePassword" onclick="toggleLoginPassword()" aria-controls="password" aria-pressed="false" aria-label="{{ __('Mostrar contraseña') }}" title="{{ __('Mostrar contraseña') }}"
                        style="position:absolute; top:50%; right:6px; transform:translateY(-50%); width:36px; height:36px; border:0; background:transparent; color:#64748b; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center;">
                  <svg id="togglePasswordIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>

            <div style="display:flex; align-items:center; margin-bottom:20px;">
              <input type="checkbox" id="remember" name="remember" value="1" style="width:16px; height:16px; margin-right:8px; accent-color:#4e73df;">
              <label for="remember" style="font-size:0.8rem; color:#6b7280; margin:0; cursor:pointer;">{{ __('Recordarme (30 días)') }}</label>
            </div>

            <button type="submit" id="submitBtn" style="
              width:100%; padding:11px 20px;
              background:#4e73df; color:#fff;
              border:none; border-radius:8px;
              font-size:0.9rem; font-weight:600;
              cursor:pointer; transition:background 0.2s;
            " onmouseenter="this.style.background='#3d5fc4'" onmouseleave="this.style.background='#4e73df'">
              {{ __('Iniciar sesión') }}
            </button>
          </form>

          {{-- Divider --}}
          <div style="display:flex; align-items:center; margin:20px 0 16px; gap:12px;">
            <div style="flex:1; height:1px; background:#e5e7eb;"></div>
            <span style="font-size:0.75rem; color:#9ca3af;">o</span>
            <div style="flex:1; height:1px; background:#e5e7eb;"></div>
          </div>

          {{-- Register CTA --}}
          <a href="/register" style="
            display:block; text-align:center;
            padding:10px 20px;
            border:1px solid #d1d5db; border-radius:8px;
            color:#4a5568; font-size:0.85rem; font-weight:500;
            text-decoration:none; transition:all 0.2s;
          " onmouseenter="this.style.borderColor='#4e73df';this.style.color='#4e73df'" onmouseleave="this.style.borderColor='#d1d5db';this.style.color='#4a5568'">
            {{ __('Crear cuenta gratis') }}
          </a>
        </div>

        {{-- Trust note --}}
        <p style="text-align:center; font-size:0.72rem; color:#9ca3af; margin-top:16px;">
          <svg width="12" height="12" viewBox="0 0 16 16" fill="#9ca3af" style="vertical-align:-1px; margin-right:3px;"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
          Conexión segura cifrada con SSL
        </p>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggleLoginPassword() {
  var input = document.getElementById('password');
  var btn = document.getElementById('togglePassword');
  var icon = document.getElementById('togglePasswordIcon');
  var show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.setAttribute('aria-pressed', show ? 'true' : 'false');
  var label = show ? @json(__('Ocultar contraseña')) : @json(__('Mostrar contraseña'));
  btn.setAttribute('aria-label', label);
  btn.setAttribute('title', label);
  icon.innerHTML = show
    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  input.focus();
}

let csrfRetryCount = 0;
const MAX_CSRF_RETRIES = 1;

function submitLogin(formData, submitBtn) {
  fetch('/customer/login', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: formData,
    credentials: 'same-origin'
  })
  .then(async (response) => {
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      return { success: false, error: data.error || data.message || 'HTTP ' + response.status, new_csrf_token: data.new_csrf_token };
    }
    return data;
  })
  .then((data) => {
    if (data && data.success) {
      // Directo al panel, sin ventana intermedia
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = @json(__('Entrando…')); }
      window.location.href = data.redirect || '/customer/dashboard';
      return;
    }

    if (data.error === 'csrf_token_mismatch' && data.new_csrf_token) {
      document.querySelector('input[name="_csrf_token"]').value = data.new_csrf_token;
      if (csrfRetryCount < MAX_CSRF_RETRIES) {
        csrfRetryCount++;
        formData.set('_csrf_token', data.new_csrf_token);
        submitLogin(formData, submitBtn);
        return;
      } else {
        Swal.fire({ icon: 'warning', title: '{{ __("Sesión expirada") }}', text: '{{ __("Tu sesión ha expirado. Por favor, intenta de nuevo.") }}', confirmButtonText: '{{ __("Reintentar") }}' });
      }
    } else {
      Swal.fire({ icon: 'error', title: '{{ __("Error de acceso") }}', text: (data && (data.error || data.message)) ? (data.error || data.message) : '{{ __("Email o contraseña incorrectos") }}' });
    }

    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '{{ __("Iniciar sesión") }}';
    }
  })
  .catch(() => {
    Swal.fire({ icon: 'error', title: '{{ __("Error") }}', text: '{{ __("Error de conexión. Por favor, intenta de nuevo.") }}' });
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '{{ __("Iniciar sesión") }}';
    }
  });
}

document.getElementById('loginForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  csrfRetryCount = 0;
  const formData = new FormData(this);
  const submitBtn = document.getElementById('submitBtn');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __("Iniciando sesión...") }}';
  }
  submitLogin(formData, submitBtn);
});
</script>
@endpush
