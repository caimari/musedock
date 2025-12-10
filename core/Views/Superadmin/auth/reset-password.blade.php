<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Restablecer Contraseña - MuseDock</title>
    <link href="/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .auth-container {
            max-width: 450px;
            width: 100%;
            padding: 2rem;
        }

        .auth-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 2.5rem;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-text {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            color: #718096;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }

        .strength-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            transition: all 0.3s ease;
            width: 0%;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .icon-wrapper {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .icon-wrapper i {
            font-size: 1.8rem;
            color: white;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #718096;
        }

        .password-input-wrapper {
            position: relative;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo-container">
                <div class="icon-wrapper">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <div class="logo-text">MuseDock CMS</div>
            </div>

            <h4 class="text-center mb-3" style="color: #2d3748;">Nueva Contraseña</h4>
            <p class="subtitle">
                Ingresa tu nueva contraseña. Debe tener al menos 8 caracteres.
            </p>

            @if(isset($error))
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ $error }}
                </div>
            @endif

            <form method="POST" action="{{ route('superadmin.password.update') }}" id="resetForm">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-1"></i>
                        Nueva Contraseña
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               placeholder="Mínimo 8 caracteres"
                               required
                               autofocus>
                        <i class="bi bi-eye password-toggle" id="togglePassword"></i>
                    </div>
                    <div class="strength-bar">
                        <div class="strength-bar-fill" id="strengthBar"></div>
                    </div>
                    <div class="password-strength" id="strengthText"></div>
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">
                        <i class="bi bi-lock-fill me-1"></i>
                        Confirmar Contraseña
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password"
                               class="form-control"
                               id="password_confirmation"
                               name="password_confirmation"
                               placeholder="Repite la contraseña"
                               required>
                        <i class="bi bi-eye password-toggle" id="togglePasswordConfirm"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-2"></i>
                    Restablecer Contraseña
                </button>
            </form>
        </div>
    </div>

    <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });

        document.getElementById('togglePasswordConfirm').addEventListener('click', function() {
            const password = document.getElementById('password_confirmation');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });

        // Password strength meter
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');

            let strength = 0;
            let text = '';
            let color = '';

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch(strength) {
                case 0:
                case 1:
                    text = 'Débil';
                    color = '#ef4444';
                    break;
                case 2:
                    text = 'Media';
                    color = '#f59e0b';
                    break;
                case 3:
                    text = 'Fuerte';
                    color = '#10b981';
                    break;
                case 4:
                    text = 'Muy fuerte';
                    color = '#059669';
                    break;
            }

            strengthBar.style.width = (strength * 25) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = password.length > 0 ? `Fortaleza: ${text}` : '';
            strengthText.style.color = color;
        });

        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirmation').value;

            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }

            if (password.length < 8) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 8 caracteres');
                return false;
            }
        });
    </script>
</body>
</html>
