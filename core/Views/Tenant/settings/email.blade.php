@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-envelope me-2"></i>{{ $title }}</h2>
                <p class="text-muted mb-0">Configura el servidor SMTP para enviar emails desde tu sitio</p>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        <form method="POST" action="/{{ admin_path() }}/settings/email">
            {!! csrf_field() !!}

            <div class="row">
                <div class="col-lg-8">
                    @php
                        $smtpRequireTenantOwn = ($smtpRequireTenantOwn ?? '0') === '1';
                        $smtpUseGlobal = ($settings['smtp_use_global'] ?? '1') === '1';
                        if ($smtpRequireTenantOwn) {
                            $smtpUseGlobal = false;
                        }
                    @endphp

                    <!-- Política tenant/global -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Origen del SMTP</h5>
                        </div>
                        <div class="card-body">
                            @if($smtpRequireTenantOwn)
                                <div class="alert alert-warning mb-3">
                                    El superadmin ha configurado este entorno para requerir SMTP propio por tenant.
                                </div>
                            @endif
                            <div class="form-check form-switch">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="smtp_use_global"
                                    name="smtp_use_global"
                                    value="1"
                                    {{ $smtpUseGlobal ? 'checked' : '' }}
                                    {{ $smtpRequireTenantOwn ? 'disabled' : '' }}>
                                <label class="form-check-label" for="smtp_use_global">
                                    Usar SMTP global del CMS
                                </label>
                            </div>
                            @if($smtpRequireTenantOwn)
                                <input type="hidden" name="smtp_use_global" value="0">
                            @endif
                            <small class="text-muted d-block mt-2">
                                Si lo desactivas, este tenant enviará con su propia configuración SMTP.
                            </small>
                        </div>
                    </div>

                    <!-- SMTP -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-server me-2"></i>Servidor SMTP</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Driver de Email</label>
                                    <select name="mail_driver" class="form-select" {{ $smtpUseGlobal ? 'disabled' : '' }}>
                                        @php $mailDriver = $settings['mail_driver'] ?? 'smtp'; @endphp
                                        <option value="smtp" {{ $mailDriver === 'smtp' ? 'selected' : '' }}>SMTP</option>
                                        <option value="sendmail" {{ $mailDriver === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                                        <option value="mail" {{ $mailDriver === 'mail' ? 'selected' : '' }}>PHP Mail</option>
                                    </select>
                                    @if($smtpUseGlobal)
                                        <input type="hidden" name="mail_driver" value="{{ $mailDriver }}">
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Servidor SMTP</label>
                                    <input type="text" name="smtp_host" class="form-control"
                                           value="{{ $settings['smtp_host'] ?? '' }}"
                                           placeholder="smtp.gmail.com"
                                           {{ $smtpUseGlobal ? 'disabled' : '' }}>
                                    <small class="text-muted">Ejemplos: smtp.gmail.com, smtp.office365.com, smtp.mailgun.org</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Puerto</label>
                                    <input type="number" name="smtp_port" class="form-control"
                                           value="{{ $settings['smtp_port'] ?? '587' }}"
                                           placeholder="587"
                                           {{ $smtpUseGlobal ? 'disabled' : '' }}>
                                    <small class="text-muted">TLS: 587, SSL: 465</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Encriptación</label>
                                    <select name="smtp_encryption" class="form-select" {{ $smtpUseGlobal ? 'disabled' : '' }}>
                                        <option value="tls" {{ ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS (Recomendado)</option>
                                        <option value="ssl" {{ ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                                        <option value="" {{ ($settings['smtp_encryption'] ?? 'tls') === '' ? 'selected' : '' }}>Sin encriptación</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Usuario SMTP</label>
                                    <input type="text" name="smtp_username" class="form-control"
                                           value="{{ $settings['smtp_username'] ?? '' }}"
                                           placeholder="tu-email@gmail.com" autocomplete="off"
                                           {{ $smtpUseGlobal ? 'disabled' : '' }}>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contraseña SMTP</label>
                                    <input type="password" name="smtp_password" class="form-control"
                                           placeholder="Dejar vacío para mantener actual" autocomplete="new-password"
                                           {{ $smtpUseGlobal ? 'disabled' : '' }}>
                                    <small class="text-muted">
                                        {{ !empty($settings['smtp_password']) ? 'Contraseña configurada (dejar vacío para mantener)' : 'No configurada' }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remitente -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Remitente por defecto</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email del remitente</label>
                                    <input type="email" name="mail_from_address" class="form-control"
                                           value="{{ $settings['mail_from_address'] ?? '' }}"
                                           placeholder="noreply@tudominio.com"
                                           {{ $smtpUseGlobal ? 'disabled' : '' }}>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nombre del remitente</label>
                                    <input type="text" name="mail_from_name" class="form-control"
                                           value="{{ $settings['mail_from_name'] ?? '' }}"
                                           placeholder="{{ $settings['site_name'] ?? 'Mi Sitio' }}"
                                           {{ $smtpUseGlobal ? 'disabled' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Guardar configuración
                        </button>
                    </div>
                </div>

                <!-- Sidebar info -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Proveedores populares</h6>
                        </div>
                        <div class="card-body">
                            <h6 class="small fw-bold"><i class="bi bi-google me-1"></i> Gmail</h6>
                            <small class="text-muted d-block mb-3">
                                Host: <code>smtp.gmail.com</code><br>
                                Puerto: <code>587</code> (TLS)<br>
                                Requiere App Password
                            </small>
                            <h6 class="small fw-bold"><i class="bi bi-microsoft me-1"></i> Outlook / Office 365</h6>
                            <small class="text-muted d-block mb-3">
                                Host: <code>smtp.office365.com</code><br>
                                Puerto: <code>587</code> (TLS)
                            </small>
                            <h6 class="small fw-bold">IONOS</h6>
                            <small class="text-muted d-block mb-3">
                                Host: <code>smtp.ionos.es</code><br>
                                Puerto: <code>587</code> (TLS)
                            </small>
                            <h6 class="small fw-bold">Mailgun / SendGrid</h6>
                            <small class="text-muted d-block">
                                Consulta la documentación de tu proveedor para obtener las credenciales SMTP.
                            </small>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-warning bg-opacity-25">
                            <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Nota</h6>
                        </div>
                        <div class="card-body">
                            <small class="text-muted">
                                Esta configuración se usará para enviar emails desde tu sitio (recuperación de contraseña, notificaciones, etc.).<br><br>
                                Si no configuras SMTP propio, el sistema usará el servidor de correo global.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
