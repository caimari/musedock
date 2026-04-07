@extends('Customer.layout')

@section('panel_content')
<style>
    .cp-profile-wrap { }
    .cp-profile-wrap h2 {
        font-size: 1.1rem; font-weight: 700; color: #243141;
        margin: 0 0 16px 0;
    }
    .cp-grid {
        display: grid;
        grid-template-columns: 1fr 220px;
        gap: 18px;
        align-items: start;
    }
    @media (max-width: 700px) {
        .cp-grid { grid-template-columns: 1fr; }
        .cp-sidebar { order: -1; }
    }

    /* Cards */
    .cp-card {
        background: #fff;
        border: 1px solid #edf0f5;
        border-radius: 10px;
        padding: 18px 20px;
        margin-bottom: 16px;
    }
    .cp-card-title {
        font-size: 1.1rem; font-weight: 600; color: #4e73df;
        margin: 0 0 14px 0;
    }
    .cp-card-title i { margin-right: 6px; }

    /* Form rows */
    .cp-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 0;
    }
    @media (max-width: 480px) {
        .cp-form-row { grid-template-columns: 1fr; }
    }
    .cp-field { margin-bottom: 12px; }
    .cp-field-full { margin-bottom: 12px; grid-column: 1 / -1; }
    .cp-label {
        display: block;
        font-size: 0.82rem; font-weight: 500; color: #243141;
        margin-bottom: 5px;
    }
    .cp-input {
        width: 100%;
        font-size: 0.88rem;
        padding: 8px 12px;
        border: 1px solid #dde1e7;
        border-radius: 7px;
        color: #243141;
        background: #fff;
        outline: none;
        transition: border-color 0.15s;
        box-sizing: border-box;
    }
    .cp-input:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 2px rgba(78,115,223,0.13);
    }
    .cp-input:disabled, .cp-input[disabled] {
        background: #f7f8fa;
        color: #8a94a6;
        cursor: not-allowed;
    }
    .cp-hint {
        font-size: 0.76rem; color: #8a94a6;
        margin-top: 3px;
    }

    /* Buttons */
    .cp-btn {
        display: inline-flex; align-items: center;
        font-size: 0.85rem; font-weight: 600;
        padding: 9px 22px;
        border: none; border-radius: 7px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .cp-btn i { margin-right: 6px; }
    .cp-btn-primary {
        background: linear-gradient(135deg, #4e73df, #3d5fc4);
        color: #fff;
    }
    .cp-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(78,115,223,0.3);
    }
    .cp-btn-danger-outline {
        background: transparent;
        color: #dc3545;
        border: 1px solid #dc3545;
        width: 100%;
        justify-content: center;
    }
    .cp-btn-danger-outline:hover {
        background: #dc3545;
        color: #fff;
    }
    .cp-btn-danger-outline:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: transparent;
        color: #dc3545;
    }
    .cp-btn-warning {
        background: #f0ad4e;
        color: #fff;
        font-size: 0.8rem;
        padding: 6px 14px;
    }
    .cp-btn-warning:hover {
        background: #ec971f;
    }

    /* Sidebar stats */
    .cp-stat-item { margin-bottom: 12px; }
    .cp-stat-label {
        font-size: 0.76rem; color: #8a94a6;
        margin-bottom: 2px;
    }
    .cp-stat-value {
        font-size: 1.2rem; font-weight: 700;
    }
    .cp-stat-primary { color: #4e73df; }
    .cp-stat-green { color: #28a745; }
    .cp-stat-muted { font-size: 0.85rem; color: #8a94a6; font-weight: 400; }

    /* Status badge */
    .cp-badge {
        display: inline-flex; align-items: center;
        font-size: 0.8rem; font-weight: 600;
        padding: 7px 14px;
        border-radius: 8px;
    }
    .cp-badge i { margin-right: 6px; }
    .cp-badge-green {
        background: #e6f9ed; color: #28a745;
    }
    .cp-badge-yellow {
        background: #fff8e1; color: #b8860b;
    }

    /* Danger zone card */
    .cp-card-danger {
        border-color: #dc3545;
    }
    .cp-card-danger .cp-card-title { color: #dc3545; }

    /* Warning box */
    .cp-warning-box {
        background: #fff8e1;
        border: 1px solid #ffe082;
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 0.82rem;
        color: #7a6c00;
        margin-bottom: 12px;
    }
    .cp-warning-box i { margin-right: 6px; }
    .cp-warning-box strong { color: #665800; }
    .cp-warning-box ul {
        margin: 6px 0 6px 18px;
        padding: 0;
    }
    .cp-warning-box li { margin-bottom: 3px; }

    .cp-text-muted { font-size: 0.78rem; color: #8a94a6; text-align: center; margin-top: 8px; }
    .cp-text-muted a { color: #4e73df; text-decoration: none; }
    .cp-text-muted a:hover { text-decoration: underline; }

    .cp-verification-block {
        margin-bottom: 10px;
    }
    .cp-verification-block p {
        font-size: 0.8rem; color: #7a6c00;
        margin: 6px 0 8px 0;
    }
</style>

<div class="cp-profile-wrap">
    <h2><i class="bi bi-person-circle" style="margin-right:6px"></i> Mi Perfil</h2>

    <div class="cp-grid">
        {{-- Left column: forms --}}
        <div>
            {{-- Personal info --}}
            <div class="cp-card">
                <div class="cp-card-title"><i class="bi bi-person"></i> Informacion Personal</div>
                <form id="profileForm">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">
                    <div class="cp-form-row">
                        <div class="cp-field">
                            <label class="cp-label" for="name">Nombre completo</label>
                            <input type="text" class="cp-input" id="name" name="name" value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required>
                        </div>
                        <div class="cp-field">
                            <label class="cp-label" for="phone">Telefono</label>
                            <input type="tel" class="cp-input" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="cp-field">
                        <label class="cp-label" for="email">Email</label>
                        <input type="email" class="cp-input" id="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" disabled>
                        <div class="cp-hint">El email no se puede cambiar</div>
                    </div>
                    <div style="margin-top:4px">
                        <button type="submit" class="cp-btn cp-btn-primary"><i class="bi bi-check-circle"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>

            {{-- Change password --}}
            <div class="cp-card">
                <div class="cp-card-title"><i class="bi bi-shield-lock"></i> Cambiar Contrasena</div>
                <form id="passwordForm">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">
                    <div class="cp-field">
                        <label class="cp-label" for="current_password">Contrasena actual</label>
                        <input type="password" class="cp-input" id="current_password" name="current_password" required>
                    </div>
                    <div class="cp-form-row">
                        <div class="cp-field">
                            <label class="cp-label" for="new_password">Nueva contrasena</label>
                            <input type="password" class="cp-input" id="new_password" name="new_password" required>
                            <div class="cp-hint">Minimo 8 caracteres</div>
                        </div>
                        <div class="cp-field">
                            <label class="cp-label" for="confirm_password">Confirmar nueva</label>
                            <input type="password" class="cp-input" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div style="margin-top:4px">
                        <button type="submit" class="cp-btn cp-btn-primary"><i class="bi bi-key"></i> Cambiar Contrasena</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right column: sidebar --}}
        <div class="cp-sidebar">
            {{-- Account stats --}}
            <div class="cp-card">
                <div class="cp-card-title"><i class="bi bi-graph-up"></i> Estadisticas</div>
                <div class="cp-stat-item">
                    <div class="cp-stat-label">Sitios totales</div>
                    <div class="cp-stat-value cp-stat-primary"><?= $stats['total_tenants'] ?? 0 ?></div>
                </div>
                <div class="cp-stat-item">
                    <div class="cp-stat-label">Sitios activos</div>
                    <div class="cp-stat-value cp-stat-green"><?= $stats['active_tenants'] ?? 0 ?></div>
                </div>
                <div class="cp-stat-item" style="margin-bottom:0">
                    <div class="cp-stat-label">Miembro desde</div>
                    <div class="cp-stat-muted"><?= isset($customer['created_at']) ? date('d/m/Y', strtotime($customer['created_at'])) : 'N/A' ?></div>
                </div>
            </div>

            {{-- Account status --}}
            <div class="cp-card">
                <div class="cp-card-title"><i class="bi bi-info-circle"></i> Estado de Cuenta</div>
                <?php if (($customer['status'] ?? '') === 'active'): ?>
                    <div class="cp-badge cp-badge-green"><i class="bi bi-check-circle"></i> Cuenta activa</div>
                <?php elseif (($customer['status'] ?? '') === 'pending_verification'): ?>
                    <div class="cp-badge cp-badge-yellow" style="margin-bottom:10px"><i class="bi bi-exclamation-triangle"></i> Pendiente de verificacion</div>
                    <div class="cp-verification-block">
                        <p>Por favor verifica tu correo electronico para activar tu cuenta.</p>
                        <button type="button" class="cp-btn cp-btn-warning" onclick="resendVerificationEmail()"><i class="bi bi-envelope"></i> Reenviar Email</button>
                    </div>
                <?php else: ?>
                    <div class="cp-badge cp-badge-yellow"><i class="bi bi-exclamation-triangle"></i> <?= ucfirst(str_replace('_', ' ', $customer['status'] ?? 'pendiente')) ?></div>
                <?php endif; ?>
            </div>

            {{-- Danger zone --}}
            <div class="cp-card cp-card-danger">
                <div class="cp-card-title"><i class="bi bi-exclamation-triangle-fill"></i> Zona de Peligro</div>
                <?php
                $totalTenants = (int)($stats['total_tenants'] ?? 0);
                $blockingDomainOrders = (int)($domain_orders_blocking_count ?? 0);
                $blockingDomainTransfers = (int)($domain_transfers_blocking_count ?? 0);
                $hasBlockingResources = ($totalTenants > 0) || ($blockingDomainOrders > 0) || ($blockingDomainTransfers > 0);
                ?>
                <?php if ($hasBlockingResources): ?>
                    <div class="cp-warning-box">
                        <i class="bi bi-info-circle"></i><strong>No puedes eliminar tu cuenta</strong>
                        <p style="margin-top:6px; margin-bottom:4px;">Tu cuenta tiene recursos activos:</p>
                        <ul>
                            <?php if ($totalTenants > 0): ?>
                            <li><?= htmlspecialchars((string)$totalTenants) ?> sitio(s)/subdominio(s)</li>
                            <?php endif; ?>
                            <?php if ($blockingDomainOrders > 0): ?>
                            <li><?= htmlspecialchars((string)$blockingDomainOrders) ?> dominio(s) registrado(s)</li>
                            <?php endif; ?>
                            <?php if ($blockingDomainTransfers > 0): ?>
                            <li><?= htmlspecialchars((string)$blockingDomainTransfers) ?> transferencia(s) en proceso</li>
                            <?php endif; ?>
                        </ul>
                        <p style="margin:0; font-size:0.76rem;">Elimina estos recursos o contacta soporte.</p>
                    </div>
                    <button type="button" class="cp-btn cp-btn-danger-outline" disabled><i class="bi bi-trash"></i> Eliminar mi cuenta</button>
                    <div class="cp-text-muted"><a href="mailto:soporte@musedock.com">Contactar soporte</a></div>
                <?php else: ?>
                    <div class="cp-hint" style="margin-bottom:12px">Al eliminar tu cuenta, todos tus datos seran eliminados permanentemente. Esta accion no se puede deshacer.</div>
                    <button type="button" class="cp-btn cp-btn-danger-outline" onclick="confirmDeleteAccount()"><i class="bi bi-trash"></i> Eliminar mi cuenta</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var csrfToken = <?= json_encode($csrf_token ?? csrf_token()) ?>;

    // Profile form
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);

        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

        fetch('/customer/profile/update', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Perfil actualizado!', text: 'Tus cambios han sido guardados.', confirmButtonColor: '#4e73df' })
                        .then(function() { window.location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error al guardar los cambios', confirmButtonColor: '#4e73df' });
                }
            })
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red. Por favor intenta de nuevo.', confirmButtonColor: '#4e73df' });
            });
    });

    // Password form
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var np = document.getElementById('new_password').value;
        var cp = document.getElementById('confirm_password').value;

        if (np !== cp) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Las contrasenas no coinciden', confirmButtonColor: '#4e73df' });
            return;
        }
        if (np.length < 8) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'La contrasena debe tener al menos 8 caracteres', confirmButtonColor: '#4e73df' });
            return;
        }

        var formData = new FormData(this);
        var form = this;

        Swal.fire({ title: 'Cambiando contrasena...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

        fetch('/customer/profile/change-password', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Contrasena cambiada!', text: 'Tu contrasena ha sido actualizada exitosamente.', confirmButtonColor: '#4e73df' })
                        .then(function() { form.reset(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error al cambiar la contrasena', confirmButtonColor: '#4e73df' });
                }
            })
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red. Por favor intenta de nuevo.', confirmButtonColor: '#4e73df' });
            });
    });

    // Resend verification email
    window.resendVerificationEmail = function() {
        Swal.fire({
            title: 'Reenviar email de verificacion?',
            text: 'Te enviaremos un nuevo correo con el enlace de verificacion.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4e73df',
            cancelButtonColor: '#8a94a6',
            confirmButtonText: 'Si, reenviar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

                fetch('/customer/resend-verification', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Email enviado!', text: 'Revisa tu correo electronico y haz clic en el enlace de verificacion.', confirmButtonColor: '#4e73df' });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo enviar el email de verificacion.', confirmButtonColor: '#4e73df' });
                    }
                })
                .catch(function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red. Por favor intenta de nuevo.', confirmButtonColor: '#4e73df' });
                });
            }
        });
    };

    // Delete account
    window.confirmDeleteAccount = function() {
        Swal.fire({
            title: 'Eliminar tu cuenta?',
            html:
                '<div style="text-align:left">' +
                    '<p style="color:#dc3545;margin-bottom:10px"><i class="bi bi-exclamation-triangle-fill" style="margin-right:6px"></i><strong>Esta accion es irreversible</strong></p>' +
                    '<p style="margin-bottom:8px;font-size:0.88rem">Se eliminaran permanentemente:</p>' +
                    '<ul style="margin:0 0 12px 18px;padding:0;font-size:0.85rem">' +
                        '<li>Tu cuenta y datos personales</li>' +
                        '<li>Historial de actividad</li>' +
                        '<li>Configuraciones guardadas</li>' +
                    '</ul>' +
                    '<p style="margin-bottom:6px;font-size:0.88rem;font-weight:600">Confirma tu contrasena para continuar:</p>' +
                    '<input type="password" id="delete-account-password" style="width:100%;font-size:0.88rem;padding:8px 12px;border:1px solid #dde1e7;border-radius:7px;outline:none;box-sizing:border-box" placeholder="Tu contrasena actual">' +
                '</div>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#8a94a6',
            confirmButtonText: '<i class="bi bi-trash" style="margin-right:6px"></i>Eliminar mi cuenta',
            cancelButtonText: 'Cancelar',
            focusCancel: true,
            preConfirm: function() {
                var pw = document.getElementById('delete-account-password').value;
                if (!pw) {
                    Swal.showValidationMessage('Debes introducir tu contrasena');
                    return false;
                }
                return pw;
            }
        }).then(function(result) {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    title: 'Eliminando cuenta...',
                    html: 'Por favor espera mientras procesamos tu solicitud...',
                    allowOutsideClick: false,
                    didOpen: function() { Swal.showLoading(); }
                });

                fetch('/customer/delete-account', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_csrf_token=' + encodeURIComponent(csrfToken) + '&password=' + encodeURIComponent(result.value)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success', title: 'Cuenta eliminada',
                            text: 'Tu cuenta ha sido eliminada exitosamente. Gracias por usar MuseDock.',
                            confirmButtonColor: '#4e73df', allowOutsideClick: false
                        }).then(function() { window.location.href = '/'; });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo eliminar la cuenta. Verifica tu contrasena.', confirmButtonColor: '#4e73df' });
                    }
                })
                .catch(function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red. Por favor intenta de nuevo.', confirmButtonColor: '#4e73df' });
                });
            }
        });
    };
})();
</script>
@endsection
