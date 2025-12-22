@extends('Customer.layout')

@section('styles')
<style>
    .admin-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }
    .admin-card:hover {
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    .admin-card .domain-name {
        font-size: 1.2rem;
        font-weight: 600;
        color: #667eea;
        margin-bottom: 5px;
    }
    .admin-card .tenant-name {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 15px;
    }
    .credential-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .credential-row:last-child {
        border-bottom: none;
    }
    .credential-label {
        color: #6c757d;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .credential-value {
        font-family: monospace;
        background: #f8f9fa;
        padding: 5px 12px;
        border-radius: 5px;
        font-size: 0.95rem;
    }
    .credential-value.password {
        color: #28a745;
        font-weight: 500;
    }
    .btn-action {
        padding: 5px 12px;
        font-size: 0.85rem;
        border-radius: 20px;
    }
    .info-box {
        background: #e3f8fc;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .warning-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-badge.active { background: #d4edda; color: #155724; }
    .status-badge.inactive { background: #f8d7da; color: #721c24; }
    .copy-btn {
        background: transparent;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 0 5px;
    }
    .copy-btn:hover {
        color: #667eea;
    }
    .password-display {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .back-link {
        color: #6c757d;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        margin-bottom: 20px;
    }
    .back-link:hover {
        color: #667eea;
    }
    .back-link i {
        margin-right: 5px;
    }
</style>
@endsection

@section('content')
<div class="container py-4">
    <a href="/customer/dashboard" class="back-link">
        <i class="fas fa-arrow-left"></i> Volver al Dashboard
    </a>

    <h2 class="mb-4"><i class="fas fa-user-shield"></i> Gestionar Accesos de Admin</h2>

    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        Cada sitio web tiene sus propias credenciales de administrador. Desde aqui puedes ver y cambiar
        el email y password de acceso al panel de administracion (<code>/admin</code>) de cada uno de tus sitios.
    </div>

    @if(empty($tenants))
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        No tienes sitios web creados todavia.
        <a href="/customer/request-free-subdomain" class="alert-link">Solicita tu primer subdominio FREE</a>.
    </div>
    @else

    @foreach($tenants as $tenant)
    <div class="admin-card" id="tenant-{{ $tenant['tenant_id'] }}">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <div class="domain-name">
                    <i class="fas fa-globe"></i> {{ $tenant['domain'] }}
                </div>
                <div class="tenant-name">{{ $tenant['tenant_name'] ?? 'Sitio Web' }}</div>
            </div>
            <div>
                <span class="status-badge {{ $tenant['tenant_status'] === 'active' ? 'active' : 'inactive' }}">
                    {{ $tenant['tenant_status'] === 'active' ? 'Activo' : ucfirst($tenant['tenant_status']) }}
                </span>
                <span class="badge bg-light text-dark ms-2">{{ ucfirst($tenant['plan'] ?? 'free') }}</span>
            </div>
        </div>

        <div class="credential-row">
            <div>
                <span class="credential-label">Email de Admin</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="credential-value" id="email-{{ $tenant['tenant_id'] }}">{{ $tenant['admin_email'] ?? 'No disponible' }}</span>
                @if($tenant['admin_email'])
                <button class="copy-btn" onclick="copyToClipboard('{{ $tenant['admin_email'] }}')" title="Copiar">
                    <i class="fas fa-copy"></i>
                </button>
                @endif
                <button class="btn btn-outline-primary btn-action" onclick="openChangeEmailModal({{ $tenant['tenant_id'] }}, '{{ $tenant['admin_email'] ?? '' }}')">
                    <i class="fas fa-edit"></i> Cambiar
                </button>
            </div>
        </div>

        <div class="credential-row">
            <div>
                <span class="credential-label">Password</span>
            </div>
            <div class="password-display">
                @if($tenant['initial_password'] && !$tenant['password_changed'])
                <span class="credential-value password" id="password-{{ $tenant['tenant_id'] }}">{{ $tenant['initial_password'] }}</span>
                <button class="copy-btn" onclick="copyToClipboard('{{ $tenant['initial_password'] }}')" title="Copiar">
                    <i class="fas fa-copy"></i>
                </button>
                <span class="badge bg-warning text-dark">Sin cambiar</span>
                @else
                <span class="credential-value">********</span>
                <span class="badge bg-success">Personalizado</span>
                @endif
                <button class="btn btn-outline-secondary btn-action" onclick="openChangePasswordModal({{ $tenant['tenant_id'] }})">
                    <i class="fas fa-key"></i> Cambiar
                </button>
                <button class="btn btn-outline-warning btn-action" onclick="regeneratePassword({{ $tenant['tenant_id'] }})">
                    <i class="fas fa-sync-alt"></i> Regenerar
                </button>
            </div>
        </div>

        <div class="mt-3 pt-3 border-top">
            <a href="https://{{ $tenant['domain'] }}/admin" class="btn btn-primary btn-sm" target="_blank">
                <i class="fas fa-external-link-alt"></i> Ir al Panel Admin
            </a>
            @if($tenant['initial_password'] && !$tenant['password_changed'])
            <small class="text-muted ms-3">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                Se recomienda cambiar la contrasena inicial
            </small>
            @endif
        </div>
    </div>
    @endforeach

    @endif
</div>

<!-- Modal Cambiar Email -->
<div class="modal fade" id="changeEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-envelope"></i> Cambiar Email de Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="email-tenant-id">
                <div class="mb-3">
                    <label class="form-label">Email Actual</label>
                    <input type="text" class="form-control" id="current-email-display" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nuevo Email *</label>
                    <input type="email" class="form-control" id="new-email" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="changeEmail()">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cambiar Password -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key"></i> Cambiar Password de Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="password-tenant-id">
                <div class="mb-3">
                    <label class="form-label">Nueva Password *</label>
                    <input type="password" class="form-control" id="new-password" minlength="8" required>
                    <small class="text-muted">Minimo 8 caracteres</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar Password *</label>
                    <input type="password" class="form-control" id="confirm-password" minlength="8" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="changePassword()">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Mostrar Password Regenerado -->
<div class="modal fade" id="newPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Nueva Password Generada</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Tu nueva password es:</p>
                <div class="bg-light p-3 rounded mb-3">
                    <code class="fs-4" id="generated-password"></code>
                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyGeneratedPassword()">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Importante:</strong> Guarda esta password en un lugar seguro.
                    No podras verla de nuevo despues de cerrar este dialogo.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = '{{ $csrf_token }}';

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Mostrar feedback
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show" role="alert">
                <div class="toast-body bg-success text-white rounded">
                    <i class="fas fa-check"></i> Copiado al portapapeles
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    });
}

function openChangeEmailModal(tenantId, currentEmail) {
    document.getElementById('email-tenant-id').value = tenantId;
    document.getElementById('current-email-display').value = currentEmail;
    document.getElementById('new-email').value = '';
    new bootstrap.Modal(document.getElementById('changeEmailModal')).show();
}

function openChangePasswordModal(tenantId) {
    document.getElementById('password-tenant-id').value = tenantId;
    document.getElementById('new-password').value = '';
    document.getElementById('confirm-password').value = '';
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
}

async function changeEmail() {
    const tenantId = document.getElementById('email-tenant-id').value;
    const newEmail = document.getElementById('new-email').value;

    if (!newEmail) {
        alert('Ingresa el nuevo email');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('email', newEmail);

        const response = await fetch(`/customer/tenant/${tenantId}/admin/email`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById(`email-${tenantId}`).textContent = data.new_email;
            bootstrap.Modal.getInstance(document.getElementById('changeEmailModal')).hide();
            alert('Email actualizado correctamente');
        } else {
            alert(data.error || 'Error al actualizar');
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexion');
    }
}

async function changePassword() {
    const tenantId = document.getElementById('password-tenant-id').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;

    if (newPassword.length < 8) {
        alert('La password debe tener al menos 8 caracteres');
        return;
    }

    if (newPassword !== confirmPassword) {
        alert('Las passwords no coinciden');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('new_password', newPassword);
        formData.append('confirm_password', confirmPassword);

        const response = await fetch(`/customer/tenant/${tenantId}/admin/password`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
            alert('Password actualizada correctamente');
            location.reload();
        } else {
            alert(data.error || 'Error al actualizar');
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexion');
    }
}

async function regeneratePassword(tenantId) {
    if (!confirm('Se generara una nueva password aleatoria. Continuar?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);

        const response = await fetch(`/customer/tenant/${tenantId}/admin/regenerate`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('generated-password').textContent = data.new_password;
            new bootstrap.Modal(document.getElementById('newPasswordModal')).show();

            // Actualizar UI
            const passwordEl = document.getElementById(`password-${tenantId}`);
            if (passwordEl) {
                passwordEl.textContent = data.new_password;
            }
        } else {
            alert(data.error || 'Error al regenerar');
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexion');
    }
}

function copyGeneratedPassword() {
    const password = document.getElementById('generated-password').textContent;
    copyToClipboard(password);
}
</script>
@endsection
