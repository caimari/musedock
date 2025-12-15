@extends('layouts.login')

@section('title', __('auth.login'))

@section('content')
<div class="login-box">
    <div class="login-logo">
        <a href="#"><b>MuseDock</b> CMS</a>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            @php
                $adminActiveLanguages = [];
                try {
                    $pdo = \Screenart\Musedock\Database::connect();
                    $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id IS NULL AND active = 1 ORDER BY order_position ASC, id ASC");
                    $stmt->execute();
                    $adminActiveLanguages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    $adminActiveLanguages = [
                        ['code' => 'es', 'name' => 'Español'],
                        ['code' => 'en', 'name' => 'English'],
                    ];
                }

                if (session_status() !== PHP_SESSION_ACTIVE) {
                    \Screenart\Musedock\Security\SessionSecurity::startSession();
                }

                $currentLocale = $_SESSION['superadmin_locale']
                    ?? $_SESSION['locale']
                    ?? $_SESSION['lang']
                    ?? $_COOKIE['superadmin_locale']
                    ?? 'es';

                $currentUrl = $_SERVER['REQUEST_URI'] ?? '/musedock/login';
                $showAdminLangSelector = count($adminActiveLanguages) > 1;
            @endphp

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

                <div class="input-group mb-3">
                    <input type="password" id="password" name="password" placeholder="{{ __('auth.password') }}" class="form-control" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>

                @php
                    $showCaptcha = \Screenart\Musedock\Security\Captcha::shouldShow();
                @endphp

                @if($showCaptcha)
                    <div class="mb-3">
                        <label class="form-label">{{ __('auth.captcha_label') ?? 'Código de verificación' }}</label>
                        <div class="d-flex align-items-center gap-2">
                            <img src="/musedock/captcha?t={{ time() }}" alt="CAPTCHA" class="border rounded" style="height: 60px;" id="captchaImage">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="refreshCaptcha" title="{{ __('auth.captcha_refresh') ?? 'Generar nuevo código' }}">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="form-control mt-2" placeholder="{{ __('auth.captcha_placeholder') ?? 'Ingresa el código' }}" required autocomplete="off">
                        <small class="text-muted">{{ __('auth.captcha_help') ?? 'Ingresa los caracteres que ves en la imagen' }}</small>
                    </div>
                @endif

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

            @if($showAdminLangSelector)
                <div class="text-center mt-4">
                    <select class="form-select form-select-sm mx-auto" style="max-width: 220px;"
                            aria-label="{{ __('languages.select_language') ?? 'Select language' }}"
                            onchange="if (this.value) window.location.href = this.value;">
                        @foreach($adminActiveLanguages as $langItem)
                            <option value="/musedock/language/switch?locale={{ $langItem['code'] }}&redirect={{ urlencode($currentUrl) }}"
                                    {{ $currentLocale === $langItem['code'] ? 'selected' : '' }}>
                                {{ $langItem['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

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

    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    if (togglePassword && passwordInput && eyeIcon) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            if (type === 'password') {
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            } else {
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            }
        });
    }

    // Refresh CAPTCHA
    const refreshCaptcha = document.getElementById('refreshCaptcha');
    const captchaImage = document.getElementById('captchaImage');

    if (refreshCaptcha && captchaImage) {
        refreshCaptcha.addEventListener('click', function() {
            captchaImage.src = '/musedock/captcha?t=' + new Date().getTime();
        });
    }
});
</script>
@endpush
