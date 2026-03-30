@extends('layouts.app')

@section('title', $title ?? 'Configurar 2FA')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>{{ $title ?? 'Configurar 2FA' }}</h2>
      <a href="/musedock/security/2fa" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Cancelar
      </a>
    </div>

    <div class="row">
      <div class="col-lg-8">

        {{-- Paso 1: Escanear QR --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <span class="badge bg-primary me-2">1</span>
              Escanea el código QR
            </h5>
          </div>
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-md-6 text-center mb-3 mb-md-0">
                {{-- QR generado con JavaScript --}}
                <div id="qrcode" class="d-inline-block border rounded p-3 bg-white"></div>
              </div>
              <div class="col-md-6">
                <p>Abre tu app de autenticación y escanea este código QR.</p>
                <p class="text-muted small">Si no puedes escanear el código, ingresa esta clave manualmente:</p>
                <div class="input-group">
                  <input type="text" class="form-control font-monospace" value="{{ $secret }}" id="secret-key" readonly>
                  <button class="btn btn-outline-secondary" type="button" onclick="copySecret()">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Paso 2: Guardar códigos de recuperación --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <span class="badge bg-primary me-2">2</span>
              Guarda tus códigos de recuperación
            </h5>
          </div>
          <div class="card-body">
            <div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <strong>¡Importante!</strong> Guarda estos códigos en un lugar seguro. Los necesitarás si pierdes acceso a tu teléfono.
            </div>

            <div class="row g-2 mb-3" id="recovery-codes">
              @foreach($recovery_codes as $code)
                <div class="col-6 col-md-4 col-lg-3">
                  <code class="d-block p-2 bg-light text-center rounded">{{ $code }}</code>
                </div>
              @endforeach
            </div>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyCodes()">
                <i class="bi bi-clipboard"></i> Copiar códigos
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="downloadCodes()">
                <i class="bi bi-download"></i> Descargar códigos
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printCodes()">
                <i class="bi bi-printer"></i> Imprimir
              </button>
            </div>
          </div>
        </div>

        {{-- Paso 3: Verificar código --}}
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <span class="badge bg-primary me-2">3</span>
              Verifica tu configuración
            </h5>
          </div>
          <div class="card-body">
            <p>Ingresa el código de 6 dígitos que muestra tu app de autenticación para confirmar que todo está configurado correctamente.</p>

            <form method="POST" action="/musedock/security/2fa/enable">
              @csrf
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Código de verificación</label>
                    <input type="text" name="code" class="form-control form-control-lg text-center font-monospace"
                           maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                           autocomplete="one-time-code" inputmode="numeric" required>
                    <div class="form-text">Ingresa el código de 6 dígitos de tu app</div>
                  </div>
                  <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-shield-check"></i> Activar 2FA
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

      </div>

      <div class="col-lg-4">
        {{-- Instrucciones --}}
        <div class="card bg-light">
          <div class="card-body">
            <h6><i class="bi bi-info-circle me-2"></i>Instrucciones</h6>
            <ol class="small mb-0">
              <li class="mb-2">Descarga una app de autenticación en tu teléfono (Google Authenticator, Authy, etc.)</li>
              <li class="mb-2">Escanea el código QR con la app</li>
              <li class="mb-2">Guarda los códigos de recuperación en un lugar seguro</li>
              <li>Ingresa el código de 6 dígitos para verificar</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
{{-- QRCode.js - davidshimjs (más confiable y ampliamente usado) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Generar QR al cargar la página
document.addEventListener('DOMContentLoaded', function() {
  const otpauthUrl = @json($qr_code_url);
  const qrcodeContainer = document.getElementById('qrcode');

  // Limpiar contenedor
  qrcodeContainer.innerHTML = '';

  try {
    new QRCode(qrcodeContainer, {
      text: otpauthUrl,
      width: 200,
      height: 200,
      colorDark: '#000000',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.H
    });
  } catch (error) {
    console.error('Error generando QR:', error);
    qrcodeContainer.innerHTML = '<p class="text-danger small">Error al generar QR.<br>Usa la clave manual de abajo.</p>';
  }
});

function copySecret() {
  const secretInput = document.getElementById('secret-key');
  secretInput.select();
  document.execCommand('copy');

  Swal.fire({
    icon: 'success',
    title: 'Copiado',
    text: 'Clave secreta copiada al portapapeles',
    timer: 1500,
    showConfirmButton: false
  });
}

function copyCodes() {
  const codes = [];
  document.querySelectorAll('#recovery-codes code').forEach(el => {
    codes.push(el.textContent.trim());
  });

  navigator.clipboard.writeText(codes.join('\n')).then(() => {
    Swal.fire({
      icon: 'success',
      title: 'Copiado',
      text: 'Códigos de recuperación copiados',
      timer: 1500,
      showConfirmButton: false
    });
  });
}

function downloadCodes() {
  const codes = [];
  document.querySelectorAll('#recovery-codes code').forEach(el => {
    codes.push(el.textContent.trim());
  });

  const content = `MuseDock - Códigos de Recuperación 2FA
========================================
Generados: ${new Date().toLocaleString()}

${codes.join('\n')}

¡IMPORTANTE!
- Guarda estos códigos en un lugar seguro
- Cada código solo puede usarse una vez
- Si los pierdes, no podrás recuperar tu cuenta
`;

  const blob = new Blob([content], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'musedock-recovery-codes.txt';
  a.click();
  URL.revokeObjectURL(url);
}

function printCodes() {
  const codes = [];
  document.querySelectorAll('#recovery-codes code').forEach(el => {
    codes.push(el.textContent.trim());
  });

  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <html>
      <head>
        <title>Códigos de Recuperación - MuseDock</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; }
          h1 { font-size: 18px; }
          .codes { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 20px 0; }
          .code { font-family: monospace; font-size: 16px; padding: 10px; background: #f0f0f0; text-align: center; }
          .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin-top: 20px; }
        </style>
      </head>
      <body>
        <h1>MuseDock - Códigos de Recuperación 2FA</h1>
        <p>Generados: ${new Date().toLocaleString()}</p>
        <div class="codes">
          ${codes.map(c => `<div class="code">${c}</div>`).join('')}
        </div>
        <div class="warning">
          <strong>¡IMPORTANTE!</strong> Guarda estos códigos en un lugar seguro. Cada código solo puede usarse una vez.
        </div>
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.print();
}
</script>
@endpush
