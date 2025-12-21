@extends('Customer.layout')

@section('styles')
<style>
    .profile-card {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .profile-card h5 {
        color: #667eea;
        margin-bottom: 20px;
        font-weight: 600;
    }
    .form-label {
        font-weight: 500;
        color: #333;
        margin-bottom: 8px;
    }
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
        padding: 12px 30px;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }
</style>
@endsection

@section('content')
<h2 class="mb-4">Mi Perfil</h2>

<div class="row">
    <div class="col-md-8">
        <!-- Informacion Personal -->
        <div class="profile-card">
            <h5><i class="bi bi-person-circle me-2"></i>Informacion Personal</h5>
            <form id="profileForm" method="POST" action="/customer/profile/update">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre completo</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" disabled>
                    <small class="text-muted">El email no se puede cambiar</small>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Telefono (opcional)</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-2"></i>Guardar Cambios
                </button>
            </form>
        </div>

        <!-- Cambiar Contrasena -->
        <div class="profile-card">
            <h5><i class="bi bi-shield-lock me-2"></i>Cambiar Contrasena</h5>
            <form id="passwordForm" method="POST" action="/customer/profile/change-password">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label for="current_password" class="form-label">Contrasena actual</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">Nueva contrasena</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <small class="text-muted">Minimo 8 caracteres</small>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmar nueva contrasena</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-key me-2"></i>Cambiar Contrasena
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Estadisticas de Cuenta -->
        <div class="profile-card">
            <h5><i class="bi bi-graph-up me-2"></i>Estadisticas</h5>
            <div class="mb-3">
                <small class="text-muted">Sitios totales</small>
                <div class="fs-4 fw-bold text-primary"><?= $stats['total_tenants'] ?? 0 ?></div>
            </div>
            <div class="mb-3">
                <small class="text-muted">Sitios activos</small>
                <div class="fs-4 fw-bold text-success"><?= $stats['active_tenants'] ?? 0 ?></div>
            </div>
            <div class="mb-3">
                <small class="text-muted">Miembro desde</small>
                <div class="text-muted"><?= isset($customer['created_at']) ? date('d/m/Y', strtotime($customer['created_at'])) : 'N/A' ?></div>
            </div>
        </div>

        <!-- Estado de la Cuenta -->
        <div class="profile-card">
            <h5><i class="bi bi-info-circle me-2"></i>Estado de Cuenta</h5>
            <?php if (($customer['status'] ?? '') === 'active'): ?>
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle me-2"></i>Cuenta activa
                </div>
            <?php elseif (($customer['status'] ?? '') === 'pending_verification'): ?>
                <div class="alert alert-warning mb-2">
                    <i class="bi bi-exclamation-triangle me-2"></i><strong>Pendiente de verificación</strong>
                    <p class="mb-2 mt-2 small">Por favor verifica tu correo electrónico para activar tu cuenta.</p>
                    <button class="btn btn-sm btn-warning" onclick="resendVerificationEmail()">
                        <i class="bi bi-envelope me-1"></i>Reenviar Email de Verificación
                    </button>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= ucfirst(str_replace('_', ' ', $customer['status'] ?? 'pendiente')) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Zona de Peligro -->
        <div class="profile-card" style="border: 1px solid #dc3545;">
            <h5 style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill me-2"></i>Zona de Peligro</h5>
            <?php
            $hasActiveSites = ($stats['total_tenants'] ?? 0) > 0;
            ?>
            <?php if ($hasActiveSites): ?>
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>No puedes eliminar tu cuenta</strong>
                    <p class="mb-0 mt-2 small">Tienes <?= $stats['total_tenants'] ?> dominio(s)/subdominio(s) activos con nosotros. Para eliminar tu cuenta, primero debes eliminar todos tus sitios o contactar con el administrador.</p>
                </div>
                <button class="btn btn-outline-danger w-100" disabled>
                    <i class="bi bi-trash me-2"></i>Eliminar mi cuenta
                </button>
                <div class="mt-2 text-center">
                    <small class="text-muted">
                        <a href="/customer/support" class="text-decoration-none">Contactar soporte</a> para solicitar eliminacion
                    </small>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-3">
                    Al eliminar tu cuenta, todos tus datos seran eliminados permanentemente. Esta accion no se puede deshacer.
                </p>
                <button class="btn btn-outline-danger w-100" onclick="confirmDeleteAccount()">
                    <i class="bi bi-trash me-2"></i>Eliminar mi cuenta
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Guardar perfil
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    Swal.fire({
        title: 'Guardando...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('/customer/profile/update', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Perfil actualizado!',
                text: 'Tus cambios han sido guardados.',
                confirmButtonColor: '#667eea'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Error al guardar los cambios',
                confirmButtonColor: '#667eea'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de red. Por favor intenta de nuevo.',
            confirmButtonColor: '#667eea'
        });
    });
});

