<style>
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
</style>

<h2 class="mb-4">Dashboard</h2>

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
    Aún no tienes sitios web. ¡Solicita tu primer subdominio FREE!
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

            <!-- Botón de Retry/Verificar si hay problemas -->
            <?php if (isset($tenant['needs_retry']) && $tenant['needs_retry']): ?>
                <?php
                $isCustomDomain = !empty($tenant['cloudflare_zone_id']) && empty($tenant['is_subdomain']);
                $isWaitingNS = ($tenant['status'] ?? '') === 'waiting_ns_change';
                ?>
                <?php if ($isCustomDomain && $isWaitingNS): ?>
                <button class="btn btn-sm btn-info" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                    <i class="bi bi-check2-circle"></i> Verificar Nameservers
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-warning" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                    <i class="bi bi-arrow-clockwise"></i> Reintentar Configuración
                </button>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Botón Health Check Manual -->
            <button class="btn btn-sm btn-outline-secondary" onclick="runHealthCheck(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                <i class="bi bi-heart-pulse"></i> Verificar Estado
            </button>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function retryProvisioning(tenantId, domain) {
    Swal.fire({
        title: '¿Verificar configuración?',
        html: `¿Deseas verificar la configuración de <strong>${domain}</strong>?<br><small class="text-muted">Para dominios personalizados, verificaremos si los nameservers han sido cambiados.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, verificar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                html: 'Verificando configuración...',
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
                    // Verificar si es un dominio con NS pendiente
                    if (data.status === 'waiting_ns_change' && data.nameservers) {
                        let nsHtml = '<div class="text-start mt-3">';
                        nsHtml += '<p>' + data.message + '</p>';
                        nsHtml += '<p><strong>Nameservers requeridos:</strong></p>';
                        nsHtml += '<div class="bg-light p-3 rounded">';
                        data.nameservers.forEach((ns, i) => {
                            nsHtml += `<p class="mb-1"><code>NS${i+1}: ${ns}</code></p>`;
                        });
                        nsHtml += '</div>';
                        nsHtml += '<p class="mt-3 text-muted small"><i class="bi bi-info-circle me-1"></i>Los cambios de DNS pueden tardar hasta 48 horas en propagarse.</p>';
                        nsHtml += '</div>';

                        Swal.fire({
                            icon: 'info',
                            title: 'Esperando cambio de DNS',
                            html: nsHtml,
                            confirmButtonColor: '#667eea',
                            confirmButtonText: 'Entendido'
                        });
                    } else if (data.status === 'active') {
                        // Dominio activado
                        Swal.fire({
                            icon: 'success',
                            title: '¡Dominio Activado!',
                            text: data.message,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        // Respuesta genérica de éxito
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: data.message,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Error al verificar la configuración',
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
                }).then(() => {
                    window.location.reload();
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
