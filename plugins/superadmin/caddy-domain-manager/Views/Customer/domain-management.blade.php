@extends('Customer.layout')

@section('styles')
<style>
    .management-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .management-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 20px 25px;
    }
    .management-card .card-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .management-card .card-body {
        padding: 25px;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        font-weight: 600;
        color: #4b5563;
        font-size: 0.95rem;
    }
    .info-value {
        color: #1f2937;
        font-size: 0.95rem;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .status-active {
        background: #d1fae5;
        color: #065f46;
    }
    .status-locked {
        background: #fef3c7;
        color: #92400e;
    }
    .status-unlocked {
        background: #dbeafe;
        color: #1e40af;
    }
    .quick-action-btn {
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s ease;
        margin-bottom: 10px;
    }
    .quick-action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .auth-code-display {
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        font-family: monospace;
        font-size: 1.1rem;
        font-weight: 600;
        color: #495057;
        margin-top: 10px;
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .3s;
        border-radius: 26px;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }
    input:checked + .toggle-slider {
        background-color: #10b981;
    }
    input:checked + .toggle-slider:before {
        transform: translateX(24px);
    }
    .renewal-select {
        min-width: 150px;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        font-size: 0.9rem;
    }
</style>
@endsection

@section('content')
<?php
    $domain = $order['full_domain'] ?? trim(($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''), '.');
    $opId = $order['openprovider_domain_id'] ?? null;

    // Domain info from OpenProvider API
    $status = $domainInfo['status'] ?? 'unknown';
    $isLocked = $domainInfo['is_locked'] ?? false;
    $autorenew = $domainInfo['autorenew'] ?? 'default';
    $isPrivateWhois = $domainInfo['is_private_whois_enabled'] ?? false;
    $expirationDate = $domainInfo['expiration_date'] ?? null;
    $renewalDate = $domainInfo['renewal_date'] ?? null;

    // Format dates
    $expirationFormatted = $expirationDate ? date('d/m/Y', strtotime($expirationDate)) : 'N/A';
    $renewalFormatted = $renewalDate ? date('d/m/Y', strtotime($renewalDate)) : 'N/A';

    // Calculate days until expiration
    $daysUntilExpiration = null;
    if ($expirationDate) {
        $now = new DateTime();
        $expiry = new DateTime($expirationDate);
        $interval = $now->diff($expiry);
        $daysUntilExpiration = $interval->days;
        if ($expiry < $now) {
            $daysUntilExpiration = -$daysUntilExpiration;
        }
    }

    // Status labels
    $statusLabels = [
        'ACT' => 'Activo',
        'active' => 'Activo',
        'FAI' => 'Error',
        'PEN' => 'Pendiente'
    ];
    $statusLabel = $statusLabels[$status] ?? ucfirst($status);
?>

<div class="row">
    <div class="col-12">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-gear-fill me-2"></i>Administrar Dominio</h4>
                <p class="text-muted mb-0">Configuración avanzada de <strong><?= htmlspecialchars($domain) ?></strong></p>
            </div>
            <a href="/customer/dashboard" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>

        <div class="row">
            <!-- Left Column: Domain Overview -->
            <div class="col-lg-6">
                <!-- Domain Information -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-info-circle me-2"></i>Información del Dominio</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Estado</span>
                            <span class="info-value">
                                <span class="status-badge status-active"><?= htmlspecialchars($statusLabel) ?></span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Fecha de Expiración</span>
                            <span class="info-value">
                                <?= htmlspecialchars($expirationFormatted) ?>
                                <?php if ($daysUntilExpiration !== null): ?>
                                    <small class="text-muted ms-2">
                                        (<?= $daysUntilExpiration > 0 ? "faltan {$daysUntilExpiration} días" : "expiró hace " . abs($daysUntilExpiration) . " días" ?>)
                                    </small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Próxima Renovación</span>
                            <span class="info-value"><?= htmlspecialchars($renewalFormatted) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Bloqueo de Transferencia</span>
                            <span class="info-value">
                                <span class="status-badge <?= $isLocked ? 'status-locked' : 'status-unlocked' ?>">
                                    <?= $isLocked ? '🔒 Bloqueado' : '🔓 Desbloqueado' ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-shield-lock me-2"></i>Seguridad</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div>
                                <span class="info-label d-block">Bloqueo de Transferencia</span>
                                <small class="text-muted">Protege contra transferencias no autorizadas</small>
                            </div>
                            <div>
                                <button class="btn btn-sm <?= $isLocked ? 'btn-danger' : 'btn-success' ?>"
                                        onclick="toggleLock('<?= $isLocked ? 'unlock' : 'lock' ?>')">
                                    <i class="bi bi-<?= $isLocked ? 'unlock' : 'lock' ?> me-1"></i>
                                    <?= $isLocked ? 'Desbloquear' : 'Bloquear' ?>
                                </button>
                            </div>
                        </div>
                        <div class="info-row">
                            <div>
                                <span class="info-label d-block">Protección WHOIS</span>
                                <small class="text-muted">Oculta tus datos de contacto en el WHOIS público</small>
                            </div>
                            <div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="whoisPrivacyToggle"
                                           <?= $isPrivateWhois ? 'checked' : '' ?>
                                           onchange="toggleWhoisPrivacy(this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Actions & Settings -->
            <div class="col-lg-6">
                <!-- Quick Actions -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-lightning-charge me-2"></i>Acciones Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-outline-primary quick-action-btn w-100" onclick="getAuthCode()">
                            <i class="bi bi-key me-2"></i>Ver Código de Autorización (Auth Code)
                        </button>
                        <button class="btn btn-outline-warning quick-action-btn w-100" onclick="regenerateAuthCode()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Regenerar Código de Autorización
                        </button>
                        <div id="authCodeDisplay" style="display: none;" class="mt-3">
                            <div class="auth-code-display" id="authCodeValue"></div>
                            <small class="text-muted d-block mt-2 text-center">
                                Usa este código para transferir tu dominio a otro registrador
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Renewal Settings -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-arrow-repeat me-2"></i>Renovación</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div>
                                <span class="info-label d-block">Auto-Renovación</span>
                                <small class="text-muted">Configura la renovación automática del dominio</small>
                            </div>
                            <div>
                                <select class="renewal-select" id="autorenewSelect" onchange="updateAutoRenew(this.value)">
                                    <option value="on" <?= $autorenew === 'on' ? 'selected' : '' ?>>Activada</option>
                                    <option value="off" <?= $autorenew === 'off' ? 'selected' : '' ?>>Desactivada</option>
                                    <option value="default" <?= $autorenew === 'default' ? 'selected' : '' ?>>Por defecto</option>
                                </select>
                            </div>
                        </div>
                        <?php if ($daysUntilExpiration !== null && $daysUntilExpiration > 0 && $daysUntilExpiration <= 30): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Atención:</strong> Tu dominio expira en <?= $daysUntilExpiration ?> días
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-compass me-2"></i>Otras Opciones</h5>
                    </div>
                    <div class="card-body">
                        <a href="/customer/domain/<?= $order['id'] ?>/dns" class="btn btn-outline-primary quick-action-btn w-100">
                            <i class="bi bi-hdd-network me-2"></i>Gestionar DNS
                        </a>
                        <a href="/customer/domain/<?= $order['id'] ?>/contacts" class="btn btn-outline-secondary quick-action-btn w-100">
                            <i class="bi bi-person-lines-fill me-2"></i>Administrar Contactos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const orderId = <?= $order['id'] ?>;
const csrfToken = '<?= $csrf_token ?>';

function toggleLock(action) {
    const actionText = action === 'lock' ? 'bloquear' : 'desbloquear';

    Swal.fire({
        title: `¿${actionText.charAt(0).toUpperCase() + actionText.slice(1)} dominio?`,
        text: action === 'lock'
            ? 'El dominio estará protegido contra transferencias no autorizadas'
            : 'El dominio podrá ser transferido a otro registrador',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'lock' ? '#10b981' : '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Sí, ${actionText}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('action', action);

            Swal.fire({
                title: 'Procesando...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/customer/domain/${orderId}/toggle-lock`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'No se pudo cambiar el estado del bloqueo', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

function getAuthCode() {
    Swal.fire({
        title: 'Obteniendo código...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/auth-code`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                document.getElementById('authCodeValue').textContent = data.auth_code;
                document.getElementById('authCodeDisplay').style.display = 'block';

                // Scroll to auth code
                document.getElementById('authCodeDisplay').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                Swal.fire('Error', data.error || 'No se pudo obtener el auth code', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function regenerateAuthCode() {
    Swal.fire({
        title: '¿Regenerar Auth Code?',
        text: 'Se generará un nuevo código de autorización. El anterior dejará de funcionar.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, regenerar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            Swal.fire({
                title: 'Generando nuevo código...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/customer/domain/${orderId}/regenerate-auth-code`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('authCodeValue').textContent = data.auth_code;
                    document.getElementById('authCodeDisplay').style.display = 'block';

                    Swal.fire({
                        icon: 'success',
                        title: data.message,
                        text: 'Nuevo código: ' + data.auth_code,
                        confirmButtonText: 'Entendido'
                    });

                    // Scroll to auth code
                    document.getElementById('authCodeDisplay').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    Swal.fire('Error', data.error || 'No se pudo regenerar el auth code', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

function updateAutoRenew(value) {
    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);
    formData.append('autorenew', value);

    Swal.fire({
        title: 'Actualizando...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/toggle-autorenew`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: data.message,
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo actualizar la auto-renovación', 'error');
            // Revert select
            document.getElementById('autorenewSelect').value = '<?= $autorenew ?>';
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Error de conexión', 'error');
        document.getElementById('autorenewSelect').value = '<?= $autorenew ?>';
    });
}

function toggleWhoisPrivacy(enabled) {
    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);
    formData.append('enabled', enabled ? '1' : '0');

    Swal.fire({
        title: 'Actualizando...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/toggle-whois-privacy`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: data.message,
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo cambiar la protección WHOIS', 'error');
            // Revert toggle
            document.getElementById('whoisPrivacyToggle').checked = <?= $isPrivateWhois ? 'true' : 'false' ?>;
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Error de conexión', 'error');
        document.getElementById('whoisPrivacyToggle').checked = <?= $isPrivateWhois ? 'true' : 'false' ?>;
    });
}
</script>
@endsection
