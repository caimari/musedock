@extends('layouts.app')

@section('title', $title ?? 'Autenticación de Dos Factores')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>{{ $title ?? 'Autenticación de Dos Factores' }}</h2>
      <a href="/musedock/profile" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver al Perfil
      </a>
    </div>

    <div class="row">
      <div class="col-lg-8">

        {{-- Estado de 2FA --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="bi bi-shield-check me-2"></i>Estado de 2FA
            </h5>
          </div>
          <div class="card-body">
            @if($is_enabled)
              <div class="alert alert-success d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                <div>
                  <strong>2FA está activado</strong>
                  <p class="mb-0 small">Tu cuenta está protegida con autenticación de dos factores.</p>
                </div>
              </div>

              <div class="d-flex gap-2 mt-3">
                <a href="/musedock/security/2fa/setup" class="btn btn-outline-primary">
                  <i class="bi bi-arrow-repeat"></i> Reconfigurar 2FA
                </a>
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#regenerateCodesModal">
                  <i class="bi bi-key"></i> Regenerar Códigos de Recuperación
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disable2FAModal">
                  <i class="bi bi-shield-x"></i> Desactivar 2FA
                </button>
              </div>

            @else
              <div class="alert alert-warning d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                <div>
                  <strong>2FA no está activado</strong>
                  <p class="mb-0 small">Te recomendamos activar la autenticación de dos factores para mayor seguridad.</p>
                </div>
              </div>

              <a href="/musedock/security/2fa/setup" class="btn btn-primary mt-3">
                <i class="bi bi-shield-plus"></i> Activar 2FA ahora
              </a>
            @endif
          </div>
        </div>

        {{-- Información --}}
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="bi bi-info-circle me-2"></i>¿Qué es 2FA?
            </h5>
          </div>
          <div class="card-body">
            <p>La autenticación de dos factores (2FA) añade una capa adicional de seguridad a tu cuenta. Además de tu contraseña, necesitarás un código temporal generado por una app en tu teléfono.</p>

            <h6 class="mt-4 mb-3">Apps compatibles:</h6>
            <div class="row">
              <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                  <i class="bi bi-google fs-4 me-2 text-primary"></i>
                  <span>Google Authenticator</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                  <i class="bi bi-microsoft fs-4 me-2 text-info"></i>
                  <span>Microsoft Authenticator</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                  <i class="bi bi-phone fs-4 me-2 text-success"></i>
                  <span>Authy</span>
                </div>
              </div>
            </div>

            <div class="alert alert-info mt-4 mb-0">
              <i class="bi bi-lightbulb me-2"></i>
              <strong>Consejo:</strong> Guarda tus códigos de recuperación en un lugar seguro. Los necesitarás si pierdes acceso a tu app de autenticación.
            </div>
          </div>
        </div>

      </div>

      <div class="col-lg-4">
        {{-- Sidebar de ayuda --}}
        <div class="card bg-light">
          <div class="card-body">
            <h6><i class="bi bi-question-circle me-2"></i>¿Necesitas ayuda?</h6>
            <ul class="small mb-0">
              <li class="mb-2">Si pierdes tu teléfono, usa un código de recuperación</li>
              <li class="mb-2">Los códigos cambian cada 30 segundos</li>
              <li>Contacta soporte si tienes problemas</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

{{-- Modal Desactivar 2FA --}}
<div class="modal fade" id="disable2FAModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="/musedock/security/2fa/disable">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Desactivar 2FA</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>¡Advertencia!</strong> Desactivar 2FA reducirá la seguridad de tu cuenta.
          </div>
          <div class="mb-3">
            <label class="form-label">Confirma tu contraseña</label>
            <input type="password" name="password" class="form-control" required placeholder="Tu contraseña actual">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Desactivar 2FA</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal Regenerar Códigos --}}
<div class="modal fade" id="regenerateCodesModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="/musedock/security/2fa/regenerate-codes">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Regenerar Códigos de Recuperación</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Atención:</strong> Los códigos de recuperación actuales dejarán de funcionar.
          </div>
          <p>Se generarán 10 nuevos códigos de recuperación. Asegúrate de guardarlos en un lugar seguro.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Regenerar Códigos</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
