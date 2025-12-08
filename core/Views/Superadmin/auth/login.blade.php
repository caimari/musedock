@extends('layouts.login')

@section('title', __('auth.login'))

@section('content')
<div class="login-box">
    <div class="login-logo">
        <a href="#"><b>MuseDock</b> CMS</a>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">{{ __('auth.login_as_superadmin') }}</p>

            {{-- Mensajes flash - Sistema moderno (_flash array) --}}
            @php
                $error = flash('error');
                $success = flash('success');
                $password_reset_success = flash('password_reset_success');
                $logout_success = flash('logout_success');
            @endphp

            {{-- También soportar sistema legacy (sesión directa) --}}
            @php
                if (!$error && isset($_SESSION['error'])) {
                    $error = $_SESSION['error'];
                    unset($_SESSION['error']);
                }
                if (!$success && isset($_SESSION['success'])) {
                    $success = $_SESSION['success'];
                    unset($_SESSION['success']);
                }
                if (!$password_reset_success && isset($_SESSION['password_reset_success'])) {
                    $password_reset_success = $_SESSION['password_reset_success'];
                    unset($_SESSION['password_reset_success']);
                }
                if (!$logout_success && isset($_SESSION['logout_success'])) {
                    $logout_success = $_SESSION['logout_success'];
                    unset($_SESSION['logout_success']);
                }
            @endphp

            {{-- Mostrar mensajes --}}
            @if($success)
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    {{ $success }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($password_reset_success)
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    {{ $password_reset_success }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($logout_success)
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    {{ $logout_success }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($error)
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ $error }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form method="POST" action="/musedock/login">
                {!! csrf_field() !!}
                <input type="email" name="email" placeholder="{{ __('auth.email') }}" class="form-control mb-3" required>
                <input type="password" name="password" placeholder="{{ __('auth.password') }}" class="form-control mb-3" required>
                <div class="form-check mb-3">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">{{ __('auth.remember_me') }}</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('auth.login') }}</button>
            </form>

            <div class="text-center mt-3">
                <a href="{{ route('superadmin.password.request') }}" class="text-muted">
                    <i class="bi bi-key"></i> {{ __('auth.forgot_password') }}
                </a>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts después de 2 segundos
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 2000);
    });
});
</script>
@endpush

