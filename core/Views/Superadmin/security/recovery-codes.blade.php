@extends('layouts.app')

@section('title', $title ?? 'Códigos de Recuperación')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <div class="row justify-content-center">
      <div class="col-lg-8">

        <div class="card">
          <div class="card-header bg-warning text-dark">
            <h5 class="card-title mb-0">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              Nuevos Códigos de Recuperación Generados
            </h5>
          </div>
          <div class="card-body">

            <div class="alert alert-danger">
              <i class="bi bi-exclamation-octagon-fill me-2"></i>
              <strong>¡IMPORTANTE!</strong> Esta es la única vez que verás estos códigos. Guárdalos ahora en un lugar seguro.
            </div>

            <p>Tus códigos de recuperación anteriores han sido invalidados. Usa estos nuevos códigos si pierdes acceso a tu app de autenticación:</p>

            <div class="row g-2 mb-4" id="recovery-codes">
              @foreach($recovery_codes as $code)
                <div class="col-6 col-md-4 col-lg-3">
                  <code class="d-block p-2 bg-light text-center rounded fs-5">{{ $code }}</code>
                </div>
              @endforeach
            </div>

            <div class="d-flex gap-2 mb-4">
              <button type="button" class="btn btn-outline-primary" onclick="copyCodes()">
                <i class="bi bi-clipboard"></i> Copiar códigos
              </button>
              <button type="button" class="btn btn-outline-primary" onclick="downloadCodes()">
                <i class="bi bi-download"></i> Descargar
              </button>
              <button type="button" class="btn btn-outline-primary" onclick="printCodes()">
                <i class="bi bi-printer"></i> Imprimir
              </button>
            </div>

            <div class="alert alert-info">
              <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Instrucciones</h6>
              <ul class="mb-0">
                <li>Cada código solo puede usarse una vez</li>
                <li>Guárdalos en un lugar seguro (gestor de contraseñas, caja fuerte, etc.)</li>
                <li>No compartas estos códigos con nadie</li>
                <li>Si usas todos los códigos, genera nuevos desde la configuración de 2FA</li>
              </ul>
            </div>

            <div class="text-center mt-4">
              <a href="/musedock/security/2fa" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle"></i> He guardado mis códigos
              </a>
            </div>

          </div>
        </div>

      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
function copyCodes() {
  const codes = [];
  document.querySelectorAll('#recovery-codes code').forEach(el => {
    codes.push(el.textContent.trim());
  });

  navigator.clipboard.writeText(codes.join('\n')).then(() => {
    Swal.fire({
      icon: 'success',
      title: 'Copiado',
      text: 'Códigos copiados al portapapeles',
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
