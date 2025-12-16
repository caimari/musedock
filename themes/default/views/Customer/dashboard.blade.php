<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard - MuseDock' ?></title>
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-custom .navbar-brand {
            color: white !important;
            font-weight: bold;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        .navbar-custom .navbar-brand img {
            height: 35px;
            margin-right: 10px;
        }
        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.9) !important;
        }
        .navbar-custom .nav-link:hover {
            color: white !important;
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
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .stats-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .stats-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .stats-card .label {
            color: #999;
            font-size: 0.9rem;
        }
        .tenant-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .tenant-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .tenant-card .domain {
            font-size: 1.1rem;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 10px;
        }
        .tenant-card .domain a {
            color: #667eea;
            text-decoration: none;
        }
        .tenant-card .domain a:hover {
            text-decoration: underline;
        }
        .tenant-card .info {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .badge-plan-free {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .badge-plan-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .badge-status-active {
            background: #28a745;
            color: white;
        }
        .badge-status-error {
            background: #dc3545;
            color: white;
        }
        .badge-status-waiting_ns_change {
            background: #ffc107;
            color: #333;
        }
        .main-content {
            padding: 30px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="/customer/dashboard">
                <img src="/assets/logo2_footer.png" alt="MuseDock" style="height: 35px; filter: brightness(0) invert(1);">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($customer['name']) ?>
                        </span>
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
                    <a class="nav-link active" href="/customer/dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="/customer/profile">
                        <i class="bi bi-person"></i> Mi Perfil
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Dashboard</h2>

                <!-- Estadísticas -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                                <i class="bi bi-globe"></i>
                            </div>
                            <div class="number"><?= $stats['total_tenants'] ?? 0 ?></div>
                            <div class="label">Total de sitios</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="icon" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="number"><?= $stats['active_tenants'] ?? 0 ?></div>
                            <div class="label">Sitios activos</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="icon" style="background: linear-gradient(135deg, #ffc107, #ff9800); color: white;">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div class="number"><?= $stats['cloudflare_protected'] ?? 0 ?></div>
                            <div class="label">Protegidos con Cloudflare</div>
                        </div>
                    </div>
                </div>

                <!-- Lista de tenants -->
                <h4 class="mt-4 mb-3">Mis Sitios</h4>

                <?php if (empty($tenants)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Aún no tienes sitios web. ¡Solicita tu primer subdominio FREE o incorpora tu dominio personalizado!
                </div>
                <?php else: ?>
                    <?php foreach ($tenants as $tenant): ?>
                    <div class="tenant-card">
                        <div class="domain">
                            <i class="bi bi-globe"></i>
                            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>" target="_blank">
                                <?= htmlspecialchars($tenant['domain']) ?>
                            </a>
                        </div>

                        <div class="info">
                            <span class="badge badge-plan-<?= strtolower($tenant['plan']) ?>">
                                Plan <?= strtoupper($tenant['plan']) ?>
                            </span>

                            <span class="badge badge-status-<?= $tenant['status'] ?>">
                                <?php
                                $statusLabels = [
                                    'active' => '✅ Activo',
                                    'pending' => '⏳ Pendiente',
                                    'waiting_ns_change' => '🔄 Esperando DNS',
                                    'error' => '❌ Error',
                                    'suspended' => '⛔ Suspendido'
                                ];
                                echo $statusLabels[$tenant['status']] ?? ucfirst($tenant['status']);
                                ?>
                            </span>

                            <!-- Health Check Status -->
                            <?php if (isset($tenant['health_badge'])): ?>
                            <span class="badge <?= $tenant['health_status'] === 'healthy' ? 'bg-success' : ($tenant['health_status'] === 'degraded' ? 'bg-warning' : 'bg-danger') ?>"
                                  title="<?= htmlspecialchars(implode(', ', $tenant['health_check']['errors'] ?? [])) ?>">
                                <?= $tenant['health_badge'] ?>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($tenant['cloudflare_proxied'])): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-shield-fill-check"></i> Cloudflare
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($tenant['is_subdomain'])): ?>
                            <span class="badge bg-info">
                                <i class="bi bi-link-45deg"></i> Subdominio
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <?php if ($tenant['status'] === 'active'): ?>
                            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>/<?= \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin') ?>"
                               class="btn btn-sm btn-primary" target="_blank">
                                <i class="bi bi-gear"></i> Panel de Admin
                            </a>
                            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>"
                               class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-eye"></i> Ver Sitio
                            </a>
                            <?php elseif ($tenant['status'] === 'waiting_ns_change'): ?>
                            <span class="text-warning">
                                <i class="bi bi-hourglass-split"></i> Esperando cambio de nameservers...
                            </span>
                            <?php endif; ?>

                            <!-- Botón de Retry si hay problemas -->
                            <?php if (isset($tenant['needs_retry']) && $tenant['needs_retry']): ?>
                            <button class="btn btn-sm btn-warning" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                                <i class="bi bi-arrow-clockwise"></i> Reintentar
                            </button>
                            <?php endif; ?>

                            <!-- Botón Health Check Manual -->
                            <?php if ($tenant['status'] === 'active'): ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="runHealthCheck(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                                <i class="bi bi-heart-pulse"></i> Verificar
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Botones de acción -->
                <div class="text-center mt-4 mb-4">
                    <a href="/customer/request-free-subdomain" class="btn btn-success btn-lg me-2">
                        <i class="bi bi-gift"></i> Solicitar Subdominio FREE
                    </a>
                    <a href="/customer/request-custom-domain" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle"></i> Solicitar Dominio Personalizado
                    </a>
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
                    window.location.href = '/customer/logout';
                }
            });
        }

        function retryProvisioning(tenantId, domain) {
            Swal.fire({
                title: '¿Reintentar configuración?',
                html: `¿Deseas reintentar la configuración de <strong>${domain}</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, reintentar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Procesando...',
                        html: 'Reintentando configuración...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch(`/customer/tenant/${tenantId}/retry`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `_csrf_token=${encodeURIComponent('<?= generate_csrf_token() ?>')}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: data.message,
                                confirmButtonColor: '#667eea'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.error || 'Error al reintentar la configuración',
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

        function runHealthCheck(tenantId, domain) {
            Swal.fire({
                title: 'Verificando estado...',
                html: `Ejecutando health check de <strong>${domain}</strong>...`,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/customer/tenant/${tenantId}/health-check`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const healthCheck = data.health_check;
                        let htmlContent = '<div class="text-start">';

                        // DNS
                        htmlContent += `<p class="mb-2"><strong>DNS:</strong> ${healthCheck.checks.dns.passed ? '✅' : '❌'} ${healthCheck.checks.dns.message}</p>`;

                        // HTTP
                        htmlContent += `<p class="mb-2"><strong>HTTP:</strong> ${healthCheck.checks.http.passed ? '✅' : '❌'} ${healthCheck.checks.http.message}</p>`;

                        // SSL
                        htmlContent += `<p class="mb-2"><strong>SSL:</strong> ${healthCheck.checks.ssl.passed ? '✅' : '❌'} ${healthCheck.checks.ssl.message}</p>`;

                        // Cloudflare
                        if (healthCheck.checks.cloudflare) {
                            htmlContent += `<p class="mb-0"><strong>Cloudflare:</strong> ${healthCheck.checks.cloudflare.passed ? '✅' : '❌'} ${healthCheck.checks.cloudflare.message}</p>`;
                        }

                        htmlContent += '</div>';

                        Swal.fire({
                            icon: healthCheck.overall_status === 'healthy' ? 'success' : (healthCheck.overall_status === 'degraded' ? 'warning' : 'error'),
                            title: 'Estado del Sitio',
                            html: htmlContent,
                            confirmButtonColor: '#667eea'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error || 'Error al verificar el estado',
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
    </script>
</body>
</html>
