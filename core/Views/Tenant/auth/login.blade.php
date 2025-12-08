@extends('layouts.login-tabler')

@section('title', __('auth.login_title'))

@section('content')
<div class="page py-5">
  <div class="container container-tight py-4">
    <div class="text-center mb-4 mt-4">
      <a href="#" onclick="window.location.href = window.location.origin" class="navbar-brand navbar-brand-autodark">
        <img src="/assets/tenant/img/logo.png" class="logo-login" style="height: 50px; width: auto;" alt="MuseDock" />
      </a>
    </div>

    <div class="card card-md">
      <div class="card-body">
        <h2 class="h2 text-center mb-4">{{ __('auth.login_title') }}</h2>

        {{-- Flash Messages --}}
        @php
          $error = consume_flash('error');
          $success = consume_flash('success');
          $logoutSuccess = consume_flash('logout_success');
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

        @if($logoutSuccess)
          <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>{{ $logoutSuccess }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        <form method="POST" action="/{{ admin_path() }}/login" autocomplete="off" novalidate>
          {!! csrf_field() !!}

          <div class="mb-3">
            <label class="form-label">{{ __('auth.email') }}</label>
            <input
              type="email"
              name="email"
              class="form-control"
              placeholder="email@ejemplo.com"
              value="{{ old('email') }}"
              required
            >
          </div>

          <div class="mb-2">
            <label class="form-label">
              {{ __('auth.password') }}
              <span class="form-label-description">
                <a href="/{{ admin_path() }}/password/forgot">{{ __('auth.forgot_password') }}</a>
              </span>
            </label>
            <div class="input-group input-group-flat">
              <input
                type="password"
                class="form-control"
                name="password"
                id="password-input"
                placeholder="********"
                required
              >
              <span class="input-group-text">
                <a href="#" class="link-secondary toggle-password" title="Ver contraseña" data-bs-toggle="tooltip">
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

          <div class="mb-2">
            <label class="form-check">
              <input type="checkbox" class="form-check-input" name="remember" />
              <span class="form-check-label">{{ __('auth.remember_me') }}</span>
            </label>
          </div>

          <div class="form-footer">
            <button type="submit" class="btn btn-primary w-100">
              {{ __('auth.login') }}
            </button>
          </div>
        </form>
      </div>
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

            // Toggle password type
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icons
            iconEye.classList.toggle('d-none');
            iconEyeOff.classList.toggle('d-none');

            // Update tooltip
            this.setAttribute('title', type === 'password' ? 'Ver contraseña' : 'Ocultar contraseña');
        });
    }
});
</script>
@endpush