// Cambiar contrasena
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (newPassword !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Las contrasenas no coinciden',
            confirmButtonColor: '#667eea'
        });
        return;
    }

    if (newPassword.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'La contrasena debe tener al menos 8 caracteres',
            confirmButtonColor: '#667eea'
        });
        return;
    }

    const formData = new FormData(this);

    Swal.fire({
        title: 'Cambiando contrasena...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('/customer/profile/change-password', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Contrasena cambiada!',
                text: 'Tu contrasena ha sido actualizada exitosamente.',
                confirmButtonColor: '#667eea'
            }).then(() => {
                this.reset();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Error al cambiar la contrasena',
                confirmButtonColor: '#667eea'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de red. Por favor intenta de nuevo.',
            confirmButtonColor: '#667eea'
        });
    });
});

// Reenviar email de verificacion
function resendVerificationEmail() {
    Swal.fire({
        title: '¿Reenviar email de verificación?',
        text: 'Te enviaremos un nuevo correo con el enlace de verificación.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, reenviar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Enviando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('/customer/resend-verification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: '_csrf_token=' + encodeURIComponent('<?= csrf_token() ?>')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Email enviado!',
                        text: 'Revisa tu correo electrónico y haz clic en el enlace de verificación.',
                        confirmButtonColor: '#667eea'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'No se pudo enviar el email de verificación.',
                        confirmButtonColor: '#667eea'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de red. Por favor intenta de nuevo.',
                    confirmButtonColor: '#667eea'
                });
            });
        }
    });
}

// Confirmar eliminacion de cuenta
function confirmDeleteAccount() {
    Swal.fire({
        title: '¿Eliminar tu cuenta?',
        html: `
            <div class="text-start">
                <p class="text-danger mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Esta accion es irreversible</strong></p>
                <p class="mb-3">Se eliminaran permanentemente:</p>
                <ul class="mb-3">
                    <li>Tu cuenta y datos personales</li>
                    <li>Historial de actividad</li>
                    <li>Configuraciones guardadas</li>
                </ul>
                <p class="mb-2"><strong>Confirma tu contrasena para continuar:</strong></p>
                <input type="password" id="delete-account-password" class="form-control" placeholder="Tu contrasena actual">
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-2"></i>Eliminar mi cuenta',
        cancelButtonText: 'Cancelar',
        focusCancel: true,
        preConfirm: () => {
            const password = document.getElementById('delete-account-password').value;
            if (!password) {
                Swal.showValidationMessage('Debes introducir tu contrasena');
                return false;
            }
            return password;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            Swal.fire({
                title: 'Eliminando cuenta...',
                html: 'Por favor espera mientras procesamos tu solicitud...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('/customer/delete-account', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: '_csrf_token=' + encodeURIComponent('<?= csrf_token() ?>') + '&password=' + encodeURIComponent(result.value)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cuenta eliminada',
                        text: 'Tu cuenta ha sido eliminada exitosamente. Gracias por usar MuseDock.',
                        confirmButtonColor: '#667eea',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = '/';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'No se pudo eliminar la cuenta. Verifica tu contrasena.',
                        confirmButtonColor: '#667eea'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de red. Por favor intenta de nuevo.',
                    confirmButtonColor: '#667eea'
                });
            });
        }
    });
}
</script>
@endsection
