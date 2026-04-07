@extends('Customer.layout')

@section('panel_content')
<style>
    .ta-back-link {
        display: inline-flex;
        align-items: center;
        color: #8a94a6;
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 500;
        margin-bottom: 16px;
        transition: color 0.15s;
    }
    .ta-back-link:hover { color: #4e73df; }
    .ta-back-link i { margin-right: 6px; }

    .ta-page-header {
        margin-bottom: 18px;
    }
    .ta-page-header h2 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #243141;
        margin: 0 0 4px 0;
    }
    .ta-page-header h2 i { margin-right: 6px; color: #4e73df; }
    .ta-page-header p {
        font-size: 0.82rem;
        color: #8a94a6;
        margin: 0;
    }

    .ta-info-box {
        background: #eef4ff;
        border-left: 4px solid #4e73df;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 0.84rem;
        color: #243141;
        line-height: 1.5;
    }
    .ta-info-box i { margin-right: 6px; color: #4e73df; }
    .ta-info-box code {
        background: #dce4f8;
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 0.82rem;
        color: #243141;
    }

    .ta-empty {
        text-align: center;
        padding: 40px 20px;
        background: #f8f9fb;
        border-radius: 10px;
        color: #8a94a6;
        font-size: 0.86rem;
    }
    .ta-empty i { font-size: 2rem; display: block; margin-bottom: 10px; color: #c5cdd8; }
    .ta-empty a {
        color: #4e73df;
        text-decoration: none;
        font-weight: 600;
    }
    .ta-empty a:hover { text-decoration: underline; }

    .ta-card {
        background: #fff;
        border: 1px solid #edf0f5;
        border-radius: 10px;
        padding: 18px 20px;
        margin-bottom: 14px;
        transition: box-shadow 0.2s;
    }
    .ta-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }

    .ta-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .ta-domain-link {
        font-size: 1.1rem;
        font-weight: 700;
        color: #4e73df;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }
    .ta-domain-link:hover { color: #3656b8; text-decoration: underline; }
    .ta-domain-link i { margin-right: 6px; font-size: 0.95rem; }

    .ta-tenant-name {
        font-size: 0.82rem;
        color: #8a94a6;
        margin-top: 2px;
    }

    .ta-badges { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .ta-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }
    .ta-badge-active { background: #d6f5dc; color: #1a6b2a; }
    .ta-badge-inactive { background: #fde0e0; color: #9b2c2c; }
    .ta-badge-plan { background: #f0f2f5; color: #243141; }

    .ta-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-top: 1px solid #f3f4f6;
        flex-wrap: wrap;
        gap: 8px;
    }
    .ta-row-label {
        font-size: 0.76rem;
        font-weight: 600;
        color: #8a94a6;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .ta-row-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .ta-value {
        font-family: 'SFMono-Regular', 'Consolas', 'Menlo', monospace;
        background: #f5f6f8;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.84rem;
        color: #243141;
    }
    .ta-value-pw {
        color: #28a745;
        font-weight: 600;
    }

    .ta-copy-btn {
        background: none;
        border: none;
        color: #8a94a6;
        cursor: pointer;
        padding: 4px 6px;
        border-radius: 4px;
        font-size: 0.82rem;
        transition: color 0.15s, background 0.15s;
    }
    .ta-copy-btn:hover { color: #4e73df; background: #eef4ff; }

    .ta-pw-indicator {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .ta-pw-unchanged { background: #fff3cd; color: #856404; }
    .ta-pw-changed { background: #d6f5dc; color: #1a6b2a; }

    .ta-btn {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 500;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.15s;
        text-decoration: none;
        line-height: 1.4;
    }
    .ta-btn i { margin-right: 6px; font-size: 0.78rem; }
    .ta-btn-primary {
        background: #4e73df;
        color: #fff;
        border-color: #4e73df;
    }
    .ta-btn-primary:hover { background: #3656b8; border-color: #3656b8; }
    .ta-btn-outline {
        background: #fff;
        color: #4e73df;
        border-color: #d0d7e8;
    }
    .ta-btn-outline:hover { background: #eef4ff; border-color: #4e73df; }
    .ta-btn-warning {
        background: #fff;
        color: #d48806;
        border-color: #eed9a4;
    }
    .ta-btn-warning:hover { background: #fffbf0; border-color: #d48806; }

    .ta-card-footer {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-top: 12px;
        border-top: 1px solid #f3f4f6;
        margin-top: 4px;
        flex-wrap: wrap;
    }
    .ta-card-footer .ta-hint {
        font-size: 0.78rem;
        color: #d48806;
        display: inline-flex;
        align-items: center;
    }
    .ta-card-footer .ta-hint i { margin-right: 6px; }

    @media (max-width: 600px) {
        .ta-card-top { flex-direction: column; }
        .ta-row { flex-direction: column; align-items: flex-start; }
        .ta-row-right { width: 100%; justify-content: flex-start; margin-top: 6px; }
        .ta-card-footer { flex-direction: column; align-items: flex-start; }
    }
</style>

<a href="/customer/dashboard" class="ta-back-link">
    <i class="bi bi-arrow-left"></i> Volver al Dashboard
</a>

<div class="ta-page-header">
    <h2><i class="bi bi-shield-lock"></i> Gestionar Accesos de Admin</h2>
    <p>Administra las credenciales de acceso al panel de cada uno de tus sitios web.</p>
</div>

<div class="ta-info-box">
    <i class="bi bi-info-circle-fill"></i>
    Cada sitio web tiene sus propias credenciales de administrador. Desde aqui puedes ver y cambiar
    el email y password de acceso al panel de administracion (<code>/admin</code>) de cada uno de tus sitios.
</div>

@if(empty($tenants))
<div class="ta-empty">
    <i class="bi bi-inbox"></i>
    No tienes sitios web creados todavia.<br>
    <a href="/customer/request-free-subdomain">Solicita tu primer subdominio FREE</a>.
</div>
@else

@foreach($tenants as $tenant)
<div class="ta-card" id="tenant-{{ $tenant['tenant_id'] }}">
    <div class="ta-card-top">
        <div>
            <a href="https://{{ $tenant['domain'] }}" target="_blank" class="ta-domain-link">
                <i class="bi bi-globe2"></i> {{ $tenant['domain'] }}
            </a>
            <div class="ta-tenant-name">{{ $tenant['tenant_name'] ?? 'Sitio Web' }}</div>
        </div>
        <div class="ta-badges">
            <span class="ta-badge {{ $tenant['tenant_status'] === 'active' ? 'ta-badge-active' : 'ta-badge-inactive' }}">
                {{ $tenant['tenant_status'] === 'active' ? 'Activo' : ucfirst($tenant['tenant_status']) }}
            </span>
            <span class="ta-badge ta-badge-plan">{{ ucfirst($tenant['plan'] ?? 'free') }}</span>
        </div>
    </div>

    {{-- Email row --}}
    <div class="ta-row">
        <span class="ta-row-label">Email de Admin</span>
        <div class="ta-row-right">
            <span class="ta-value" id="email-{{ $tenant['tenant_id'] }}">{{ $tenant['admin_email'] ?? 'No disponible' }}</span>
            @if($tenant['admin_email'])
            <button class="ta-copy-btn" onclick="copyToClipboard('{{ $tenant['admin_email'] }}')" title="Copiar email">
                <i class="bi bi-clipboard"></i>
            </button>
            @endif
            <button class="ta-btn ta-btn-outline" onclick="changeEmail({{ $tenant['tenant_id'] }}, '{{ addslashes($tenant['admin_email'] ?? '') }}')">
                <i class="bi bi-pencil"></i> Cambiar Email
            </button>
        </div>
    </div>

    {{-- Password row --}}
    <div class="ta-row">
        <span class="ta-row-label">Password</span>
        <div class="ta-row-right">
            @if($tenant['initial_password'] && !$tenant['password_changed'])
            <span class="ta-value ta-value-pw" id="password-{{ $tenant['tenant_id'] }}">{{ $tenant['initial_password'] }}</span>
            <button class="ta-copy-btn" onclick="copyToClipboard('{{ $tenant['initial_password'] }}')" title="Copiar password">
                <i class="bi bi-clipboard"></i>
            </button>
            <span class="ta-pw-indicator ta-pw-unchanged">Sin cambiar</span>
            @else
            <span class="ta-value">********</span>
            <span class="ta-pw-indicator ta-pw-changed">Personalizado</span>
            @endif
            <button class="ta-btn ta-btn-outline" onclick="changePassword({{ $tenant['tenant_id'] }})">
                <i class="bi bi-key"></i> Cambiar
            </button>
            <button class="ta-btn ta-btn-warning" onclick="regeneratePassword({{ $tenant['tenant_id'] }})">
                <i class="bi bi-arrow-repeat"></i> Regenerar
            </button>
        </div>
    </div>

    <div class="ta-card-footer">
        <a href="https://{{ $tenant['domain'] }}/admin" target="_blank" class="ta-btn ta-btn-primary">
            <i class="bi bi-box-arrow-up-right"></i> Ir al Panel Admin
        </a>
        @if($tenant['initial_password'] && !$tenant['password_changed'])
        <span class="ta-hint">
            <i class="bi bi-exclamation-triangle"></i> Se recomienda cambiar la contrasena inicial
        </span>
        @endif
    </div>
</div>
@endforeach

@endif

<script>
var csrfToken = '{{ $csrf_token }}';

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        Swal.fire({
            toast: true,
            position: 'bottom-end',
            icon: 'success',
            title: 'Copiado al portapapeles',
            showConfirmButton: false,
            timer: 1800,
            timerProgressBar: true
        });
    });
}

function changeEmail(tenantId, currentEmail) {
    Swal.fire({
        title: 'Cambiar Email de Admin',
        html:
            '<div style="text-align:left;font-size:0.84rem;margin-bottom:10px;color:#8a94a6;">' +
                'Email actual: <strong style="color:#243141;">' + (currentEmail || 'No disponible') + '</strong>' +
            '</div>',
        input: 'email',
        inputLabel: 'Nuevo Email',
        inputPlaceholder: 'nuevo@email.com',
        showCancelButton: true,
        confirmButtonColor: '#4e73df',
        cancelButtonColor: '#8a94a6',
        confirmButtonText: '<i class="bi bi-check-lg"></i> Guardar',
        cancelButtonText: 'Cancelar',
        inputValidator: function(value) {
            if (!value) return 'Introduce un email valido';
        },
        showLoaderOnConfirm: true,
        preConfirm: function(newEmail) {
            var formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('email', newEmail);
            return fetch('/customer/tenant/' + tenantId + '/admin/email', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Error al actualizar');
                return data;
            })
            .catch(function(err) {
                Swal.showValidationMessage(err.message || 'Error de conexion');
            });
        },
        allowOutsideClick: function() { return !Swal.isLoading(); }
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            var el = document.getElementById('email-' + tenantId);
            if (el) el.textContent = result.value.new_email;
            Swal.fire({
                icon: 'success',
                title: 'Email actualizado',
                text: 'El email de admin se ha cambiado correctamente.',
                confirmButtonColor: '#4e73df'
            });
        }
    });
}

function changePassword(tenantId) {
    Swal.fire({
        title: 'Cambiar Password de Admin',
        html:
            '<div style="margin-bottom:10px;">' +
                '<label style="display:block;font-size:0.82rem;font-weight:600;color:#243141;margin-bottom:4px;">Nueva Password</label>' +
                '<input type="password" id="swal-pw1" style="width:100%;padding:8px 12px;border:1px solid #d0d7e8;border-radius:8px;font-size:0.86rem;outline:none;" placeholder="Minimo 8 caracteres">' +
            '</div>' +
            '<div>' +
                '<label style="display:block;font-size:0.82rem;font-weight:600;color:#243141;margin-bottom:4px;">Confirmar Password</label>' +
                '<input type="password" id="swal-pw2" style="width:100%;padding:8px 12px;border:1px solid #d0d7e8;border-radius:8px;font-size:0.86rem;outline:none;" placeholder="Repite la password">' +
            '</div>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonColor: '#4e73df',
        cancelButtonColor: '#8a94a6',
        confirmButtonText: '<i class="bi bi-check-lg"></i> Guardar',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: function() {
            var pw1 = document.getElementById('swal-pw1').value;
            var pw2 = document.getElementById('swal-pw2').value;
            if (pw1.length < 8) {
                Swal.showValidationMessage('La password debe tener al menos 8 caracteres');
                return false;
            }
            if (pw1 !== pw2) {
                Swal.showValidationMessage('Las passwords no coinciden');
                return false;
            }
            var formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('new_password', pw1);
            formData.append('confirm_password', pw2);
            return fetch('/customer/tenant/' + tenantId + '/admin/password', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Error al actualizar');
                return data;
            })
            .catch(function(err) {
                Swal.showValidationMessage(err.message || 'Error de conexion');
            });
        },
        allowOutsideClick: function() { return !Swal.isLoading(); }
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            Swal.fire({
                icon: 'success',
                title: 'Password actualizada',
                text: 'La password se ha cambiado correctamente.',
                confirmButtonColor: '#4e73df'
            }).then(function() {
                location.reload();
            });
        }
    });
}

function regeneratePassword(tenantId) {
    Swal.fire({
        title: 'Regenerar Password',
        text: 'Se generara una nueva password aleatoria. La password actual dejara de funcionar.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d48806',
        cancelButtonColor: '#8a94a6',
        confirmButtonText: '<i class="bi bi-arrow-repeat"></i> Si, regenerar',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: function() {
            var formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            return fetch('/customer/tenant/' + tenantId + '/admin/regenerate', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Error al regenerar');
                return data;
            })
            .catch(function(err) {
                Swal.showValidationMessage(err.message || 'Error de conexion');
            });
        },
        allowOutsideClick: function() { return !Swal.isLoading(); }
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            var newPw = result.value.new_password;
            var pwEl = document.getElementById('password-' + tenantId);
            if (pwEl) pwEl.textContent = newPw;

            Swal.fire({
                icon: 'success',
                title: 'Nueva Password Generada',
                html:
                    '<div style="background:#f5f6f8;padding:12px 16px;border-radius:8px;margin:12px 0;display:flex;align-items:center;justify-content:center;gap:10px;">' +
                        '<code style="font-size:1.1rem;font-weight:600;color:#28a745;word-break:break-all;">' + newPw + '</code>' +
                        '<button onclick="navigator.clipboard.writeText(\'' + newPw + '\');this.innerHTML=\'<i class=bi-check2></i>\';this.style.color=\'#28a745\';" ' +
                            'style="background:none;border:1px solid #d0d7e8;border-radius:6px;padding:4px 8px;cursor:pointer;color:#8a94a6;font-size:0.82rem;" title="Copiar">' +
                            '<i class="bi bi-clipboard"></i>' +
                        '</button>' +
                    '</div>' +
                    '<div style="background:#fff3cd;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#856404;text-align:left;">' +
                        '<i class="bi bi-exclamation-triangle" style="margin-right:6px;"></i>' +
                        '<strong>Importante:</strong> Guarda esta password en un lugar seguro. No podras verla de nuevo despues de cerrar este dialogo.' +
                    '</div>',
                confirmButtonColor: '#4e73df',
                confirmButtonText: 'Entendido'
            });
        }
    });
}
</script>
@endsection
