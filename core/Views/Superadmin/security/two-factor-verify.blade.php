<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verificación 2FA - MuseDock</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .verify-card {
      background: white;
      border-radius: 1rem;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      max-width: 420px;
      width: 100%;
    }
    .code-input {
      font-size: 2rem;
      letter-spacing: 0.5rem;
      text-align: center;
      font-family: monospace;
    }
    .code-input::placeholder {
      letter-spacing: 0.3rem;
    }
  </style>
</head>
<body>
  <div class="verify-card p-4 p-md-5">

    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 p-3 mb-3">
        <i class="bi bi-shield-lock fs-1 text-primary"></i>
      </div>
      <h4 class="fw-bold">Verificación de Seguridad</h4>
      <p class="text-muted">Ingresa el código de tu app de autenticación</p>
    </div>

    @if(session('error'))
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>{{ session('error') }}</div>
      </div>
    @endif

    <form method="POST" action="/musedock/login/2fa" id="verify-form">
      @csrf
      <input type="hidden" name="use_recovery" value="0" id="use-recovery-input">

      <div class="mb-4">
        <input type="text" name="code" class="form-control form-control-lg code-input"
               maxlength="8" placeholder="000000"
               autocomplete="one-time-code" inputmode="numeric"
               autofocus required id="code-input">
        <div class="form-text text-center mt-2">
          <span id="code-hint">Código de 6 dígitos</span>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
        <i class="bi bi-check-circle"></i> Verificar
      </button>

      <div class="text-center">
        <button type="button" class="btn btn-link text-decoration-none" onclick="toggleRecoveryMode()">
          <i class="bi bi-key"></i> Usar código de recuperación
        </button>
      </div>
    </form>

    <hr>

    <div class="text-center">
      <a href="/musedock/login" class="btn btn-link text-muted text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver al login
      </a>
    </div>

  </div>

  <script>
    let recoveryMode = false;

    function toggleRecoveryMode() {
      recoveryMode = !recoveryMode;
      const codeInput = document.getElementById('code-input');
      const codeHint = document.getElementById('code-hint');
      const useRecoveryInput = document.getElementById('use-recovery-input');

      if (recoveryMode) {
        codeInput.placeholder = 'XXXX-XXXX';
        codeInput.maxLength = 9;
        codeInput.inputMode = 'text';
        codeHint.textContent = 'Código de recuperación (8 caracteres)';
        useRecoveryInput.value = '1';
      } else {
        codeInput.placeholder = '000000';
        codeInput.maxLength = 6;
        codeInput.inputMode = 'numeric';
        codeHint.textContent = 'Código de 6 dígitos';
        useRecoveryInput.value = '0';
      }

      codeInput.value = '';
      codeInput.focus();
    }

    // Auto-submit cuando se completa el código
    document.getElementById('code-input').addEventListener('input', function(e) {
      const value = e.target.value.replace(/[^0-9]/g, '');

      if (!recoveryMode) {
        e.target.value = value;
        if (value.length === 6) {
          document.getElementById('verify-form').submit();
        }
      }
    });
  </script>
</body>
</html>
