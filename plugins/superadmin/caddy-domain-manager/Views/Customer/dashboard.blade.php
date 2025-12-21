@extends('Customer.layout')

@section('styles')
<style>
    .dashboard-header {
        margin-bottom: 30px;
    }
    .dashboard-header h2 {
        font-size: 1.75rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 5px;
    }
    .dashboard-header p {
        color: #6b7280;
        font-size: 0.95rem;
        margin: 0;
    }

    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb;
        height: 100%;
    }
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #667eea;
    }
    .stats-card .icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin-bottom: 12px;
    }
    .stats-card .number {
        font-size: 1.875rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 4px;
        line-height: 1;
    }
    .stats-card .label {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
        margin: 30px 0 20px 0;
    }

    .tenant-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb;
    }
    .tenant-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #d1d5db;
        transform: translateY(-1px);
    }
    .tenant-card .domain {
        font-size: 1rem;
        font-weight: 600;
        color: #667eea;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tenant-card .domain i {
        font-size: 1.1rem;
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
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .tenant-card .info .badge {
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 500;
    }
    .badge-plan-free {
        background: #10b981;
        color: white;
    }
    .badge-plan-custom {
        background: #667eea;
        color: white;
    }
    .badge-status-active {
        background: #10b981;
        color: white;
    }
    .badge-status-error {
        background: #ef4444;
        color: white;
    }
    .badge-status-waiting_ns_change {
        background: #f59e0b;
        color: white;
    }
    .tenant-card .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .tenant-card .actions .btn {
        font-size: 0.875rem;
        padding: 6px 14px;
        border-radius: 6px;
    }
    .action-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 30px;
        flex-wrap: wrap;
    }
    .action-buttons .btn {
        font-size: 0.95rem;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 500;
    }
</style>
@endsection

@section('content')
<div class="dashboard-header">
    <h2>Dashboard</h2>
    <p>Bienvenido a tu panel de control</p>
</div>

<!-- Estadisticas -->
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
<h4 class="section-title">Mis Sitios</h4>

<?php if (empty($tenants)): ?>
<div class="alert alert-info" style="border-radius: 10px; border: 1px solid #3b82f6; background: #eff6ff;">
    <i class="bi bi-info-circle me-2"></i>
    Aún no tienes sitios web. Solicita tu primer subdominio FREE o incorpora tu dominio personalizado!
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
                    'active' => 'Activo',
                    'pending' => 'Pendiente',
                    'waiting_ns_change' => 'Esperando DNS',
                    'error' => 'Error',
                    'suspended' => 'Suspendido'
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

        <div class="actions">
            <?php if ($tenant['status'] === 'active'): ?>
            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>/<?= \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin') ?>"
               class="btn btn-sm btn-primary" target="_blank">
                <i class="bi bi-gear"></i> Admin
            </a>
            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>"
               class="btn btn-sm btn-outline-primary" target="_blank">
                <i class="bi bi-eye"></i> Ver Sitio
            </a>
            <?php if (!empty($tenant['cloudflare_zone_id'])): ?>
            <a href="/customer/domain/<?= $tenant['id'] ?>/manage" class="btn btn-sm btn-outline-info">
                <i class="bi bi-envelope-at"></i> DNS / Email
            </a>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary" onclick="runHealthCheck(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                <i class="bi bi-heart-pulse"></i> Verificar
            </button>
            <?php elseif ($tenant['status'] === 'waiting_ns_change'): ?>
            <span class="text-warning small">
                <i class="bi bi-hourglass-split"></i> Esperando cambio de nameservers...
            </span>
            <?php if (!empty($tenant['cloudflare_zone_id'])): ?>
            <a href="/customer/domain/<?= $tenant['id'] ?>/manage" class="btn btn-sm btn-outline-info">
                <i class="bi bi-envelope-at"></i> Gestionar
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Boton de Retry/Verificar si hay problemas -->
            <?php if (isset($tenant['needs_retry']) && $tenant['needs_retry']): ?>
                <?php
                $isCustomDomain = !empty($tenant['cloudflare_zone_id']) && empty($tenant['is_subdomain']);
                $isWaitingNS = ($tenant['status'] ?? '') === 'waiting_ns_change';
                ?>
                <?php if ($isCustomDomain && $isWaitingNS): ?>
                <button class="btn btn-sm btn-info" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                    <i class="bi bi-check2-circle"></i> Verificar NS
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-warning" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                    <i class="bi bi-arrow-clockwise"></i> Reintentar
                </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Botones de accion -->
<div class="action-buttons">
    <a href="/customer/request-free-subdomain" class="btn btn-success">
        <i class="bi bi-gift me-2"></i>Solicitar Subdominio FREE
    </a>
    <a href="/customer/request-custom-domain" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Incorporar Dominio Existente
    </a>
    <a href="/customer/register-domain" class="btn btn-info">
        <i class="bi bi-bag-plus me-2"></i>Registrar Nuevo Dominio
    </a>
</div>
@endsection

@section('scripts')
<script>
function retryProvisioning(tenantId, domain) {
    Swal.fire({
        title: 'Verificar configuracion?',
        html: `Deseas verificar la configuracion de <strong>${domain}</strong>?<br><small class="text-muted">Para dominios personalizados, verificaremos si los nameservers han sido cambiados.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, verificar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                html: 'Verificando configuracion...',
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
                body: `_csrf_token=${encodeURIComponent('<?= csrf_token() ?>')}`
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
                            title: 'Dominio Activado!',
                            text: data.message,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        // Respuesta generica de exito
                        Swal.fire({
                            icon: 'success',
                            title: 'Exito!',
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
                        text: data.error || 'Error al verificar la configuracion',
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
@endsection
