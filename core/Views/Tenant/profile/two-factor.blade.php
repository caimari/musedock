@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
  <div class="d-flex align-items-center gap-3">
    <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#6c757d,#adb5bd);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="bi bi-shield-lock" style="font-size:1.35rem;color:#fff;"></i>
    </div>
    <div>
      <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Protege tu cuenta con un segundo paso</h3>
      <p class="text-muted mb-0" style="font-size:0.85rem;">Además de la contraseña, se pedirá un código de tu móvil al iniciar sesión y al autorizar conexiones de IA.</p>
    </div>
  </div>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    @if($enabled)
      <div style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:rgba(25,135,84,0.1);border:1px solid rgba(25,135,84,0.2);color:#198754;">
        <i class="bi bi-check-circle-fill"></i><span>Activada</span>
      </div>
    @else
      <div style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:rgba(220,53,69,0.08);border:1px solid rgba(220,53,69,0.2);color:#dc3545;">
        <i class="bi bi-exclamation-circle"></i><span>Desactivada</span>
      </div>
    @endif
    <a href="{{ $adminBase }}/profile" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;text-decoration:none;color:#6c757d;border:1px solid #e9ecef;background:#f8f9fa;">
      <i class="bi bi-person"></i><span>Mi perfil</span>
    </a>
  </div>
</div>

@if($recoveryCodes)
  <div class="card border-warning mb-4">
    <div class="card-body">
      <h5 class="mb-2"><i class="bi bi-key-fill text-warning me-1"></i> Códigos de recuperación</h5>
      <p class="small mb-3">Guárdalos en un lugar seguro (gestor de contraseñas o impresos). Cada uno sirve <strong>una sola vez</strong> si pierdes el móvil. <strong>No se volverán a mostrar.</strong></p>
      <div class="row g-2 mb-3" id="recoveryCodes">
        @foreach($recoveryCodes as $rc)
          <div class="col-6 col-md-3"><code class="d-block text-center p-2 bg-light border rounded" style="font-size:0.95rem;">{{ $rc }}</code></div>
        @endforeach
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="copyCodes"><i class="bi bi-clipboard"></i> Copiar todos</button>
    </div>
  </div>
@endif

@if(!$enabled)
  <div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Activar verificación en dos pasos</h5></div>
    <div class="card-body">
      <div class="row g-4 align-items-center">
        <div class="col-md-5 text-center">
          <div id="qrcode" class="d-inline-block border rounded p-3 bg-white"></div>
          <div class="small text-muted mt-2">¿No puedes escanear? Introduce esta clave:</div>
          <code class="d-block mt-1" style="word-break:break-all;">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
        </div>
        <div class="col-md-7">
          <ol class="mb-3 ps-3">
            <li>Instala una app de autenticación: Google Authenticator, Microsoft Authenticator, 1Password, Authy…</li>
            <li>Escanea el código QR con la app.</li>
            <li>Escribe el código de 6 dígitos que muestra para confirmar.</li>
          </ol>
          <form method="POST" action="{{ $adminBase }}/profile/2fa/enable" class="d-flex gap-2" style="max-width:340px;">
            {!! csrf_field() !!}
            <input type="text" name="code" class="form-control text-center" style="letter-spacing:0.25em;font-family:monospace;" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
            <button type="submit" class="btn btn-primary text-nowrap">Activar</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@else
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><h5 class="card-title mb-0">Estado</h5></div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="mb-2"><i class="bi bi-calendar-check text-muted me-1"></i> Activada: {{ $enabledAt ? date('d/m/Y H:i', strtotime($enabledAt)) : '—' }}</li>
            <li class="mb-2"><i class="bi bi-clock-history text-muted me-1"></i> Último uso: {{ $lastUsedAt ? date('d/m/Y H:i', strtotime($lastUsedAt)) : 'Nunca' }}</li>
            <li class="{{ $remainingCodes <= 3 ? 'text-danger' : '' }}"><i class="bi bi-key text-muted me-1"></i> Códigos de recuperación restantes: <strong>{{ $remainingCodes }}</strong></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><h5 class="card-title mb-0">Gestionar</h5></div>
        <div class="card-body">
          <p class="small text-muted">Confirma con tu contraseña y un código de la app (o de recuperación).</p>
          <form method="POST" id="manageForm">
            {!! csrf_field() !!}
            <div class="row g-2 mb-3">
              <div class="col-sm-6"><input type="password" name="password" class="form-control" placeholder="Contraseña" autocomplete="current-password" required></div>
              <div class="col-sm-6"><input type="text" name="code" class="form-control" placeholder="Código" autocomplete="one-time-code" required></div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button type="submit" class="btn btn-outline-primary btn-sm" formaction="{{ $adminBase }}/profile/2fa/recovery-codes"><i class="bi bi-arrow-repeat"></i> Nuevos códigos de recuperación</button>
              <button type="submit" class="btn btn-outline-danger btn-sm" formaction="{{ $adminBase }}/profile/2fa/disable" id="btnDisable"><i class="bi bi-shield-x"></i> Desactivar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endif
@endsection

@push('scripts')
@if(!$enabled)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById('qrcode'), {
    text: {!! json_encode($otpauthUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
    width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M
  });
</script>
@endif
<script>
(function () {
  var copy = document.getElementById('copyCodes');
  if (copy) {
    copy.addEventListener('click', function () {
      var codes = Array.prototype.map.call(document.querySelectorAll('#recoveryCodes code'), function (c) { return c.textContent.trim(); });
      navigator.clipboard.writeText(codes.join('\n')).then(function () { copy.innerHTML = '<i class="bi bi-check2"></i> Copiados'; });
    });
  }
  var disable = document.getElementById('btnDisable');
  if (disable) {
    disable.addEventListener('click', function (e) {
      if (!confirm('¿Desactivar la verificación en dos pasos? Tu cuenta quedará protegida solo por la contraseña.')) e.preventDefault();
    });
  }
})();
</script>
@endpush
