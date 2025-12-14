<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Mi Perfil - MuseDock' ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-custom {
            background: #f8f9fa;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #e0e0e0;
        }
        .navbar-custom .navbar-brand {
            color: #333 !important;
            font-weight: bold;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-custom .navbar-brand img {
            max-height: 40px;
            width: auto;
        }
        .navbar-custom .nav-link {
            color: #555 !important;
        }
        .navbar-custom .nav-link:hover {
            color: var(--primary-color) !important;
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            border-right: 1px solid #e0e0e0;
            padding: 20px;
        }
        .sidebar .user-info {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }
        .sidebar .user-info .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: #f8f9fa;
            color: var(--primary-color);
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd6, #6a4292);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="/customer/dashboard">
                <img src="/themes/play-bootstrap/img/logo/logo.svg" alt="MuseDock" onerror="this.style.display='none';">
                <span>MuseDock</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/customer/profile">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($customer['name']) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="logout(); return false;">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
                <div class="user-info">
                    <div class="avatar"><?= strtoupper(substr($customer['name'], 0, 1)) ?></div>
                    <h6><?= htmlspecialchars($customer['name']) ?></h6>
                    <small class="text-muted"><?= htmlspecialchars($customer['email']) ?></small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="/customer/dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="/customer/profile">
                        <i class="bi bi-person"></i> Mi Perfil
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <h2 class="mb-4">Mi Perfil</h2>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Información Personal</h5>
                            </div>
                            <div class="card-body">
                                <form id="profileForm" onsubmit="updateProfile(event)">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nombre Completo</label>
                                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($customer['name']) ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" value="<?= htmlspecialchars($customer['email']) ?>" disabled>
                                        <div class="form-text">El email no se puede cambiar. Contacta a soporte si necesitas actualizarlo.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Empresa / Organización</label>
                                        <input type="text" class="form-control" name="company" value="<?= htmlspecialchars($customer['company'] ?? '') ?>">
                                    </div>

                                    <hr class="my-4">
                                    <h6 class="mb-3">Cambiar Contraseña (Opcional)</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Nueva Contraseña</label>
                                        <input type="password" class="form-control" name="password" minlength="8">
                                        <div class="form-text">Déjalo en blanco si no quieres cambiarla. Mínimo 8 caracteres.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Confirmar Contraseña</label>
                                        <input type="password" class="form-control" name="password_confirmation">
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-2"></i>Guardar Cambios
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Estado de Cuenta</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="text-muted d-block mb-1">Estado</label>
                                    <?php if ($customer['status'] === 'active'): ?>
                                        <span class="badge bg-success p-2"><i class="bi bi-check-circle me-1"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark p-2"><i class="bi bi-exclamation-triangle me-1"></i> <?= ucfirst($customer['status']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-muted d-block mb-1">Miembro desde</label>
                                    <strong><?= date('d/m/Y', strtotime($customer['created_at'])) ?></strong>
                                </div>

                                <div class="mb-3">
                                    <label class="text-muted d-block mb-1">Último acceso</label>
                                    <strong><?= $customer['last_login_at'] ? date('d/m/Y H:i', strtotime($customer['last_login_at'])) : 'Nunca' ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function logout() {
            Swal.fire({
                title: '¿Cerrar sesión?',
                text: "¿Estás seguro que deseas salir?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('_csrf_token', '<?= $csrf_token ?>');

                    fetch('/customer/logout', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect || '/customer/login';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        window.location.href = '/customer/login';
                    });
                }
            });
        }

        function updateProfile(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            
            // Validar contraseñas
            const password = formData.get('password');
            const confirm = formData.get('password_confirmation');
            
            if (password && password !== confirm) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden'
                });
                return;
            }

            Swal.fire({
                title: 'Guardando...',
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('/customer/profile/update', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: 'Tu perfil ha sido actualizado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Ocurrió un error al actualizar el perfil'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ocurrió un error de conexión'
                });
            });
        }
    </script>
</body>
</html>
