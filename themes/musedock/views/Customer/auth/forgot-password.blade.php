@extends('layouts.app')

@section('title')
{{ ($page_title ?? __('Recuperar contraseña')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('content')
<div class="padding-none ziph-page_content">
  <div class="container" style="padding-top:32px; padding-bottom:48px;">
    <div class="row justify-content-center">
      <div class="col-md-5 col-lg-4">

        {{-- Title --}}
        <div class="text-center" style="margin-bottom:24px;">
          <div style="width:48px; height:48px; border-radius:12px; background:#f0f4ff; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
            <svg width="24" height="24" viewBox="0 0 16 16" fill="#4e73df"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
          </div>
          <h1 style="color:#243141; font-size:1.25rem; font-weight:600; margin:0 0 6px;">{{ __('Recuperar contraseña') }}</h1>
          <p style="color:#8a94a6; font-size:0.85rem; margin:0;">{{ __('Te enviaremos un enlace para restablecer tu contraseña.') }}</p>
        </div>

        {{-- Card --}}
        <div style="background:#fff; border-radius:12px; box-shadow:0 2px 16px rgba(0,0,0,0.08); padding:28px 24px; border:1px solid #edf0f5;">

          <form id="forgotForm" method="POST" action="/customer/forgot-password">
            <input type="hidden" name="_csrf_token" value="{{ $csrf_token ?? csrf_token() }}">

            <div style="margin-bottom:20px;">
              <label for="email" style="display:block; font-size:0.8rem; font-weight:600; color:#4a5568; margin-bottom:5px;">{{ __('Email de tu cuenta') }}</label>
              <input type="email" id="email" name="email" required placeholder="tu@email.com" autofocus
                     style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; outline:none; transition:border-color 0.2s;"
                     onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
            </div>

            <button type="submit" id="submitBtn" style="
              width:100%; padding:11px 20px;
              background:#4e73df; color:#fff;
              border:none; border-radius:8px;
              font-size:0.9rem; font-weight:600;
              cursor:pointer; transition:background 0.2s;
            " onmouseenter="this.style.background='#3d5fc4'" onmouseleave="this.style.background='#4e73df'">
              {{ __('Enviar enlace de recuperación') }}
            </button>
          </form>

          {{-- Divider --}}
          <div style="display:flex; align-items:center; margin:20px 0 16px; gap:12px;">
            <div style="flex:1; height:1px; background:#e5e7eb;"></div>
            <span style="font-size:0.75rem; color:#9ca3af;">o</span>
            <div style="flex:1; height:1px; background:#e5e7eb;"></div>
          </div>

          <a href="/customer/login" style="
            display:block; text-align:center;
            padding:10px 20px;
            border:1px solid #d1d5db; border-radius:8px;
            color:#4a5568; font-size:0.85rem; font-weight:500;
            text-decoration:none; transition:all 0.2s;
          " onmouseenter="this.style.borderColor='#4e73df';this.style.color='#4e73df'" onmouseleave="this.style.borderColor='#d1d5db';this.style.color='#4a5568'">
            {{ __('Volver al inicio de sesión') }}
          </a>
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
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __("Enviando...") }}';
  }

  fetch('/customer/forgot-password', {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(async (response) => {
    const data = await response.json().catch(() => ({}));
    if (!response.ok) return { success: false, error: data.error || data.message || 'HTTP ' + response.status };
    return data;
  })
  .then((data) => {
    if (data && data.success) {
      Swal.fire({ icon: 'success', title: '{{ __("¡Email enviado!") }}', text: data.message || '{{ __("Revisa tu bandeja de entrada") }}', showConfirmButton: true })
        .then(() => { window.location.href = '/customer/login'; });
      return;
    }
    Swal.fire({ icon: 'error', title: '{{ __("Error") }}', text: (data && (data.error || data.message)) ? (data.error || data.message) : '{{ __("No se pudo enviar el email") }}' });
    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '{{ __("Enviar enlace de recuperación") }}'; }
  })
  .catch(() => {
    Swal.fire({ icon: 'error', title: '{{ __("Error") }}', text: '{{ __("Error de conexión. Por favor, intenta de nuevo.") }}' });
    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '{{ __("Enviar enlace de recuperación") }}'; }
  });
});
</script>
@endpush
