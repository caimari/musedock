@extends('layouts.login-tabler')

@section('title', __('reset_password') ?? 'Nueva contraseña')

@section('content')
<div class="page py-5">
  <div class="container container-tight py-4">
    <div class="text-center mb-4">
      <a href="#" onclick="window.location.href = window.location.origin" class="navbar-brand navbar-brand-autodark">
       <img src="/assets/tenant/img/logo.png" class="logo-login" style="height: 50px; width: auto;" alt="MuseDock" />
      </a>
    </div>

    <form class="card card-md" method="POST" action="/{{ admin_path() }}/password/reset/{{ $token }}" autocomplete="off">
      {!! csrf_field() !!}
      <div class="card-body">
        <h2 class="card-title text-center mb-4">Nueva contraseña</h2>

        @php
          $error = consume_flash('error');
          $success = consume_flash('success');
        @endphp

        @if($error)
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @if($success)
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ $success }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        <div class="mb-3">
          <label class="form-label">{{ __('password') ?? 'Contraseña nueva' }}</label>
          <div class="input-group input-group-flat">
            <input
              type="password"
              class="form-control"
              name="password"
              id="password-input"
              placeholder="Mínimo 6 caracteres"
              required
            >
            <span class="input-group-text">
              <a href="#" class="link-secondary toggle-password" title="Ver contraseña">
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
          <label class="form-label">{{ __('confirm_password') ?? 'Confirmar contraseña' }}</label>
          <div class="input-group input-group-flat">
            <input
              type="password"
              class="form-control"
              name="password_confirmation"
              id="password-confirm-input"
              placeholder="Repite la contraseña"
              required
            >
            <span class="input-group-text">
              <a href="#" class="link-secondary toggle-password-confirm" title="Ver contraseña">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-eye-confirm" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                  <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                  <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-eye-off-confirm d-none" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                  <path d="M10.585 10.587a2 2 0 0 0 2.829 2.828" />
                  <path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87" />
                  <path d="M3 3l18 18" />
                </svg>
              </a>
            </span>
          </div>
        </div>

        <div class="form-footer">
          <button type="submit" class="btn btn-primary w-100">Guardar nueva contraseña</button>
        </div>
      </div>
    </form>

    <div class="text-center text-secondary mt-3">
      <a href="/{{ admin_path() }}/login" tabindex="-1">{{ __('back_to_login') ?? 'Volver a iniciar sesión' }}</a>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.querySelector('.toggle-password');
    const passwordInput = document.getElementById('password-input');
    const iconEye = document.querySelector('.icon-eye');
    const iconEyeOff = document.querySelector('.icon-eye-off');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function(e) {
            e.preventDefault();
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            iconEye.classList.toggle('d-none');
            iconEyeOff.classList.toggle('d-none');
        });
    }

    // Toggle confirm password visibility
    const togglePasswordConfirm = document.querySelector('.toggle-password-confirm');
    const passwordConfirmInput = document.getElementById('password-confirm-input');
    const iconEyeConfirm = document.querySelector('.icon-eye-confirm');
    const iconEyeOffConfirm = document.querySelector('.icon-eye-off-confirm');

    if (togglePasswordConfirm && passwordConfirmInput) {
        togglePasswordConfirm.addEventListener('click', function(e) {
            e.preventDefault();
            const type = passwordConfirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordConfirmInput.setAttribute('type', type);
            iconEyeConfirm.classList.toggle('d-none');
            iconEyeOffConfirm.classList.toggle('d-none');
        });
    }
});
</script>
@endpush
