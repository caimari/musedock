@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="bi bi-envelope me-2"></i>{{ $title }}</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('settings.email.update') }}">
      {!! csrf_field() !!}

      <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Nota:</strong> Estos ajustes modifican las variables del archivo <code>.env</code>. Asegúrate de tener una copia de seguridad antes de hacer cambios.
      </div>

      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Configuración SMTP</h5>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Driver de Email</label>
              <select name="mail_driver" class="form-select">
                <option value="smtp" {{ ($envConfig['MAIL_DRIVER'] ?? 'smtp') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                <option value="sendmail" {{ ($envConfig['MAIL_DRIVER'] ?? '') === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                <option value="mail" {{ ($envConfig['MAIL_DRIVER'] ?? '') === 'mail' ? 'selected' : '' }}>PHP Mail</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Encriptación</label>
              <select name="smtp_encryption" class="form-select">
                <option value="tls" {{ ($envConfig['SMTP_ENCRYPTION'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS (Recomendado)</option>
                <option value="ssl" {{ ($envConfig['SMTP_ENCRYPTION'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                <option value="" {{ empty($envConfig['SMTP_ENCRYPTION'] ?? 'tls') ? 'selected' : '' }}>Sin encriptación</option>
              </select>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-8">
              <label class="form-label">Servidor SMTP</label>
              <input type="text" name="smtp_host" class="form-control" value="{{ $envConfig['SMTP_HOST'] ?? '' }}" placeholder="smtp.gmail.com">
              <small class="text-muted">Ejemplos: smtp.gmail.com, smtp.office365.com, smtp.mailgun.org</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Puerto</label>
              <input type="number" name="smtp_port" class="form-control" value="{{ $envConfig['SMTP_PORT'] ?? '587' }}" placeholder="587">
              <small class="text-muted">TLS: 587, SSL: 465</small>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Usuario SMTP</label>
              <input type="text" name="smtp_username" class="form-control" value="{{ $envConfig['SMTP_USERNAME'] ?? '' }}" placeholder="tu-email@gmail.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">Contraseña SMTP</label>
              <input type="password" name="smtp_password" class="form-control" placeholder="Dejar vacío para mantener actual">
              <small class="text-muted">{{ !empty($envConfig['SMTP_PASSWORD']) ? 'Contraseña configurada (dejar vacío para mantener)' : 'No configurada' }}</small>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Remitente por defecto</h5>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Email del remitente</label>
              <input type="email" name="mail_from_address" class="form-control" value="{{ $envConfig['MAIL_FROM_ADDRESS'] ?? '' }}" placeholder="noreply@tudominio.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombre del remitente</label>
              <input type="text" name="mail_from_name" class="form-control" value="{{ $envConfig['MAIL_FROM_NAME'] ?? '' }}" placeholder="Mi Sitio Web">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
          <h5 class="mb-0">Proveedores populares</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-3">
              <h6><i class="bi bi-google me-1"></i> Gmail</h6>
              <small class="text-muted">
                Host: <code>smtp.gmail.com</code><br>
                Puerto: <code>587</code> (TLS)<br>
                Requiere: App Password
              </small>
            </div>
            <div class="col-md-4 mb-3">
              <h6><i class="bi bi-microsoft me-1"></i> Outlook/Office 365</h6>
              <small class="text-muted">
                Host: <code>smtp.office365.com</code><br>
                Puerto: <code>587</code> (TLS)
              </small>
            </div>
            <div class="col-md-4 mb-3">
              <h6><i class="bi bi-mailbox me-1"></i> IONOS</h6>
              <small class="text-muted">
                Host: <code>smtp.ionos.es</code><br>
                Puerto: <code>587</code> (TLS)
              </small>
            </div>
          </div>
        </div>
      </div>

      <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-2"></i>Guardar configuración
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
