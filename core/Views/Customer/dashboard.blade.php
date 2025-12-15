<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard - MuseDock' ?></title>
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
        }
        .stats-card .label {
            color: #666;
            font-size: 0.9rem;
        }
        .tenant-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }
        .tenant-card .domain {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .tenant-card .domain a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .tenant-card .domain a:hover {
            text-decoration: underline;
        }
        .tenant-card .info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .tenant-card .info .badge {
            padding: 6px 12px;
        }
        .badge-plan-free {
            background: #28a745;
        }
        .badge-plan-starter {
            background: #ffc107;
        }
        .badge-plan-business {
            background: #dc3545;
        }
        .badge-status-active {
            background: #28a745;
        }
        .badge-status-error {
            background: #dc3545;
        }
        .alert-verification {
            background: linear-gradient(135deg, #fff3cd, #ffe5a0);
            border: none;
            border-left: 4px solid #ffc107;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="/customer/dashboard">MuseDock</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/customer/profile">
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
                    <a class="nav-link active" href="/customer/dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="/customer/profile">
                        <i class="bi bi-person"></i> Mi Perfil
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <h2 class="mb-4">Bienvenido, <?= htmlspecialchars($customer['name']) ?></h2>

                <?php if ($show_verification_warning): ?>
                <div class="alert alert-verification" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Verifica tu email</strong> - Revisa tu bandeja de entrada para activar todas las funcionalidades.
                </div>
                <?php endif; ?>

                <!-- Estadísticas -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                                <i class="bi bi-globe"></i>
                            </div>
                            <div class="number"><?= $stats['total_tenants'] ?></div>
                            <div class="label">Total de sitios</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="icon" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="number"><?= $stats['active_tenants'] ?></div>
                            <div class="label">Sitios activos</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="icon" style="background: linear-gradient(135deg, #ffc107, #ff9800); color: white;">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div class="number"><?= $stats['cloudflare_protected'] ?></div>
                            <div class="label">Protegidos con Cloudflare</div>
                        </div>
                    </div>
                </div>

                <!-- Lista de tenants -->
                <h4 class="mt-4 mb-3">Mis Sitios</h4>

                <?php if (empty($tenants)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Aún no tienes sitios web. ¡Tu primer sitio ya está configurado! Accede desde la lista cuando esté listo.
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

                            <span class="badge badge-status-<?= $tenant['status'] === 'active' ? 'active' : 'error' ?>">
                                <?= $tenant['status'] === 'active' ? '✅ Activo' : '❌ ' . ucfirst($tenant['status']) ?>
                            </span>

                            <!-- Health Check Status -->
                            <?php if (isset($tenant['health_badge'])): ?>
                            <span class="badge <?= $tenant['health_status'] === 'healthy' ? 'bg-success' : ($tenant['health_status'] === 'degraded' ? 'bg-warning' : 'bg-danger') ?>"
                                  title="<?= htmlspecialchars(implode(', ', $tenant['health_check']['errors'] ?? [])) ?>">
                                <?= $tenant['health_badge'] ?>
                            </span>
                            <?php endif; ?>

                            <?php if ($tenant['cloudflare_proxied']): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-shield-fill-check"></i> Cloudflare Proxy
                            </span>
                            <?php endif; ?>

                            <?php if ($tenant['is_subdomain']): ?>
                            <span class="badge bg-info">
                                <i class="bi bi-link-45deg"></i> Subdominio
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Detalles del Health Check -->
                        <?php if (isset($tenant['health_check']) && !empty($tenant['health_check']['warnings'])): ?>
                        <div class="mt-2">
                            <small class="text-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?= implode(', ', $tenant['health_check']['warnings']) ?>
                            </small>
                        </div>
                        <?php endif; ?>

                        <div class="mt-3">
                            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>/<?= \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin') ?>"
                               class="btn btn-sm btn-primary" target="_blank">
                                <i class="bi bi-gear"></i> Panel de Admin
                            </a>
                            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>"
                               class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-eye"></i> Ver Sitio
                            </a>

                            <!-- Botón de Retry si hay problemas -->
                            <?php if (isset($tenant['needs_retry']) && $tenant['needs_retry']): ?>
                            <button class="btn btn-sm btn-warning" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                                <i class="bi bi-arrow-clockwise"></i> Reintentar Configuración
                            </button>
                            <?php endif; ?>

                            <!-- Botón Health Check Manual -->
                            <button class="btn btn-sm btn-outline-secondary" onclick="runHealthCheck(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                                <i class="bi bi-heart-pulse"></i> Verificar Estado
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Botón para solicitar dominio personalizado -->
    <div class="text-center mt-4">
        <a href="/customer/request-custom-domain" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle"></i> Solicitar Dominio Personalizado
        </a>
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
                    formData.append('_csrf_token', '<?= \Screenart\Musedock\Security\CSRFProtection::generateToken() ?>');

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

        /**
         * Reintentar provisioning de un tenant
         */
        function retryProvisioning(tenantId, domain) {
            Swal.fire({
                title: 'Reintentar Configuración',
                html: `¿Quieres reintentar la configuración de <strong>${domain}</strong>?<br><small>Esto volverá a ejecutar la configuración de Cloudflare y Caddy.</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, reintentar',
                cancelButtonText: 'Cancelar',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const formData = new FormData();
                    formData.append('_csrf_token', '<?= \Screenart\Musedock\Security\CSRFProtection::generateToken() ?>');

                    return fetch(`/customer/tenant/${tenantId}/retry`, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.error || 'Error desconocido');
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Error: ${error.message}`);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Configuración reiniciada!',
                        text: 'El sitio debería estar funcionando en unos momentos.',
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        }

        /**
         * Ejecutar health check manual
         */
        function runHealthCheck(tenantId, domain) {
            Swal.fire({
                title: 'Verificando Estado',
                html: `Comprobando el estado de <strong>${domain}</strong>...`,
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();

                    fetch(`/customer/tenant/${tenantId}/health-check`, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.error || 'Error desconocido');
                        }

                        const health = data.health_check;
                        let icon = 'success';
                        let title = '✅ Todo funciona correctamente';

                        if (health.overall_status === 'error') {
                            icon = 'error';
                            title = '❌ Se detectaron errores';
                        } else if (health.overall_status === 'degraded') {
                            icon = 'warning';
                            title = '⚠️ Funcionamiento degradado';
                        }

                        let html = '<div class="text-left">';

                        // DNS
                        if (health.checks.dns) {
                            html += `<p><strong>DNS:</strong> ${health.checks.dns.passed ? '✅' : '❌'} ${health.checks.dns.message}</p>`;
                        }

                        // HTTP
                        if (health.checks.http) {
                            html += `<p><strong>HTTP/HTTPS:</strong> ${health.checks.http.passed ? '✅' : '❌'} ${health.checks.http.message}</p>`;
                        }

                        // SSL
                        if (health.checks.ssl) {
                            html += `<p><strong>SSL:</strong> ${health.checks.ssl.passed ? '✅' : '❌'} ${health.checks.ssl.message}</p>`;
                        }

                        // Cloudflare
                        if (health.checks.cloudflare) {
                            html += `<p><strong>Cloudflare:</strong> ${health.checks.cloudflare.message}</p>`;
                        }

                        // Errores
                        if (health.errors && health.errors.length > 0) {
                            html += '<hr><p><strong>Errores:</strong></p><ul>';
                            health.errors.forEach(error => {
                                html += `<li class="text-danger">${error}</li>`;
                            });
                            html += '</ul>';
                        }

                        // Warnings
                        if (health.warnings && health.warnings.length > 0) {
                            html += '<hr><p><strong>Advertencias:</strong></p><ul>';
                            health.warnings.forEach(warning => {
                                html += `<li class="text-warning">${warning}</li>`;
                            });
                            html += '</ul>';
                        }

                        html += '</div>';

                        Swal.fire({
                            icon: icon,
                            title: title,
                            html: html,
                            confirmButtonColor: '#667eea',
                            width: 600
                        });
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message,
                            confirmButtonColor: '#667eea'
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>
