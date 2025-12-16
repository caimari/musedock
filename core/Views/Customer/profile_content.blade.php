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

<h2 class="mb-4">Mi Perfil</h2>

<div class="row">
    <div class="col-md-8">
        <!-- Información Personal -->
        <div class="profile-card">
            <h5><i class="bi bi-person-circle"></i> Información Personal</h5>
            <form id="profileForm" method="POST" action="/customer/profile/update">
                <input type="hidden" name="_csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre completo</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($customer['name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($customer['email']) ?>" disabled>
                    <small class="text-muted">El email no se puede cambiar</small>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Teléfono (opcional)</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Guardar Cambios
                </button>
            </form>
        </div>

        <!-- Cambiar Contraseña -->
        <div class="profile-card">
            <h5><i class="bi bi-shield-lock"></i> Cambiar Contraseña</h5>
            <form id="passwordForm" method="POST" action="/customer/profile/change-password">
                <input type="hidden" name="_csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="mb-3">
                    <label for="current_password" class="form-label">Contraseña actual</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">Nueva contraseña</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <small class="text-muted">Mínimo 8 caracteres</small>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmar nueva contraseña</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-key"></i> Cambiar Contraseña
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Estadísticas de Cuenta -->
        <div class="profile-card">
            <h5><i class="bi bi-graph-up"></i> Estadísticas</h5>
            <div class="mb-3">
                <small class="text-muted">Sitios totales</small>
                <div class="fs-4 fw-bold text-primary"><?= $stats['total_tenants'] ?></div>
            </div>
            <div class="mb-3">
                <small class="text-muted">Sitios activos</small>
                <div class="fs-4 fw-bold text-success"><?= $stats['active_tenants'] ?></div>
            </div>
            <div class="mb-3">
                <small class="text-muted">Miembro desde</small>
                <div class="text-muted"><?= date('d/m/Y', strtotime($customer['created_at'])) ?></div>
            </div>
        </div>

        <!-- Estado de la Cuenta -->
        <div class="profile-card">
            <h5><i class="bi bi-info-circle"></i> Estado de Cuenta</h5>
            <?php if ($customer['status'] === 'active'): ?>
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle"></i> Cuenta activa
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i> <?= ucfirst($customer['status']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                title: '¡Perfil actualizado!',
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

// Cambiar contraseña
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (newPassword !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Las contraseñas no coinciden',
            confirmButtonColor: '#667eea'
        });
        return;
    }

    if (newPassword.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'La contraseña debe tener al menos 8 caracteres',
            confirmButtonColor: '#667eea'
        });
        return;
    }

    const formData = new FormData(this);

    Swal.fire({
        title: 'Cambiando contraseña...',
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
                title: '¡Contraseña cambiada!',
                text: 'Tu contraseña ha sido actualizada exitosamente.',
                confirmButtonColor: '#667eea'
            }).then(() => {
                this.reset();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Error al cambiar la contraseña',
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
</script>
